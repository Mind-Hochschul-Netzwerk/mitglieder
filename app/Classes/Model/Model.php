<?php

namespace App\Model;

use App\Repository\Repository;

abstract class Model {
    protected static $repositoryClass = Repository::class;

    public static function getRepository(): Repository
    {
        return self::$repositoryClass::getInstance();
    }
}
