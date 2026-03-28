<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dbaas;

use App\Http\Controllers\Controller;
use App\Repositories\DatabaseInstanceRepository;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RestoreController extends Controller
{
    public function __construct(
        private readonly BackupService $backupService,
        private readonly DatabaseInstanceRepository $databaseInstanceRepository,
    ) {
    }

    public function __invoke(Request $request, int $database): RedirectResponse
    {
        $validated = $request->validate([
            's3_key' => ['required', 'string', 'max:255'],
        ]);

        $db = $this->databaseInstanceRepository->findByIdOrFail($database);
        $this->backupService->restore($db, $validated['s3_key']);

        return redirect()->route('dbaas.show', $database)->with('success', 'リストアを開始しました。');
    }
}
