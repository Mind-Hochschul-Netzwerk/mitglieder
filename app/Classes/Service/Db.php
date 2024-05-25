<?php
declare(strict_types=1);
namespace App\Service;

use App\Config;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

class Db extends \Hengeb\Db\Db implements \App\Interfaces\Singleton
{
    use \App\Traits\Singleton;

    private function __construct(...$args)
    {
        parent::__construct([
            'host' => Config::$mysqlHost,
            'port' => 3306,
            'user' => Config::$mysqlUser,
            'password' => Config::$mysqlPassword,
            'database' => Config::$mysqlDatabase,
        ]);
    }
}
