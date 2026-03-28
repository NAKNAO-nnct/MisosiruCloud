<?php

declare(strict_types=1);

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Services\NetworkService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IndexController extends Controller
{
    public function __construct(private readonly NetworkService $networkService)
    {
    }

    public function __invoke(Request $request): View
    {
        $networks = $this->networkService->listNetworks();

        return view('networks.index', compact('networks'));
    }
}
