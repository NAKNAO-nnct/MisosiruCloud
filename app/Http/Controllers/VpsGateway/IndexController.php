<?php

declare(strict_types=1);

namespace App\Http\Controllers\VpsGateway;

use App\Http\Controllers\Controller;
use App\Repositories\VpsGatewayRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IndexController extends Controller
{
    public function __construct(private readonly VpsGatewayRepository $vpsGatewayRepository)
    {
    }

    public function __invoke(Request $request): View
    {
        $gateways = $this->vpsGatewayRepository->all();

        return view('admin.vps.index', compact('gateways'));
    }
}
