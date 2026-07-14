<?php
declare(strict_types=1);
namespace App\Model;

use App\Model\Enum\GroupVisibility;
use App\Model\Enum\JoinPolicy;
use App\Model\Enum\LeavePolicy;
use App\Model\Enum\ListPostPolicy;
use App\Model\Enum\MemberVisibility;
use Symfony\Component\Ldap\Entry;

class Group
{
    public ?Entry $ldapEntry = null;

    public function __construct(
        public readonly string $name,
        public string $displayName = '',
        public string $description = '',
        public string $category = '',
        public private(set) array $memberUsernames = [],
        public private(set) array $ownerUsernames = [],
        public ?string $mailAddress = null,
        public JoinPolicy $joinPolicy = JoinPolicy::Invite,
        public LeavePolicy $leavePolicy = LeavePolicy::Allowed,
        public MemberVisibility $memberVisibility = MemberVisibility::Members,
        public GroupVisibility $visibility = GroupVisibility::Public,
        public bool $privileged = false,
        public bool $isMailGroup = false,
        public string $listLabel = '',
        public ListPostPolicy $listPostPolicy = ListPostPolicy::Members,
        public string $listSenderRewrite = '{sender-name} (via MHN)',
        public private(set) array $unknownDescriptionLines = [],
    ) {}

    public function isMember(string $username): bool
    {
        return in_array($username, $this->memberUsernames, true);
    }

    public function isOwner(string $username): bool
    {
        return in_array($username, $this->ownerUsernames, true);
    }

    public function addMember(string $username): void
    {
        if (!$this->isMember($username)) {
            $this->memberUsernames[] = $username;
        }
    }

    public function removeMember(string $username): void
    {
        $this->memberUsernames = array_values(
            array_filter($this->memberUsernames, fn($u) => $u !== $username)
        );
    }

    public function addOwner(string $username): void
    {
        if (!$this->isOwner($username)) {
            $this->ownerUsernames[] = $username;
        }
    }

    public function removeOwner(string $username): void
    {
        $this->ownerUsernames = array_values(
            array_filter($this->ownerUsernames, fn($u) => $u !== $username)
        );
    }
}
