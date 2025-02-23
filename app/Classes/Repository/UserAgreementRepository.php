<?php
namespace App\Repository;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

use App\Model\UserAgreement;

class UserAgreementRepository extends Repository
{
    public function findAllByUserId(): array
    {
        $rows = Db::getInstance()->query('SELECT id, timestamp FROM user_agreements ORDER BY id DESC')->getAll();
        return array_map(fn($row) => Agreement::fromDatabase(...$row), $rows);
    }

    public function findOneById(int $id): ?Agreement
    {
        $result = Db::getInstance()->query('SELECT id, text, timestamp FROM agreements WHERE id = :id', ['id' => $id]);
        return ($result->getRowCount() === 0) ? null : Agreement::fromDatabase(...$result->getRow());
    }

    public function store(Agreement $agreement): void
    {
        if ($agreement->id) {
            throw new \InvalidArgumentException('Agreement texts may never be changed, create a new id');
        }
        $agreement->id = Db::getInstance()->query('INSERT INTO agreements SET text=:text, timestamp=:timestamp', [
            'text' => $agreement->text,
            'timestamp' => $agreement->timestamp,
        ])->getInsertId();
    }

}
