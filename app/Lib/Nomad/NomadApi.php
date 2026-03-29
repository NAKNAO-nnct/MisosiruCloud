<?php

declare(strict_types=1);

namespace App\Lib\Nomad;

use App\Lib\Nomad\Resources\Allocation;
use App\Lib\Nomad\Resources\Job;
use App\Lib\Nomad\Resources\Namespace_;
use App\Lib\Nomad\Resources\Node;
use App\Lib\Nomad\Resources\Quota;

class NomadApi
{
    public function __construct(private readonly Client $client)
    {
    }

    public function job(): Job
    {
        return new Job($this->client);
    }

    public function namespace(): Namespace_
    {
        return new Namespace_($this->client);
    }

    public function allocation(): Allocation
    {
        return new Allocation($this->client);
    }

    public function node(): Node
    {
        return new Node($this->client);
    }

    public function quota(): Quota
    {
        return new Quota($this->client);
    }
}
