<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Container\ContainerJobData;
use App\Data\Tenant\TenantData;
use App\Lib\Nomad\NomadApi;
use App\Repositories\ContainerJobRepository;
use App\Repositories\TenantRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ContainerService
{
    public function __construct(
        private readonly ?NomadApi $nomadApi,
        private readonly ContainerJobRepository $containerJobRepository,
        private readonly TenantRepository $tenantRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $params
     */
    public function deployContainer(TenantData $tenant, array $params): ContainerJobData
    {
        $this->ensureNomadConfigured();

        $namespace = $tenant->getNomadNamespace() ?: $tenant->getSlug();
        $this->ensureNamespaceExists($namespace);

        $name = (string) $params['name'];
        $jobId = $this->buildJobId($tenant, $name);

        $containerJob = DB::transaction(fn (): ContainerJobData => $this->containerJobRepository->create([
            'tenant_id' => $tenant->getId(),
            'nomad_job_id' => $jobId,
            'name' => (string) $params['name'],
            'image' => (string) $params['image'],
            'domain' => isset($params['domain']) ? (string) $params['domain'] : null,
            'replicas' => (int) ($params['replicas'] ?? 1),
            'cpu_mhz' => (int) $params['cpu_mhz'],
            'memory_mb' => (int) $params['memory_mb'],
            'port_mappings' => isset($params['port_mappings']) && is_array($params['port_mappings'])
                ? $params['port_mappings']
                : null,
            'env_vars_encrypted' => isset($params['env_vars']) && is_array($params['env_vars'])
                ? json_encode($params['env_vars'], JSON_UNESCAPED_SLASHES)
                : null,
        ]));

        $jobSpec = $this->buildJobSpec($tenant, $containerJob);

        $this->nomadApi->job()->registerJob([
            'Job' => $jobSpec,
        ]);

        return $containerJob;
    }

    public function restartContainer(ContainerJobData $job): void
    {
        $this->ensureNomadConfigured();

        $tenant = $this->tenantRepository->findByIdOrFail($job->getTenantId());
        $namespace = $tenant->getNomadNamespace() ?: $tenant->getSlug();

        $this->nomadApi->job()->stopJob($job->getNomadJobId(), $namespace, false);
        $this->nomadApi->job()->registerJob([
            'Job' => $this->buildJobSpec($tenant, $job),
        ]);
    }

    public function scaleContainer(ContainerJobData $job, int $replicas): void
    {
        $this->ensureNomadConfigured();

        $tenant = $this->tenantRepository->findByIdOrFail($job->getTenantId());
        $namespace = $tenant->getNomadNamespace() ?: $tenant->getSlug();

        $this->nomadApi->job()->scaleJob($job->getNomadJobId(), $replicas, $namespace);

        $this->containerJobRepository->update($job->getId(), [
            'replicas' => $replicas,
        ]);
    }

    public function terminateContainer(ContainerJobData $job): void
    {
        $this->ensureNomadConfigured();

        $tenant = $this->tenantRepository->findByIdOrFail($job->getTenantId());
        $namespace = $tenant->getNomadNamespace() ?: $tenant->getSlug();

        $this->nomadApi->job()->stopJob($job->getNomadJobId(), $namespace, true);
        $this->containerJobRepository->delete($job->getId());
    }

    public function getLogs(ContainerJobData $job, string $taskName): string
    {
        $this->ensureNomadConfigured();

        $allocations = $this->nomadApi->job()->getJobAllocations($job->getNomadJobId());

        if (empty($allocations)) {
            return '';
        }

        $firstAllocation = $allocations[0];
        $allocId = (string) ($firstAllocation['ID'] ?? '');

        if ($allocId === '') {
            return '';
        }

        return $this->nomadApi->job()->getJobLogs($allocId, $taskName);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildJobSpec(TenantData $tenant, ContainerJobData $job): array
    {
        $namespace = $tenant->getNomadNamespace() ?: $tenant->getSlug();
        $portMappings = $job->getPortMappings() ?? [];

        $networkPorts = [];

        foreach ($portMappings as $mapping) {
            $label = (string) ($mapping['label'] ?? 'http');
            $to = (int) ($mapping['to'] ?? 80);
            $value = isset($mapping['value']) ? (int) $mapping['value'] : null;

            $portConfig = ['to' => $to];

            if ($value !== null && $value > 0) {
                $portConfig['static'] = $value;
            }

            $networkPorts[$label] = $portConfig;
        }

        if ($networkPorts === []) {
            $networkPorts = [
                'http' => ['to' => 80],
            ];
        }

        $serviceTags = $this->buildTraefikTags($job);

        return [
            'ID' => $job->getNomadJobId(),
            'Name' => $job->getNomadJobId(),
            'Namespace' => $namespace,
            'Type' => 'service',
            'Datacenters' => [(string) config('services.nomad.datacenter', 'dc1')],
            'TaskGroups' => [[
                'Name' => 'app',
                'Count' => $job->getReplicas(),
                'Networks' => [[
                    'Mode' => 'bridge',
                    'DynamicPorts' => [],
                    'ReservedPorts' => $networkPorts,
                ]],
                'Tasks' => [[
                    'Name' => 'app',
                    'Driver' => 'docker',
                    'Config' => [
                        'image' => $job->getImage(),
                        'ports' => array_keys($networkPorts),
                    ],
                    'Resources' => [
                        'CPU' => $job->getCpuMhz(),
                        'MemoryMB' => $job->getMemoryMb(),
                    ],
                    'Env' => $this->decodeEnvVars($job->getEnvVarsEncrypted()),
                    'Services' => [[
                        'Name' => $job->getNomadJobId(),
                        'PortLabel' => (string) array_key_first($networkPorts),
                        'Tags' => $serviceTags,
                    ]],
                ]],
            ]],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function buildTraefikTags(ContainerJobData $job): array
    {
        $domain = $job->getDomain();

        if (!$domain) {
            return [];
        }

        $serviceName = $job->getNomadJobId();

        return [
            'traefik.enable=true',
            "traefik.http.routers.{$serviceName}.rule=Host(`{$domain}`)",
            "traefik.http.routers.{$serviceName}.entrypoints=websecure",
            "traefik.http.services.{$serviceName}.loadbalancer.server.port=80",
        ];
    }

    private function ensureNomadConfigured(): void
    {
        if (!$this->nomadApi) {
            throw new RuntimeException('Nomad API is not configured.');
        }
    }

    private function ensureNamespaceExists(string $namespace): void
    {
        $namespaces = $this->nomadApi->namespace()->listNamespaces();

        foreach ($namespaces as $item) {
            if (($item['Name'] ?? null) === $namespace) {
                return;
            }
        }

        $this->nomadApi->namespace()->createNamespace($namespace, "Tenant namespace: {$namespace}");
    }

    private function buildJobId(TenantData $tenant, string $name): string
    {
        $base = Str::slug($tenant->getSlug() . '-' . $name);

        return mb_substr($base, 0, 120);
    }

    /**
     * @return array<string, string>
     */
    private function decodeEnvVars(?string $encoded): array
    {
        if (!$encoded) {
            return [];
        }

        $decoded = json_decode($encoded, true);

        if (!is_array($decoded)) {
            return [];
        }

        $result = [];

        foreach ($decoded as $key => $value) {
            if (is_string($key) && (is_scalar($value) || $value === null)) {
                $result[$key] = (string) ($value ?? '');
            }
        }

        return $result;
    }
}
