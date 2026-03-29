<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Dns;

use App\Http\Controllers\Controller;
use App\Models\DnsZone;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ZoneIndex extends Controller
{
    public function __invoke(Request $request): View
    {
        $zones = DnsZone::query()
            ->withCount('records')
            ->orderBy('name')
            ->get();

        return view('admin.dns.zone-index', compact('zones'));
    }
}
