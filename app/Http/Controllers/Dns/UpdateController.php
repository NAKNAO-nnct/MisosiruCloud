<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dns;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dns\SaveDnsRecordRequest;
use App\Services\DnsManagementService;
use Illuminate\Http\RedirectResponse;

class UpdateController extends Controller
{
    public function __construct(private readonly DnsManagementService $dnsManagementService)
    {
    }

    public function __invoke(SaveDnsRecordRequest $request, string $recordId): RedirectResponse
    {
        $this->dnsManagementService->updateRecord($recordId, $request->validated());

        return redirect()->route('dns.index')->with('success', 'DNS レコードを更新しました。');
    }
}
