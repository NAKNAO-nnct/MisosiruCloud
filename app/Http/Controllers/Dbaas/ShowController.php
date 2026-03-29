<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dbaas;

use App\Data\Dbaas\DbaasDetailResponseData;
use App\Http\Controllers\Controller;
use App\Repositories\BackupScheduleRepository;
use App\Repositories\DatabaseInstanceRepository;
use App\Repositories\TenantRepository;
use App\Services\BackupService;
use App\Services\DbaasService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShowController extends Controller
{
    public function __construct(
        private readonly DatabaseInstanceRepository $databaseInstanceRepository,
        private readonly BackupScheduleRepository $backupScheduleRepository,
        private readonly DbaasService $dbaasService,
        private readonly BackupService $backupService,
        private readonly TenantRepository $tenantRepository,
    ) {
    }

    public function __invoke(Request $request, int $database): View
    {
        $db = $this->databaseInstanceRepository->findByIdOrFail($database);
        $backupSchedule = $this->backupScheduleRepository->findByDatabaseInstanceId($db->getId());
        $responseData = DbaasDetailResponseData::make([
            'database' => $db,
            'connection' => $this->dbaasService->getConnectionDetails($db),
            'backups' => $this->backupService->listBackups($db),
            'backupSchedule' => $backupSchedule,
            'tenantName' => $this->tenantRepository->findByIdOrFail($db->getTenantId())->getName(),
        ]);

        return view('dbaas.show', $responseData->toArray());
    }
}
