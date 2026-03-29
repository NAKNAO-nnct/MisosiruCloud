<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Dns;

use App\Http\Controllers\Controller;
use App\Repositories\DnsZoneRepository;
use Illuminate\Http\RedirectResponse;

class ZoneDestroy extends Controller
{
    public function __construct(private readonly DnsZoneRepository $dnsZoneRepository)
    {
    }

    public function __invoke(int $zoneId): RedirectResponse
    {
        $this->dnsZoneRepository->delete($zoneId);

        return redirect()->route('dns-zones.index')->with('success', 'DNSゾーンを削除しました。');
    }
}
