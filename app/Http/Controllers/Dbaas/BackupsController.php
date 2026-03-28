<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dbaas;

use App\Http\Controllers\Controller;
use App\Repositories\DatabaseInstanceRepository;
use App\Services\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BackupsController extends Controller
{
    public function __construct(
        private readonly BackupService $backupService,
        private readonly DatabaseInstanceRepository $databaseInstanceRepository,
    ) {
    }

    public function __invoke(Request $request, int $database): JsonResponse
    {
        $db = $this->databaseInstanceRepository->findByIdOrFail($database);

        return response()->json(['backups' => $this->backupService->listBackups($db)]);
    }
}
