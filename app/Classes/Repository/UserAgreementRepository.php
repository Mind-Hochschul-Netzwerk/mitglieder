<?php
namespace App\Repository;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

use App\Model\User;
use App\Model\UserAgreement;
use Hengeb\Db\Db;

class UserAgreementRepository extends Repository
{
    private Db $db;

    public function __construct()
    {
        $this->db = Db::getInstance();
    }

    public function findAllByUser(User $user): array
    {
        $rows = $this->db->query('SELECT ua.id, ua.user_id, ua.agreement_id, ua.timestamp, ua.action, ua.admin_id
          FROM user_agreements AS ua
          JOIN agreements as a ON ua.agreement_id = a.id
          WHERE ua.user_id=:user_id ORDER BY ua.id DESC', [
            'user_id' => $user->get('id'),
          ])->getAll();
        return array_map(fn($row) => UserAgreement::fromDatabase(...$row), $rows);
    }

    public function findLatestByUserPerName(User $user): array
    {
        $rows = $this->db->query('SELECT name, ua.id, user_id, agreement_id, ua.timestamp, action, admin_id FROM user_agreements AS ua
          JOIN agreements AS a ON ua.agreement_id = a.id
          WHERE ua.id IN (
            SELECT MAX(ua.id) FROM user_agreements AS ua
            JOIN agreements as a ON ua.agreement_id = a.id
            WHERE ua.user_id=:user_id
            GROUP BY a.name ORDER BY ua.id DESC
          ) ORDER BY name', [
            'user_id' => $user->get('id'),
          ])->getAll();
        $names = array_column($rows, 'name');
        array_walk($rows, function(&$row) {
            unset($row['name']);
        });
        return array_combine(
            keys: $names,
            values: array_map(fn($row) => UserAgreement::fromDatabase(...$row), $rows)
        );
    }

    public function findLatestByUserAndName(User $user, string $name): ?UserAgreement
    {
        $row = $this->db->query('SELECT ua.id, user_id, agreement_id, ua.timestamp, action, admin_id FROM user_agreements AS ua
          JOIN agreements AS a ON ua.agreement_id = a.id
          WHERE a.name = :name AND ua.user_id = :user_id
          ORDER BY ua.id DESC LIMIT 1', [
            'name' => $name,
            'user_id' => $user->get('id'),
          ])->getRow();
        if (!$row) {
          return null;
        }
        return UserAgreement::fromDatabase(...$row);
    }

    public function persist(UserAgreement $item): void
    {
        if ($item->id) {
            throw new \InvalidArgumentException('UserAgreements may never be changed, create a new one');
        }
        $item->id = $this->db->query('INSERT INTO user_agreements SET
          user_id=:user_id, agreement_id=:agreement_id, timestamp=:timestamp, action=:action, admin_id=:admin_id', [
            'user_id' => $item->user->get('id'),
            'agreement_id' => $item->agreement->id,
            'timestamp' => $item->timestamp,
            'action' => $item->action,
            'admin_id' => $item->admin?->get('id'),
        ])->getInsertId();
    }
}
