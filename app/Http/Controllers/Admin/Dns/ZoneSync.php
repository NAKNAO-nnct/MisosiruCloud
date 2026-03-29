<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Dns;

use App\Http\Controllers\Controller;
use App\Services\DnsService;
use Illuminate\Http\RedirectResponse;

class ZoneSync extends Controller
{
    public function __construct(private readonly DnsService $dnsService)
    {
    }

    public function __invoke(int $zoneId): RedirectResponse
    {
        $synced = $this->dnsService->syncFromProvider($zoneId);

        return redirect()->route('dns-zones.records.index', $zoneId)
            ->with('success', $synced->count() . ' 件のDNSレコードを同期しました。');
    }
}
