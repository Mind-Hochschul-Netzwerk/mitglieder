<?php
declare(strict_types=1);
namespace App\Service;

use App\Model\Group;

/**
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

/**
 * Wird vom GroupRepository vor dem Speichern aufgerufen, wenn sich die Mitglieder einer Gruppe ändern.
 * Eine Exception bricht das Speichern ab (die Gruppe im LDAP bleibt dann unverändert).
 */
interface GroupMembershipListener
{
    /**
     * @param string[] $added Benutzernamen, die neu in der Gruppe sind
     * @param string[] $removed Benutzernamen, die nicht mehr in der Gruppe sind
     */
    public function membershipChanging(Group $group, array $added, array $removed): void;
}
