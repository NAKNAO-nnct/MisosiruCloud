<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Data\Vm\ProvisionVmCommand;
use App\Repositories\TenantRepository;
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

    public function __construct(
        private ProvisionVmCommand $command,
    ) {
        $this->onConnection('provisioning');
        $this->queue = 'provisioning';
    }

    public function handle(
        TenantRepository $tenantRepository,
        VmService $vmService,
    ): void {
        $tenantId = $this->command->getTenantId();
        $tenant = $tenantRepository->findByIdOrFail($tenantId);

        try {
            $vmService->provisionVm($tenant, $this->command->toArray());
        } catch (Throwable $e) {
            throw new RuntimeException("Failed to provision VM for tenant {$tenantId}: {$e->getMessage()}", 0, $e);
        }
    }
}
