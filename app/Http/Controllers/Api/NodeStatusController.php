<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Lib\Proxmox\ProxmoxApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NodeStatusController extends Controller
{
    public function __construct(private readonly ?ProxmoxApi $api)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->api) {
            return response()->json([
                'message' => 'No active Proxmox node configured.',
            ], 503);
        }

        $nodes = $this->api->node()->listNodes();
        $statuses = [];

        foreach ($nodes as $node) {
            $statuses[] = $this->api->node()->getNodeStatus($node['node']);
        }

        return response()->json($statuses);
    }
}
