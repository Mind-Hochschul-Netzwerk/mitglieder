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
    private Db $db;

    public function __construct()
    {
        $this->db = Db::getInstance();
    }

    public function findAll(): array
    {
        $rows = $this->db->query('SELECT id, name, version, text, timestamp FROM agreements ORDER BY name ASC, version DESC')->getAll();
        return array_map(fn($row) => Agreement::fromDatabase(...$row), $rows);
    }

    public function findAllNames(): array
    {
        return $this->db->query('SELECT DISTINCT name FROM agreements ORDER BY name')->getColumn();
    }

    public function findAllByName(string $name): array
    {
        $rows = $this->db->query('SELECT id, name, version, text, timestamp FROM agreements
            WHERE name=:name ORDER BY version DESC', [
                'name' => $name
            ])->getAll();
        return array_map(fn($row) => Agreement::fromDatabase(...$row), $rows);
    }

    public function findLatestByName(string $name): ?Agreement
    {
        $row = $this->db->query('SELECT id, name, version, text, timestamp FROM agreements
          WHERE name=:name ORDER BY version DESC LIMIT 1', [
            'name' => $name
          ])->getRow();
        return $row ? Agreement::fromDatabase(...$row) : null;
    }

    public function findLatestPerName(): array
    {
        $rows = $this->db->query('SELECT id, name, version, text, timestamp FROM agreements WHERE id IN
          (SELECT MAX(id) FROM agreements GROUP BY name) ORDER BY name')->getAll();
        return array_combine(
            keys: array_column($rows, 'name'),
            values: array_map(fn($row) => Agreement::fromDatabase(...$row), $rows)
        );
    }

    public function findOneById(int $id): ?Agreement
    {
        $result = $this->db->query('SELECT id, name, version, text, timestamp FROM agreements WHERE id = :id', ['id' => $id]);
        return ($result->getRowCount() === 0) ? null : Agreement::fromDatabase(...$result->getRow());
    }

    public function persist(Agreement $agreement): void
    {
        if ($agreement->id) {
            throw new \InvalidArgumentException('Agreement texts may never be changed, create a new version');
        }
        $agreement->id = $this->db->query('INSERT INTO agreements SET
            name=:name, version=:version, text=:text, timestamp=:timestamp', [
            'name' => $agreement->name,
            'version' => $agreement->version,
            'text' => $agreement->text,
            'timestamp' => $agreement->timestamp,
        ])->getInsertId();
    }
}
