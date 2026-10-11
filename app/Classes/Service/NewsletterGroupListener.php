<?php
declare(strict_types=1);
namespace App\Service;

use App\Model\Group;

/**
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

/**
 * Überträgt Änderungen der Mitgliedschaft in der Gruppe „newsletter“ sofort nach ListMonk. So gilt dort
 * beim Abgleich (ListmonkSync) eine Abmeldung in ListMonk immer als neuer als der Stand im LDAP.
 */
class NewsletterGroupListener implements GroupMembershipListener
{
    public const string GROUP = 'newsletter';

    public function __construct(
        private Listmonk $listmonk,
        private Ldap $ldap,
    ) {}

    public function membershipChanging(Group $group, array $added, array $removed): void
    {
        if ($group->name !== self::GROUP || !$this->listmonk->isNewsletterConfigured()) {
            return;
        }
        foreach ($added as $username) {
            $member = $this->ldap->getMemberByUsername($username);
            if ($member) {
                $this->listmonk->subscribeNewsletter(ListmonkSync::memberFor($member));
            }
        }
        foreach ($removed as $username) {
            $member = $this->ldap->getMemberByUsername($username);
            if ($member) {
                $this->listmonk->unsubscribeNewsletter(ListmonkSync::memberFor($member));
            }
        }
    }
}
