<?php

namespace App\Model;

use App\Repository\Repository;
use Hengeb\Router\Interface\RetrievableModel;

abstract class Model implements RetrievableModel {
    protected static string $repositoryClass = Repository::class;

    public static function getRepository(): Repository
    {
        return static::$repositoryClass::getInstance();
    }

    public static function retrieveModel(mixed $id, string $identifierName = 'id'): ?static
    {
        $repository = static::getRepository();
        return $repository->{'findOneBy' . $identifierName}($id);
    }
}
