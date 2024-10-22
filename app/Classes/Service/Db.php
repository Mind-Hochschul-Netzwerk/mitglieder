<?php
declare(strict_types=1);
namespace App\Service;

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
            'host' => getenv('MYSQL_HOST'),
            'port' => 3306,
            'user' => getenv('MYSQL_USER'),
            'password' => getenv('MYSQL_PASSWORD'),
            'database' => getenv('MYSQL_DATABASE'),
        ]);
    }
}
