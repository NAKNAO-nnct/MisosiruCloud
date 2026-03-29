<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Repositories\VmMetaRepository;
use App\Services\VmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

final class DestroyVmJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        private int $vmMetaId,
    ) {
        $this->onConnection('provisioning');
        $this->queue = 'provisioning';
    }

    public function handle(
        VmMetaRepository $vmMetaRepository,
        VmService $vmService,
    ): void {
        $vmMeta = $vmMetaRepository->findByIdOrFail($this->vmMetaId);

        try {
            $vmService->terminateVm($vmMeta);
        } catch (Throwable $e) {
            throw new RuntimeException("Failed to destroy VM {$vmMeta->getProxmoxVmid()}: {$e->getMessage()}", 0, $e);
        }
    }
}
