<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dbaas;

use App\Http\Controllers\Controller;
use App\Repositories\DatabaseInstanceRepository;
use App\Services\DbaasService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DestroyController extends Controller
{
    public function __construct(
        private readonly DbaasService $dbaasService,
        private readonly DatabaseInstanceRepository $databaseInstanceRepository,
    ) {
    }

    public function __invoke(Request $request, int $database): RedirectResponse
    {
        $db = $this->databaseInstanceRepository->findByIdOrFail($database);
        $this->dbaasService->terminate($db);

        return redirect()->route('dbaas.index')->with('success', 'DBインスタンスを削除しました。');
    }
}
