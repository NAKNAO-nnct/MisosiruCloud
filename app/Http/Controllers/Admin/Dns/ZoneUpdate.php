<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Dns;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dns\SaveDnsZoneRequest;
use App\Repositories\DnsZoneRepository;
use Illuminate\Http\RedirectResponse;

class ZoneUpdate extends Controller
{
    public function __construct(private readonly DnsZoneRepository $dnsZoneRepository)
    {
    }

    public function __invoke(SaveDnsZoneRequest $request, int $zoneId): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        $this->dnsZoneRepository->update($zoneId, $data);

        return redirect()->route('dns-zones.index')->with('success', 'DNSゾーンを更新しました。');
    }
}
