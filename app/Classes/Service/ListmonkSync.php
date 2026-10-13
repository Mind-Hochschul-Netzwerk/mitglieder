<?php
declare(strict_types=1);
namespace App\Service;

use App\Model\MailAddress;
use App\Repository\GroupRepository;
use App\Repository\UserRepository;

/**
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

/**
 * Gleicht LDAP und ListMonk ab (Cronjob bzw. Admin-Bereich):
 *
 * 1. Hard Bounces aus ListMonk: die betroffene Adresse des Mitglieds wird im LDAP als ungültig markiert
 * 2. Mitglieder-Liste (Gruppe „mitglieder“): LDAP hat immer recht
 * 3. Newsletter (Gruppe „newsletter“): zusätzlich werden in ListMonk abgemeldete Mitglieder aus der Gruppe entfernt
 */
class ListmonkSync
{
    public const string GROUP_ALL = 'mitglieder';

    public function __construct(
        private Listmonk $listmonk,
        private Ldap $ldap,
        private GroupRepository $groupRepository,
        private UserRepository $userRepository,
    ) {}

    /**
     * Mitgliedsdaten (Ldap::getAll()) für den Listmonk-Service: verwendet wird die erste gültige Adresse,
     * also die MHN-Adresse (falls vorhanden), sonst die private.
     *
     * @return array{id: int, firstname: string, lastname: string, email: string}
     */
    public static function memberFor(array $ldapMember): array
    {
        $email = '';
        foreach ([$ldapMember['orgEmail'] ?? null, $ldapMember['email'] ?? null] as $candidate) {
            if ($candidate !== null && $candidate !== '' && !MailAddress::isInvalid($candidate)) {
                $email = $candidate;
                break;
            }
        }
        return [
            'id' => (int) $ldapMember['id'],
            'firstname' => (string) $ldapMember['givenName'],
            'lastname' => (string) $ldapMember['familyName'],
            'email' => $email,
        ];
    }

    /**
     * @return array{bounces: array, members: array, newsletter: ?array}
     * @throws \RuntimeException wenn ListMonk nicht konfiguriert ist oder eine Gruppe fehlt
     */
    public function run(): array
    {
        $result = ['bounces' => $this->markBouncedAddresses()];

        // erst nach dem Markieren laden, damit frisch markierte Adressen schon übersprungen werden
        $ldapMembers = $this->ldap->getAll();
        $byUsername = [];
        foreach ($ldapMembers as $member) {
            $byUsername[$member['username']] = $member;
        }

        $result['members'] = $this->listmonk->syncMembers(
            $this->membersOfGroup(self::GROUP_ALL, $byUsername)
        );

        $result['newsletter'] = null;
        if ($this->listmonk->isNewsletterConfigured()) {
            $newsletter = $this->listmonk->syncNewsletter(
                $this->membersOfGroup(NewsletterGroupListener::GROUP, $byUsername)
            );
            $newsletter['removedFromGroup'] = $this->removeFromNewsletterGroup($newsletter['unsubscribed'], $ldapMembers, $newsletter['errors']);
            $result['newsletter'] = $newsletter;
        }

        return $result;
    }

    /**
     * @param array<string, array> $byUsername Ldap::getAll() nach Benutzername
     * @return array[] Mitglieder für den Listmonk-Service; Benutzer, die es nicht (mehr) gibt, werden übergangen
     */
    private function membersOfGroup(string $groupName, array $byUsername): array
    {
        $group = $this->groupRepository->findOneByName($groupName);
        if ($group === null) {
            throw new \RuntimeException('Die LDAP-Gruppe „' . $groupName . '“ existiert nicht.', 1750000009);
        }
        $members = [];
        foreach ($group->memberUsernames as $username) {
            if (isset($byUsername[$username])) {
                $members[] = self::memberFor($byUsername[$username]);
            }
        }
        return $members;
    }

    /**
     * Entfernt in ListMonk abgemeldete Mitglieder aus der Newsletter-Gruppe. Das Repository wird ohne Listener
     * gespeichert, die Abmeldung muss nicht erneut nach ListMonk übertragen werden.
     *
     * @param int[] $memberNumbers
     * @param string[] $errors
     */
    private function removeFromNewsletterGroup(array $memberNumbers, array $ldapMembers, array &$errors): int
    {
        if (!$memberNumbers) {
            return 0;
        }
        $usernameByNumber = [];
        foreach ($ldapMembers as $member) {
            $usernameByNumber[(int) $member['id']] = $member['username'];
        }

        $group = $this->groupRepository->findOneByName(NewsletterGroupListener::GROUP);
        $count = 0;
        foreach ($memberNumbers as $number) {
            if (isset($usernameByNumber[$number]) && $group->isMember($usernameByNumber[$number])) {
                $group->removeMember($usernameByNumber[$number]);
                $count++;
            }
        }
        if ($count) {
            try {
                $this->groupRepository->save($group, notifyListeners: false);
            } catch (\Throwable $e) {
                $errors[] = 'Entfernen aus der Gruppe fehlgeschlagen: ' . $e->getMessage();
                return 0;
            }
        }
        return $count;
    }

    /**
     * Markiert Adressen von Mitgliedern, die in ListMonk einen Hard Bounce verursacht haben, im LDAP als ungültig
     * (z.B. name@example.com.LISTMONK_BOUNCE.2026-10-11.invalid). Wiederholbar: danach passt die Adresse nicht mehr.
     *
     * @return array{marked: int, errors: string[]}
     */
    private function markBouncedAddresses(): array
    {
        $marked = 0;
        $errors = [];

        $bounced = array_flip($this->listmonk->getHardBounces());
        if ($bounced) {
            foreach ($this->ldap->getAll() as $member) {
                $private = (string) $member['email'];
                $org = (string) ($member['orgEmail'] ?? '');
                $privateBounced = isset($bounced[mb_strtolower($private)]) && !MailAddress::isInvalid($private);
                $orgBounced = $org !== '' && isset($bounced[mb_strtolower($org)]) && !MailAddress::isInvalid($org);
                if (!$privateBounced && !$orgBounced) {
                    continue;
                }
                try {
                    $user = $this->userRepository->findOneById((int) $member['id']);
                    if ($user === null) {
                        continue;
                    }
                    if ($privateBounced) {
                        $user->setEmail(MailAddress::mark($private));
                    }
                    if ($orgBounced) {
                        $user->setOrgEmail(MailAddress::mark($org));
                    }
                    $this->userRepository->save($user);
                    $marked++;
                } catch (\Throwable $e) {
                    $errors[] = $member['username'] . ': ' . $e->getMessage();
                }
            }
        }

        return ['marked' => $marked, 'errors' => $errors];
    }
}
