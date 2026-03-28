<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Lib\Proxmox\ProxmoxApiFactory;
use App\Repositories\ProxmoxNodeRepository;
use Illuminate\View\View;
use Throwable;

class IndexController extends Controller
{
    public function __construct(
        private readonly ProxmoxNodeRepository $proxmoxNodeRepository,
        private readonly ProxmoxApiFactory $proxmoxApiFactory,
    ) {
    }

    public function __invoke(): View
    {
        $clusters = $this->proxmoxNodeRepository->all();
        $activeClusterCpuPercent = null;
        $nodeCpuUsages = [];
        $clusterCpuPercents = [];
        $clusterFetchErrors = [];

        foreach ($clusters as $cluster) {
            try {
                $api = $this->proxmoxApiFactory->forCluster($cluster);
                $nodes = $api->node()->listNodes();
                $cpus = [];

                foreach ($nodes as $node) {
                    $nodeName = (string) ($node['node'] ?? '');

                    if ($nodeName === '') {
                        continue;
                    }

                    $status = $api->node()->getNodeStatus($nodeName);
                    $cpus[] = $status->cpu;
                    $nodeCpuUsages[] = [
                        'cluster' => $cluster->getName(),
                        'node' => $nodeName,
                        'status' => $status->status,
                        'cpuPercent' => $status->cpu * 100,
                    ];
                }

                if ($cpus !== []) {
                    $clusterCpuPercents[$cluster->getId()] = array_sum($cpus) / count($cpus) * 100;
                }
            } catch (Throwable $e) {
                $clusterFetchErrors[$cluster->getId()] = $e->getMessage();

                logger()->warning('Failed to fetch Proxmox node status for cluster.', [
                    'cluster_id' => $cluster->getId(),
                    'cluster_name' => $cluster->getName(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $activeCluster = $clusters->first(fn ($cluster) => $cluster->isActive());

        if ($activeCluster) {
            $activeClusterCpuPercent = $clusterCpuPercents[$activeCluster->getId()] ?? null;
        }

        return view('dashboard', [
            'clusters' => $clusters,
            'clusterCount' => $clusters->count(),
            'activeClusterCount' => $clusters->filter(fn ($cluster) => $cluster->isActive())->count(),
            'activeClusterCpuPercent' => $activeClusterCpuPercent,
            'nodeCpuUsages' => $nodeCpuUsages,
            'clusterCpuPercents' => $clusterCpuPercents,
            'clusterFetchErrors' => $clusterFetchErrors,
        ]);
    }
}
