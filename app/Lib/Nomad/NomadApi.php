<?php

declare(strict_types=1);

namespace App\Lib\Nomad;

use App\Lib\Nomad\Resources\Job;
use App\Lib\Nomad\Resources\Namespace_;

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
}
