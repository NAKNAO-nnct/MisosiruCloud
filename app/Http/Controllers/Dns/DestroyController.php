<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dns;

use App\Http\Controllers\Controller;
use App\Services\DnsManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DestroyController extends Controller
{
    public function __construct(private readonly DnsManagementService $dnsManagementService)
    {
    }

    public function __invoke(Request $request, string $recordId): RedirectResponse
    {
        $this->dnsManagementService->deleteRecord($recordId);

        return redirect()->route('dns.index')->with('success', 'DNS レコードを削除しました。');
    }
}
