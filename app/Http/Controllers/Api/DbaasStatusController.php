<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\DatabaseInstanceRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DbaasStatusController extends Controller
{
    public function __construct(private readonly DatabaseInstanceRepository $databaseInstanceRepository)
    {
    }

    public function __invoke(Request $request, int $database): JsonResponse
    {
        $db = $this->databaseInstanceRepository->findByIdOrFail($database);

        return response()->json([
            'id' => $db->getId(),
            'status' => $db->getStatus(),
        ]);
    }
}
