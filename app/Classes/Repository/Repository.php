<?php

namespace App\Repository;

abstract class Repository {
    private static $instances = null;
    public static function getInstance(): static
    {
        return static::$instances[static::class] ??= new static();
    }
}
