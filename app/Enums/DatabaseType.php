<?php

declare(strict_types=1);

namespace App\Enums;

enum DatabaseType: string
{
    case Mysql = 'mysql';
    case Postgres = 'postgres';
    case Redis = 'redis';

    public function versions(): array
    {
        return match ($this) {
            self::Mysql => ['8.4', '8.3', '8.2', '8.1', '8.0'],
            self::Postgres => ['17', '16', '15', '14', '13'],
            self::Redis => ['7.2', '7.0', '6.2'],
        };
    }
}
