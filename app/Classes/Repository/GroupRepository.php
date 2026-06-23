<?php
declare(strict_types=1);
namespace App\Repository;

use App\Model\Enum\GroupVisibility;
use App\Model\Enum\JoinPolicy;
use App\Model\Enum\LeavePolicy;
use App\Model\Enum\MemberVisibility;
use App\Model\Group;
use App\Service\Ldap;
use Symfony\Component\Ldap\Entry;

class GroupRepository
{
    public function __construct(
        private Ldap $ldap
    ) {}

    public function findOneByName(string $name): ?Group
    {
        $entry = $this->ldap->getGroupEntry($name);
        if (!$entry) {
            return null;
        }
        return $this->entryToGroup($entry);
    }

    /** @return Group[] */
    public function findAll(): array
    {
        return array_map($this->entryToGroup(...), $this->ldap->getAllGroupEntries());
    }

    public function save(Group $group): void
    {
        $memberDns = array_map($this->ldap->getDnByUsername(...), $group->memberUsernames);
        $ownerDns = array_map($this->ldap->getDnByUsername(...), $group->ownerUsernames);
        $objectClasses = array_values(array_filter([
            'top', 'groupOfNames', $group->isMailGroup ? 'mailGroup' : null,
        ]));
        $configJson = json_encode([
            'joinPolicy' => $group->joinPolicy->value,
            'leavePolicy' => $group->leavePolicy->value,
            'memberVisibility' => $group->memberVisibility->value,
            'visibility' => $group->visibility->value,
        ], JSON_UNESCAPED_UNICODE);

        // groupOfNames requires at least one member; bind DN as invisible placeholder for empty groups
        $effectiveMemberDns = $memberDns ?: [$this->ldap->getBindDn()];

        if ($group->ldapEntry === null) {
            $attributes = [
                'objectClass' => $objectClasses,
                'cn' => [$group->name],
                'member' => $effectiveMemberDns,
                'businessCategory' => [$configJson],
            ];
            if ($group->displayName !== '') {
                $attributes['description'] = [$group->displayName];
            }
            if ($group->category !== '') {
                $attributes['ou'] = [$group->category];
            }
            if (!empty($ownerDns)) {
                $attributes['owner'] = $ownerDns;
            }
            if ($group->mailAddress !== null) {
                $attributes['mail'] = [$group->mailAddress];
            }
            $entry = new Entry($this->ldap->getDnByGroupName($group->name), $attributes);
            $this->ldap->addGroupEntry($entry);
            $group->ldapEntry = $entry;
        } else {
            $entry = $group->ldapEntry;
            $entry->setAttribute('objectClass', $objectClasses);
            $entry->setAttribute('member', $effectiveMemberDns);
            $entry->setAttribute('owner', $ownerDns);
            $entry->setAttribute('description', $group->displayName !== '' ? [$group->displayName] : []);
            $entry->setAttribute('ou', $group->category !== '' ? [$group->category] : []);
            $entry->setAttribute('mail', $group->mailAddress !== null ? [$group->mailAddress] : []);
            $entry->setAttribute('businessCategory', [$configJson]);
            $this->ldap->updateGroupEntry($entry);
        }
    }

    public function delete(Group $group): void
    {
        $this->ldap->deleteGroup($group->name);
    }

    private function entryToGroup(Entry $entry): Group
    {
        $config = json_decode($entry->getAttribute('businessCategory')[0] ?? '{}', true) ?: [];

        $memberUsernames = array_values(array_filter(array_map(
            $this->ldap->getUsernameFromDn(...),
            $entry->getAttribute('member') ?? []
        )));

        $ownerUsernames = array_values(array_filter(array_map(
            $this->ldap->getUsernameFromDn(...),
            $entry->getAttribute('owner') ?? []
        )));

        $group = new Group(
            name: $entry->getAttribute('cn')[0] ?? '',
            displayName: $entry->getAttribute('description')[0] ?? '',
            category: $entry->getAttribute('ou')[0] ?? '',
            memberUsernames: $memberUsernames,
            ownerUsernames: $ownerUsernames,
            mailAddress: ($entry->getAttribute('mail') ?? [])[0] ?? null,
            joinPolicy: JoinPolicy::tryFrom($config['joinPolicy'] ?? '') ?? JoinPolicy::Invite,
            leavePolicy: LeavePolicy::tryFrom($config['leavePolicy'] ?? '') ?? LeavePolicy::Allowed,
            memberVisibility: MemberVisibility::tryFrom($config['memberVisibility'] ?? '') ?? MemberVisibility::Members,
            visibility: GroupVisibility::tryFrom($config['visibility'] ?? '') ?? GroupVisibility::Public,
        );
        $group->ldapEntry = $entry;
        return $group;
    }
}
