<?php
declare(strict_types=1);
namespace App\Repository;

use App\Model\Enum\GroupVisibility;
use App\Model\Enum\JoinPolicy;
use App\Model\Enum\LeavePolicy;
use App\Model\Enum\ListPostPolicy;
use App\Model\Enum\MemberVisibility;
use App\Model\Group;
use App\Service\Ldap;
use Symfony\Component\Ldap\Entry;

class GroupRepository
{
    /** LDAP verwendet kebab-case-Keys im description-Attribut (siehe Doku "LDAP description[] keys");
     *  hier auf die camelCase-Keys der Config gemappt.
     *  Alle Präfixe, die hier nicht aufgeführt sind, bleiben beim Speichern unverändert erhalten. */
    private const array DESCRIPTION_KEY_MAP = [
        'display-name'    => 'name',
        'description'     => 'text',
        'join-policy'     => 'joinPolicy',
        'allow-leave'     => 'leavePolicy',
        'member-visibility' => 'memberVisibility',
        'visibility'      => 'visibility',
        'privileged'      => 'privileged',
        'list-label'      => 'listLabel',
        'post-access'     => 'listPostPolicy',
        'smtp-from-name'  => 'listSenderRewrite',
    ];

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

        // groupOfNames requires at least one member; bind DN as invisible placeholder for empty groups
        $effectiveMemberDns = $memberDns ?: [$this->ldap->getBindDn()];

        $descriptionValues = array_values(array_filter([
            $group->displayName !== ''  ? 'display-name:'     . $group->displayName               : null,
            $group->description !== ''  ? 'description:'      . $group->description               : null,
            'join-policy:'         . $group->joinPolicy->value,
            'allow-leave:'         . $group->leavePolicy->value,
            'member-visibility:'   . $group->memberVisibility->value,
            'visibility:'          . $group->visibility->value,
            $group->privileged    ? 'privileged:true'                                              : null,
            $group->isMailGroup   ? 'list-label:'       . $group->listLabel                        : null,
            $group->isMailGroup   ? 'post-access:'      . $group->listPostPolicy->value             : null,
            $group->isMailGroup   ? 'smtp-from-name:'   . $group->listSenderRewrite                 : null,
        ]));
        $descriptionValues = array_merge($descriptionValues, $group->unknownDescriptionLines);

        if ($group->ldapEntry === null) {
            $attributes = [
                'objectClass' => $objectClasses,
                'cn'          => [$group->name],
                'member'      => $effectiveMemberDns,
                'description' => $descriptionValues,
            ];
            if ($group->category !== '')        $attributes['ou']    = [$group->category];
            if (!empty($ownerDns))              $attributes['owner'] = $ownerDns;
            if ($group->mailAddress !== null)   $attributes['mail']  = [$group->mailAddress];
            $entry = new Entry($this->ldap->getDnByGroupName($group->name), $attributes);
            $this->ldap->addGroupEntry($entry);
            $group->ldapEntry = $entry;
        } else {
            $entry = $group->ldapEntry;
            $entry->setAttribute('objectClass',   $objectClasses);
            $entry->setAttribute('member',        $effectiveMemberDns);
            $entry->setAttribute('owner',         $ownerDns);
            $entry->setAttribute('description',   $descriptionValues);
            $entry->setAttribute('ou',            $group->category !== '' ? [$group->category] : []);
            $entry->setAttribute('mail',          $group->mailAddress !== null ? [$group->mailAddress] : []);
            $entry->setAttribute('businessCategory', []); // remove legacy field if present
            $this->ldap->updateGroupEntry($entry);
        }
    }

    public function delete(Group $group): void
    {
        $this->ldap->deleteGroup($group->name);
    }

    private function entryToGroup(Entry $entry): Group
    {
        $config = [];
        $unknownDescriptionLines = [];
        foreach ($entry->getAttribute('description') ?? [] as $val) {
            $sep = strpos($val, ':');
            $key = $sep !== false ? substr($val, 0, $sep) : $val;
            if ($sep !== false && array_key_exists($key, self::DESCRIPTION_KEY_MAP)) {
                $config[self::DESCRIPTION_KEY_MAP[$key]] = substr($val, $sep + 1);
            } else {
                $unknownDescriptionLines[] = $val;
            }
        }

        $memberUsernames = array_values(array_filter(array_map(
            $this->ldap->getUsernameFromDn(...),
            $entry->getAttribute('member') ?? []
        )));

        $ownerUsernames = array_values(array_filter(array_map(
            $this->ldap->getUsernameFromDn(...),
            $entry->getAttribute('owner') ?? []
        )));

        $group = new Group(
            name:              $entry->getAttribute('cn')[0] ?? '',
            displayName:       $config['name'] ?? '',
            description:       $config['text'] ?? '',
            category:          ($entry->getAttribute('ou') ?? [])[0] ?? '',
            memberUsernames:   $memberUsernames,
            ownerUsernames:    $ownerUsernames,
            mailAddress:       ($entry->getAttribute('mail') ?? [])[0] ?? null,
            joinPolicy:        JoinPolicy::tryFrom($config['joinPolicy'] ?? '')       ?? JoinPolicy::Invite,
            leavePolicy:       LeavePolicy::tryFrom($config['leavePolicy'] ?? '')     ?? LeavePolicy::Allowed,
            memberVisibility:  MemberVisibility::tryFrom($config['memberVisibility'] ?? '') ?? MemberVisibility::Members,
            visibility:        GroupVisibility::tryFrom($config['visibility'] ?? '')  ?? GroupVisibility::Public,
            privileged:        ($config['privileged'] ?? '') === 'true',
            listLabel:         $config['listLabel'] ?? '',
            listPostPolicy:    ListPostPolicy::tryFrom($config['listPostPolicy'] ?? '') ?? ListPostPolicy::Members,
            listSenderRewrite: $config['listSenderRewrite'] ?? '{sender-name} (via MHN)',
            unknownDescriptionLines: $unknownDescriptionLines,
        );
        $group->ldapEntry = $entry;
        return $group;
    }
}
