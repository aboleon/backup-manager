<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Dumpers;

use Spatie\DbDumper\Databases\MySql;

class MySqlDumperFactory
{
    public function create(): MySql
    {
        return MySql::create();
    }
}
