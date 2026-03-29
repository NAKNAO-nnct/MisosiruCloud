<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Dns;

use App\Http\Controllers\Controller;
use App\Repositories\DnsRecordRepository;
use App\Repositories\DnsZoneRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecordIndex extends Controller
{
    public function __construct(
        private readonly DnsZoneRepository $dnsZoneRepository,
        private readonly DnsRecordRepository $dnsRecordRepository,
    ) {
    }

    public function __invoke(Request $request, int $zoneId): View
    {
        $zone = $this->dnsZoneRepository->findByIdOrFail($zoneId);
        $records = $this->dnsRecordRepository->findByZoneId($zoneId);

        return view('admin.dns.record-index', compact('zone', 'records'));
    }
}
