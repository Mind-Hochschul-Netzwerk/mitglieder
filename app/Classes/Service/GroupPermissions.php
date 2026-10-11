<?php
declare(strict_types=1);
namespace App\Service;

use App\Model\Group;
use Hengeb\Router\Exception\AccessDeniedException;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

/**
 * Wer darf eine Gruppe verwalten bzw. ihre Einstellungen ändern?
 *
 * - Gruppenverwalter*innen (Owner): alltägliche Verwaltung und der vereinfachte Einstellungsdialog
 * - groupadmin: alle Einstellungen (Einstellungsseite); bei privilegierten Gruppen nur `rechte`
 * - rechte: zusätzlich das Flag „privilegiert“
 */
class GroupPermissions
{
    public function __construct(
        private CurrentUser $currentUser,
    ) {}

    public function isGroupAdmin(): bool
    {
        return $this->currentUser->hasRole('groupadmin');
    }

    public function isRechteAdmin(): bool
    {
        return $this->currentUser->hasRole('rechte');
    }

    public function isOwner(Group $group): bool
    {
        return $group->isOwner($this->currentUser->get('username'));
    }

    public function mayManage(Group $group): bool
    {
        if ($this->isOwner($group)) {
            return true;
        }
        if ($group->privileged) {
            return $this->isRechteAdmin();
        }
        return $this->isGroupAdmin();
    }

    public function requireManage(Group $group): void
    {
        if (!$this->mayManage($group)) {
            throw new AccessDeniedException();
        }
    }

    /** darf die Einstellungsseite mit allen Einstellungen (und das Mailserver-Passwort) bearbeiten */
    public function mayAdminister(Group $group): bool
    {
        return $this->isGroupAdmin() && $this->mayManage($group);
    }

    public function requireAdminister(Group $group): void
    {
        if (!$this->mayAdminister($group)) {
            throw new AccessDeniedException();
        }
    }
}
