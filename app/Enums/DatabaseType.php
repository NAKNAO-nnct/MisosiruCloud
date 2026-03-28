<?php

declare(strict_types=1);

namespace App\Enums;

enum DatabaseType: string
{
    case Mysql = 'mysql';
    case Postgres = 'postgres';
    case Redis = 'redis';
}
