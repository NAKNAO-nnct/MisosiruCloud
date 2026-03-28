<?php

declare(strict_types=1);

namespace App\Lib\Proxmox;

use App\Lib\Proxmox\Resources\Cluster;
use App\Lib\Proxmox\Resources\Network;
use App\Lib\Proxmox\Resources\Node;
use App\Lib\Proxmox\Resources\Storage;
use App\Lib\Proxmox\Resources\Vm;

class ProxmoxApi
{
    public function __construct(private readonly Client $client)
    {
    }

    public function cluster(): Cluster
    {
        return new Cluster($this->client);
    }

    public function node(): Node
    {
        return new Node($this->client);
    }

    public function storage(): Storage
    {
        return new Storage($this->client);
    }

    public function network(): Network
    {
        return new Network($this->client);
    }

    public function vm(): Vm
    {
        return new Vm($this->client);
    }
}
