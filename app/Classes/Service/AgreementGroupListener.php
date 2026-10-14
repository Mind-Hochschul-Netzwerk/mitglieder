<?php
declare(strict_types=1);
namespace App\Service;

use App\Model\Group;
use Hengeb\Router\Exception\InvalidUserDataException;

/**
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

/**
 * Verhindert, dass jemand Mitglied einer Gruppe wird, die eine Version der Verpflichtung zum Datenschutz verlangt,
 * ohne sie abgegeben zu haben (gilt für jeden Weg über das GroupRepository, nicht nur für die Beitrittsbuttons).
 */
class AgreementGroupListener implements GroupMembershipListener
{
    public function __construct(
        private PrivacyAgreementCheck $check,
    ) {}

    public function membershipChanging(Group $group, array $added, array $removed): void
    {
        if ($group->requiredAgreementVersion === null || !$added) {
            return;
        }
        $lacking = $this->check->membersLacking($added, $group->requiredAgreementVersion);
        if ($lacking) {
            $names = implode(', ', array_column($lacking, 'name'));
            throw new InvalidUserDataException(
                $names . ' ' . (count($lacking) === 1 ? 'hat' : 'haben') . ' keine aktuelle Verpflichtungserklärung zum Datenschutz '
                . '(mindestens Version ' . $group->requiredAgreementVersion . ') abgegeben und '
                . (count($lacking) === 1 ? 'kann' : 'können') . ' deshalb nicht Mitglied der Gruppe werden.'
            );
        }
    }
}
