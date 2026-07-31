<?php
declare(strict_types=1);
namespace App\Model;

use App\Model\Enum\ArchiveMode;
use App\Model\Enum\GroupVisibility;
use App\Model\Enum\JoinPolicy;
use App\Model\Enum\LeavePolicy;
use App\Model\Enum\MemberVisibility;
use App\Model\Enum\PostAccessLevel;
use App\Model\Enum\ReplyToBehavior;
use Symfony\Component\Ldap\Entry;

class Group
{
    public ?Entry $ldapEntry = null;

    public string $displayName {
        get => $this->displayName !== '' ? $this->displayName : $this->name;
    }

    public function __construct(
        public readonly string $name,
        string $displayName = '',
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
        public string $listLabel = '',
        public PostAccessLevel $postAccessMembers = PostAccessLevel::Allow,
        public PostAccessLevel $postAccessPublic = PostAccessLevel::Deny,
        public string $listSenderRewrite = '{sender-name} (via MHN)',
        public ReplyToBehavior $replyTo = ReplyToBehavior::List,
        public ArchiveMode $archive = ArchiveMode::Members,
        public string $listPasswordCiphertext = '',
        public string $apiToken = '',
        public private(set) array $unknownDescriptionLines = [],
    ) {
        $this->displayName = $displayName;
    }

    public function hasListPassword(): bool
    {
        return $this->listPasswordCiphertext !== '';
    }

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
