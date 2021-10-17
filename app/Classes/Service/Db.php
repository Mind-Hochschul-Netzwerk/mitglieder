<?php
declare(strict_types=1);
namespace MHN\Mitglieder\Service;

use MHN\Mitglieder\Config;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

class Db extends \Hengeb\Db\Db implements \MHN\Mitglieder\Interfaces\Singleton
{
    use \MHN\Mitglieder\Traits\Singleton;

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
