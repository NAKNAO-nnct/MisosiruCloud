<?php

declare(strict_types=1);

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Services\NetworkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DestroyController extends Controller
{
    public function __construct(private readonly NetworkService $networkService)
    {
    }

    public function __invoke(Request $request, int $tenant): RedirectResponse
    {
        $this->networkService->deleteNetwork($tenant);

        return redirect()->route('networks.index')
            ->with('success', 'ネットワークを削除しました。');
    }
}
