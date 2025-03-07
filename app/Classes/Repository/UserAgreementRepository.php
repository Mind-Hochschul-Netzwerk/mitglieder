<?php
/**
 * UserAgreementRepository handles database operations related to user agreements.
 *
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */
declare(strict_types=1);

namespace App\Repository;

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

    /**
     * Retrieves all user agreements for a given user.
     *
     * @param User $user The user whose agreements should be retrieved.
     * @return UserAgreement[] List of user agreements.
     */
    public function findAllByUser(User $user): array
    {
        $rows = $this->db->query('SELECT ua.id, ua.user_id, ua.agreement_id, ua.timestamp, ua.action, ua.admin_info
          FROM user_agreements AS ua
          JOIN agreements as a ON ua.agreement_id = a.id
          WHERE ua.user_id=:user_id ORDER BY ua.id DESC', [
            'user_id' => $user->get('id'),
          ])->getAll();
        return array_map(fn($row) => UserAgreement::fromDatabase(...$row), $rows);
    }

    /**
     * Retrieves the latest agreement state for each agreement name for a given user.
     *
     * @param User $user The user whose latest agreement states should be retrieved.
     * @return array<string, UserAgreement> An associative array where keys are agreement names and values are the latest user agreements.
     */
    public function findLatestByUserPerName(User $user): array
    {
        $rows = $this->db->query('SELECT name, ua.id, user_id, agreement_id, ua.timestamp, action, admin_info FROM user_agreements AS ua
          JOIN agreements AS a ON ua.agreement_id = a.id
          WHERE ua.id IN (
            SELECT MAX(ua.id) FROM user_agreements AS ua
            JOIN agreements as a ON ua.agreement_id = a.id
            WHERE ua.user_id=:user_id
            GROUP BY a.name ORDER BY ua.id DESC
          ) ORDER BY name', [
            'user_id' => $user->get('id'),
          ])->getAll();

        // the names will be the keys of the returned array
        $names = array_column($rows, 'name');

        // remove the key 'name' so we can use UserAgreement::fromDatabase(...$row) with the remaining keys
        array_walk($rows, function(&$row) {
            unset($row['name']);
        });

        return array_combine(
            keys: $names,
            values: array_map(fn($row) => UserAgreement::fromDatabase(...$row), $rows)
        );
    }

    /**
     * Retrieves the latest user agreement for a specific agreement name.
     *
     * @param User $user The user whose agreement should be retrieved.
     * @param string $name The name of the agreement.
     * @return UserAgreement|null The latest user agreement or null if none exists.
     */
    public function findLatestByUserAndName(User $user, string $name): ?UserAgreement
    {
        $row = $this->db->query('SELECT ua.id, user_id, agreement_id, ua.timestamp, action, admin_info FROM user_agreements AS ua
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

    /**
     * Persists a new user agreement in the database.
     *
     * @param UserAgreement $item The user agreement to persist.
     * @throws \InvalidArgumentException If attempting to update an existing user agreement.
     */
    public function persist(UserAgreement $item): void
    {
        if ($item->id) {
            throw new \InvalidArgumentException('UserAgreements may never be changed, create a new one');
        }
        $item->id = intval($this->db->query('INSERT INTO user_agreements SET
          user_id=:user_id, agreement_id=:agreement_id, timestamp=:timestamp, action=:action, admin_info=:admin_info', [
            'user_id' => $item->user->get('id'),
            'agreement_id' => $item->agreement->id,
            'timestamp' => $item->timestamp,
            'action' => $item->action,
            'admin_info' => $item->admin?->json(),
        ])->getInsertId());
    }
}
