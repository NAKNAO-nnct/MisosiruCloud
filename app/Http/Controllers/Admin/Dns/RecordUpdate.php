<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Dns;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dns\SaveDnsRecordDataRequest;
use App\Services\DnsService;
use Illuminate\Http\RedirectResponse;

class RecordUpdate extends Controller
{
    public function __construct(private readonly DnsService $dnsService)
    {
    }

    public function __invoke(SaveDnsRecordDataRequest $request, int $zoneId, int $recordId): RedirectResponse
    {
        $this->dnsService->updateRecord($zoneId, $recordId, $request->validated());

        return redirect()->route('dns-zones.records.index', $zoneId)->with('success', 'DNSレコードを更新しました。');
    }
}
