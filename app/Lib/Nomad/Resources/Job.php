<?php

declare(strict_types=1);

namespace App\Lib\Nomad\Resources;

use App\Lib\Nomad\Client;

class Job
{
    public function __construct(private readonly Client $client)
    {
    }

    public function listJobs(string $namespace = ''): array
    {
        $params = $namespace !== '' ? ['namespace' => $namespace] : [];

        return $this->client->get('jobs', $params);
    }

    public function getJob(string $jobId, string $namespace = ''): array
    {
        $params = $namespace !== '' ? ['namespace' => $namespace] : [];

        return $this->client->get("job/{$jobId}", $params);
    }

    public function registerJob(array $jobSpec): array
    {
        return $this->client->put('jobs', $jobSpec);
    }

    public function stopJob(string $jobId, string $namespace = '', bool $purge = false): array
    {
        $params = [];

        if ($namespace !== '') {
            $params['namespace'] = $namespace;
        }

        if ($purge) {
            $params['purge'] = true;
        }

        return $this->client->delete("job/{$jobId}", $params);
    }

    public function getJobAllocations(string $jobId): array
    {
        return $this->client->get("job/{$jobId}/allocations");
    }

    public function scaleJob(string $jobId, int $count, string $namespace = ''): array
    {
        $payload = ['Count' => $count];

        if ($namespace !== '') {
            $payload['Namespace'] = $namespace;
        }

        return $this->client->post("job/{$jobId}/scale", $payload);
    }

    public function getJobLogs(string $allocId, string $taskName, string $logType = 'stdout'): string
    {
        return $this->client->getRaw("client/fs/logs/{$allocId}", [
            'task' => $taskName,
            'type' => $logType,
            'plain' => true,
        ]);
    }
}
