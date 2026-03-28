<?php

declare(strict_types=1);

namespace App\Lib\Proxmox;

use App\Data\ProxmoxNode\ProxmoxNodeData;

class ProxmoxApiFactory
{
    public function forCluster(ProxmoxNodeData $cluster): ProxmoxApi
    {
        $client = new Client(
            hostname: $cluster->getHostname(),
            tokenId: $cluster->getApiTokenId(),
            tokenSecret: $cluster->getApiTokenSecret(),
            verifyTls: !app()->isLocal(),
        );

        return new ProxmoxApi($client);
    }
}
