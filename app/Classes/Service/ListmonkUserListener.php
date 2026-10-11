<?php
declare(strict_types=1);
namespace App\Service;

use App\Repository\GroupRepository;

/**
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

/**
 * Überträgt eine geänderte Adresse sofort nach ListMonk (statt erst beim nächsten Abgleich). Schlägt das
 * fehl, bleibt die Änderung gespeichert; der regelmäßige Abgleich holt es nach.
 */
class ListmonkUserListener implements UserMailListener
{
    public function __construct(
        private Listmonk $listmonk,
        private Ldap $ldap,
        private GroupRepository $groupRepository,
    ) {}

    public function mailChanged(string $username): void
    {
        if (!$this->listmonk->isConfigured()) {
            return;
        }
        try {
            $member = $this->ldap->getMemberByUsername($username);
            if ($member === null) {
                return;
            }
            $this->listmonk->syncMember(
                ListmonkSync::memberFor($member),
                $this->groupRepository->findOneByName(ListmonkSync::GROUP_ALL)?->isMember($username) ?? false,
                $this->groupRepository->findOneByName(NewsletterGroupListener::GROUP)?->isMember($username) ?? false,
            );
        } catch (\Throwable $e) {
            error_log('ListMonk: Adressänderung von ' . $username . ' konnte nicht übertragen werden: ' . $e->getMessage());
        }
    }
}
