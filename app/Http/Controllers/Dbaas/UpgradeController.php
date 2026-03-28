<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dbaas;

use App\Http\Controllers\Controller;
use App\Repositories\DatabaseInstanceRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UpgradeController extends Controller
{
    public function __construct(private readonly DatabaseInstanceRepository $databaseInstanceRepository)
    {
    }

    public function __invoke(Request $request, int $database): RedirectResponse
    {
        $validated = $request->validate([
            'db_version' => ['required', 'string', 'max:20'],
        ]);

        $this->databaseInstanceRepository->update($database, [
            'db_version' => $validated['db_version'],
            'status' => 'upgrading',
        ]);

        return redirect()->route('dbaas.show', $database)->with('success', 'バージョンアップグレードを開始しました。');
    }
}
