<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Dns;

use App\Http\Controllers\Controller;
use App\Services\DnsService;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class Reload extends Controller
{
    public function __construct(private readonly DnsService $dnsService)
    {
    }

    public function __invoke(): RedirectResponse
    {
        try {
            $this->dnsService->regenerateLocalZones();
        } catch (RuntimeException $exception) {
            return redirect()->route('dns-zones.index')->with('error', 'CoreDNSのリロードに失敗しました: ' . $exception->getMessage());
        }

        return redirect()->route('dns-zones.index')->with('success', 'ローカルDNSゾーンを再生成してCoreDNSをリロードしました。');
    }
}
