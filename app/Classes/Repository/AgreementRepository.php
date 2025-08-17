<?php
/**
 * AgreementRepository handles database interactions related to agreements.
 *
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */
declare(strict_types=1);

namespace App\Repository;

use App\Model\Agreement;
use DateTimeImmutable;
use Hengeb\Db\Db;

class AgreementRepository
{
    public function __construct(
        private Db $db,
    )
    {
    }

    private function createModel($row): Agreement
    {
        return new Agreement(
            id: $row['id'],
            name: $row['name'],
            version: $row['version'],
            text: $row['text'],
            timestamp: new DateTimeImmutable($row['timestamp']),
        );
    }

    /**
     * Retrieves all agreements sorted by name and version (newest first).
     *
     * @return Agreement[] List of all agreements.
     */
    public function findAll(): array
    {
        $rows = $this->db->query('SELECT id, name, version, text, timestamp FROM agreements ORDER BY name ASC, version DESC')->getAll();
        return array_map(fn($row) => $this->createModel($row), $rows);
    }

    /**
     * Retrieves all unique agreement names.
     *
     * @return string[] List of unique agreement names.
     */
    public function findAllNames(): array
    {
        return $this->db->query('SELECT DISTINCT name FROM agreements ORDER BY name')->getColumn();
    }

    /**
     * Retrieves all versions of an agreement by name, sorted by version descending.
     *
     * @param string $name The agreement name.
     * @return Agreement[] List of agreements with the given name.
     */
    public function findAllByName(string $name): array
    {
        $rows = $this->db->query('SELECT id, name, version, text, timestamp FROM agreements
            WHERE name=:name ORDER BY version DESC', [
                'name' => $name
            ])->getAll();
        return array_map(fn($row) => $this->createModel($row), $rows);
    }

    /**
     * Retrieves the latest version of an agreement by name.
     *
     * @param string $name The agreement name.
     * @return Agreement|null The latest agreement version or null if not found.
     */
    public function findLatestByName(string $name): ?Agreement
    {
        $row = $this->db->query('SELECT id, name, version, text, timestamp FROM agreements
          WHERE name=:name ORDER BY version DESC LIMIT 1', [
            'name' => $name
          ])->getRow();
        return $row ? $this->createModel($row) : null;
    }

    /**
     * Retrieves the latest version of each agreement.
     *
     * @return array<string, Agreement> Associative array where keys are agreement names and values are the latest agreement versions.
     */
    public function findLatestPerName(): array
    {
        $rows = $this->db->query('SELECT id, name, version, text, timestamp FROM agreements WHERE id IN
          (SELECT MAX(id) FROM agreements GROUP BY name) ORDER BY name')->getAll();
        return array_combine(
            keys: array_column($rows, 'name'),
            values: array_map(fn($row) => $this->createModel($row), $rows)
        );
    }

    /**
     * Finds an agreement by its ID.
     *
     * @param int $id The agreement ID.
     * @return Agreement|null The agreement or null if not found.
     */
    public function findOneById(int $id): ?Agreement
    {
        $result = $this->db->query('SELECT id, name, version, text, timestamp FROM agreements WHERE id = :id', ['id' => $id]);
        return ($result->getRowCount() === 0) ? null : $this->createModel($result->getRow());
    }

    /**
     * Persists a new agreement in the database.
     *
     * @param Agreement $agreement The agreement to persist.
     * @throws \InvalidArgumentException If an attempt is made to update an existing agreement.
     */
    public function persist(Agreement $agreement): void
    {
        if ($agreement->id) {
            throw new \InvalidArgumentException('Agreement texts may never be changed, create a new version');
        }
        $agreement->id = intval($this->db->query('INSERT INTO agreements SET
          name=:name, version=:version, text=:text, timestamp=:timestamp', [
            'name' => $agreement->name,
            'version' => $agreement->version,
            'text' => $agreement->text,
            'timestamp' => $agreement->timestamp,
        ])->getInsertId());
    }
}
