<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dbaas;

use App\Http\Controllers\Controller;
use App\Repositories\DatabaseInstanceRepository;
use App\Services\DbaasService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StartController extends Controller
{
    public function __construct(
        private readonly DbaasService $dbaasService,
        private readonly DatabaseInstanceRepository $databaseInstanceRepository,
    ) {
    }

    public function __invoke(Request $request, int $database): RedirectResponse
    {
        $db = $this->databaseInstanceRepository->findByIdOrFail($database);
        $this->dbaasService->start($db);

        return redirect()->route('dbaas.show', $database)->with('success', 'DBを起動しました。');
    }
}
