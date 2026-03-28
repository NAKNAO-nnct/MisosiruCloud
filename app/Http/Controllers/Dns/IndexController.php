<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dns;

use App\Http\Controllers\Controller;
use App\Services\DnsManagementService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IndexController extends Controller
{
    public function __construct(private readonly DnsManagementService $dnsManagementService)
    {
    }

    public function __invoke(Request $request): View
    {
        $records = $this->dnsManagementService->listRecords();

        return view('admin.dns.index', compact('records'));
    }
}
