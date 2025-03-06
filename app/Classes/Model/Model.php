<?php

namespace App\Model;

use App\Repository\Repository;

abstract class Model {
    protected static string $repositoryClass = Repository::class;

    public static function getRepository(): Repository
    {
        return static::$repositoryClass::getInstance();
    }
}
