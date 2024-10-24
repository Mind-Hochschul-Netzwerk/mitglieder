<?php

namespace App\Repository;

abstract class Repository {
    private static $instance = null;
    public static function getInstance(): static
    {
        return static::$instance ??= new static();
    }
}
