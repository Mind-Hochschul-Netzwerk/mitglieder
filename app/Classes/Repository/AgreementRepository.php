<?php
namespace App\Repository;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

use App\Model\Agreement;
use Hengeb\Db\Db;

class AgreementRepository extends Repository
{
    public function findAll(): array
    {
        $rows = Db::getInstance()->query('SELECT id, name, version, text, timestamp FROM agreements ORDER BY name ASC, version DESC')->getAll();
        return array_map(fn($row) => Agreement::fromDatabase(...$row), $rows);
    }

    public function findAllByName(string $name): array
    {
        $rows = Db::getInstance()->query('SELECT id, name, version, text, timestamp FROM agreements
            WHERE name=:name ORDER BY version DESC', [
                'name' => $name
            ])->getAll();
        return array_map(fn($row) => Agreement::fromDatabase(...$row), $rows);
    }

    public function findOneById(int $id): ?Agreement
    {
        $result = Db::getInstance()->query('SELECT id, name, version, text, timestamp FROM agreements WHERE id = :id', ['id' => $id]);
        return ($result->getRowCount() === 0) ? null : Agreement::fromDatabase(...$result->getRow());
    }

    public function persist(Agreement $agreement): void
    {
        if ($agreement->id) {
            throw new \InvalidArgumentException('Agreement texts may never be changed, create a new version');
        }
        $agreement->id = Db::getInstance()->query('INSERT INTO agreements SET
            name=:name, version=:version, text=:text, timestamp=:timestamp', [
            'name' => $agreement->name,
            'version' => $agreement->version,
            'text' => $agreement->text,
            'timestamp' => $agreement->timestamp,
        ])->getInsertId();
    }
}
