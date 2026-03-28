<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProxmoxNode;

use App\Http\Controllers\Controller;
use App\Repositories\ProxmoxNodeRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class TestSnippetApiController extends Controller
{
    public function __construct(private readonly ProxmoxNodeRepository $proxmoxNodeRepository)
    {
    }

    public function __invoke(Request $request, int $proxmoxNode): RedirectResponse
    {
        $node = $this->proxmoxNodeRepository->findByIdOrFail($proxmoxNode);
        $baseUrl = $node->getSnippetApiUrl();

        if (!$baseUrl) {
            return back()->with('snippet_api_test_error', 'スニペット API URL が設定されていません。');
        }

        try {
            $response = Http::timeout(5)->get($baseUrl . '/health');

            if ($response->successful()) {
                return back()->with('snippet_api_test_success', sprintf(
                    'スニペット API に正常に接続しました。URL: %s',
                    $baseUrl,
                ));
            }

            return back()->with('snippet_api_test_error', sprintf(
                'スニペット API からエラーレスポンスが返されました（%d）。',
                $response->status(),
            ));
        } catch (Throwable $e) {
            return back()->with('snippet_api_test_error', sprintf(
                'スニペット API への接続に失敗しました: %s',
                $e->getMessage(),
            ));
        }
    }
}
