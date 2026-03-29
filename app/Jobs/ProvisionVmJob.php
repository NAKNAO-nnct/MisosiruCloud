<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Repositories\VmMetaRepository;
use App\Services\VmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

final class ProvisionVmJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    /**
     * @param array<string, mixed> $templateParams ['template_vmid' => int, 'cpu' => int|null, 'memory_mb' => int|null, 'disk_gb' => int|null]
     */
    public function __construct(
        private int $vmMetaId,
        private array $templateParams,
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
            $vmService->provisionVm($vmMeta, $this->templateParams);
        } catch (Throwable $e) {
            throw new RuntimeException("Failed to provision VM (id={$this->vmMetaId}): {$e->getMessage()}", 0, $e);
        }
    }
}
