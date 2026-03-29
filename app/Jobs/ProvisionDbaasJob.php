<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Data\Dbaas\ProvisionDbaasCommand;
use App\Repositories\TenantRepository;
use App\Services\DbaasService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

final class ProvisionDbaasJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        private ProvisionDbaasCommand $command,
    ) {
        $this->onConnection('provisioning');
        $this->queue = 'provisioning';
    }

    public function handle(
        TenantRepository $tenantRepository,
        DbaasService $dbaasService,
    ): void {
        $tenantId = $this->command->getTenantId();
        $tenant = $tenantRepository->findByIdOrFail($tenantId);

        try {
            $dbaasService->provision($tenant, $this->command->toArray());
        } catch (Throwable $e) {
            throw new RuntimeException("Failed to provision DBaaS for tenant {$tenantId}: {$e->getMessage()}", 0, $e);
        }
    }
}
