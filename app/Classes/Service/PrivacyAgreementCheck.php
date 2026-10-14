<?php
declare(strict_types=1);
namespace App\Service;

use App\Model\Agreement;
use App\Model\Group;
use App\Repository\AgreementRepository;
use Hengeb\Db\Db;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

/**
 * Prüft, ob Mitglieder eine (Mindest-)Version der Verpflichtung zum Datenschutz angenommen haben. Gilt als erfüllt,
 * wenn die letzte Aktion des Mitglieds zu dieser Vereinbarung eine Annahme einer Version >= der verlangten ist
 * (ein Widerruf oder keine Annahme erfüllt sie nicht).
 */
class PrivacyAgreementCheck
{
    public const string AGREEMENT = 'Datenschutzverpflichtung';

    public function __construct(
        private Db $db,
        private AgreementRepository $agreementRepository,
    ) {}

    /** alle Versionen der Verpflichtung, neueste zuerst */
    public function versions(): array
    {
        return $this->agreementRepository->findAllByName(self::AGREEMENT);
    }

    public function agreement(int $version): ?Agreement
    {
        return $this->agreementRepository->findOneByNameAndVersion(self::AGREEMENT, $version);
    }

    /** die für die Gruppe verlangte Version der Verpflichtung (null: keine verlangt) */
    public function requiredAgreement(Group $group): ?Agreement
    {
        return $group->requiredAgreementVersion === null ? null : $this->agreement($group->requiredAgreementVersion);
    }

    public function satisfies(string $username, int $version): bool
    {
        return $this->membersLacking([$username], $version) === [];
    }

    /**
     * Welche der Benutzer erfüllen die verlangte Version nicht?
     *
     * @param string[] $usernames
     * @return array<array{username: string, name: string}> nach Namen sortiert
     */
    public function membersLacking(array $usernames, int $version): array
    {
        $usernames = array_values(array_unique($usernames));
        if (!$usernames) {
            return [];
        }

        $params = ['name' => self::AGREEMENT, 'name2' => self::AGREEMENT, 'version' => $version];
        $placeholders = [];
        foreach ($usernames as $i => $username) {
            $params['u' . $i] = $username;
            $placeholders[] = ':u' . $i;
        }

        $rows = $this->db->query('SELECT m.username, m.vorname, m.nachname FROM mitglieder AS m
            WHERE m.username IN (' . implode(',', $placeholders) . ')
              AND m.id NOT IN (
                SELECT ua.user_id FROM user_agreements AS ua
                JOIN agreements AS a ON a.id = ua.agreement_id
                WHERE a.name = :name AND ua.action = \'accept\' AND a.version >= :version
                  AND ua.id = (
                    SELECT MAX(ua2.id) FROM user_agreements AS ua2
                    JOIN agreements AS a2 ON a2.id = ua2.agreement_id
                    WHERE a2.name = :name2 AND ua2.user_id = ua.user_id
                  )
              )
            ORDER BY m.nachname, m.vorname', $params)->getAll();

        return array_map(fn(array $row) => [
            'username' => $row['username'],
            'name' => trim($row['vorname'] . ' ' . $row['nachname']),
        ], $rows);
    }
}
