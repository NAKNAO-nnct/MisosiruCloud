<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dbaas;

use App\Http\Controllers\Controller;
use App\Repositories\DatabaseInstanceRepository;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BackupController extends Controller
{
    public function __construct(
        private readonly BackupService $backupService,
        private readonly DatabaseInstanceRepository $databaseInstanceRepository,
    ) {
    }

    public function __invoke(Request $request, int $database): RedirectResponse
    {
        $db = $this->databaseInstanceRepository->findByIdOrFail($database);
        $this->backupService->executeBackup($db);

        return redirect()->route('dbaas.show', $database)->with('success', 'バックアップを実行しました。');
    }
}
