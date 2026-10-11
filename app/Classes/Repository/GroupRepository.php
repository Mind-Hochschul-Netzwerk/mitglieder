<?php
declare(strict_types=1);
namespace App\Repository;

use App\Model\Enum\ArchiveMode;
use App\Model\Enum\FromAddress;
use App\Model\Enum\GroupVisibility;
use App\Model\Enum\JoinPolicy;
use App\Model\Enum\LeavePolicy;
use App\Model\Enum\MemberVisibility;
use App\Model\Enum\PostAccessLevel;
use App\Model\Enum\ReplyToBehavior;
use App\Model\Enum\SenderAddressHeader;
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
        'post-access-members' => 'postAccessMembers',
        'post-access-public'  => 'postAccessPublic',
        'smtp-from-name'  => 'listSenderRewrite',
        'reply-to'        => 'replyTo',
        'archive'         => 'archive',
        'mail-password'   => 'listPasswordCiphertext',
        'api-token'       => 'apiToken',
        'post-access-unauthenticated' => 'postAccessUnauthenticated',
        'from-address'    => 'fromAddress',
        'sender-address-header' => 'senderAddressHeader',
        'footer'          => 'footer',
        'max-size'        => 'maxSize',
        'max-per-sender'  => 'maxPerSender',
        'archive-max-age' => 'archiveMaxAge',
        'personalize'     => 'personalize',
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
            'top', 'groupOfNames', $group->mailAddress !== null ? 'mailGroup' : null,
        ]));

        // groupOfNames requires at least one member; bind DN as invisible placeholder for empty groups
        $effectiveMemberDns = $memberDns ?: [$this->ldap->getBindDn()];

        // null = nicht gesetzt (kein Eintrag), '' = bewusst leer (Eintrag "key:")
        $descriptionValues = array_values(array_filter([
            self::line('display-name', $group->displayName),
            self::line('description', $group->description),
            self::line('join-policy', $group->joinPolicy?->value),
            self::line('allow-leave', $group->leavePolicy?->value),
            self::line('member-visibility', $group->memberVisibility?->value),
            self::line('visibility', $group->visibility?->value),
            $group->privileged    ? 'privileged:true'                                              : null,
            self::line('list-label', $group->listLabel),
            self::line('post-access-members', $group->postAccessMembers?->value),
            self::line('post-access-public', $group->postAccessPublic?->value),
            self::line('post-access-unauthenticated', $group->postAccessUnauthenticated?->value),
            self::line('smtp-from-name', $group->listSenderRewrite),
            self::line('reply-to', $group->replyTo?->value),
            self::line('from-address', $group->fromAddress?->value),
            self::line('sender-address-header', $group->senderAddressHeader?->value),
            self::line('archive', $group->archive?->value),
            self::line('archive-max-age', $group->archiveMaxAge),
            self::line('footer', $group->footer),
            self::line('max-size', $group->maxSize),
            self::line('max-per-sender', $group->maxPerSender),
            self::line('personalize', $group->personalize),
            // Passwort-Chiffrat und Token werden unverändert zurückgeschrieben (das description-Attribut wird
            // komplett ersetzt); überschrieben werden sie nur, wenn das Model einen neuen Wert enthält
            $group->listPasswordCiphertext !== '' ? 'mail-password:' . $group->listPasswordCiphertext : null,
            $group->apiToken !== ''               ? 'api-token:'     . $group->apiToken               : null,
        ], fn(?string $line) => $line !== null));
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
            $this->ldap->updateGroupEntry($entry);
        }
    }

    public function delete(Group $group): void
    {
        $this->ldap->deleteGroup($group->name);
    }

    /** description-Zeile "key:value"; null (nicht gesetzt) ergibt keine Zeile, '' eine Zeile mit leerem Wert */
    private static function line(string $key, ?string $value): ?string
    {
        return $value === null ? null : $key . ':' . $value;
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
            displayName:       $config['name'] ?? null,
            description:       $config['text'] ?? null,
            category:          ($entry->getAttribute('ou') ?? [])[0] ?? '',
            memberUsernames:   $memberUsernames,
            ownerUsernames:    $ownerUsernames,
            mailAddress:       ($entry->getAttribute('mail') ?? [])[0] ?? null,
            joinPolicy:        JoinPolicy::tryFrom($config['joinPolicy'] ?? ''),
            leavePolicy:       LeavePolicy::tryFrom($config['leavePolicy'] ?? ''),
            memberVisibility:  MemberVisibility::tryFrom($config['memberVisibility'] ?? ''),
            visibility:        GroupVisibility::tryFrom($config['visibility'] ?? ''),
            privileged:        ($config['privileged'] ?? '') === 'true',
            listLabel:         $config['listLabel'] ?? null,
            postAccessMembers: PostAccessLevel::tryFrom($config['postAccessMembers'] ?? ''),
            postAccessPublic:  PostAccessLevel::tryFrom($config['postAccessPublic'] ?? ''),
            listSenderRewrite: $config['listSenderRewrite'] ?? null,
            replyTo:           ReplyToBehavior::tryFrom($config['replyTo'] ?? ''),
            archive:           ArchiveMode::tryFrom($config['archive'] ?? ''),
            listPasswordCiphertext: $config['listPasswordCiphertext'] ?? '',
            apiToken:          $config['apiToken'] ?? '',
            postAccessUnauthenticated: PostAccessLevel::tryFrom($config['postAccessUnauthenticated'] ?? ''),
            fromAddress:       FromAddress::tryFrom($config['fromAddress'] ?? ''),
            senderAddressHeader: SenderAddressHeader::tryFrom($config['senderAddressHeader'] ?? ''),
            footer:            $config['footer'] ?? null,
            maxSize:           $config['maxSize'] ?? null,
            maxPerSender:      $config['maxPerSender'] ?? null,
            archiveMaxAge:     $config['archiveMaxAge'] ?? null,
            personalize:       $config['personalize'] ?? null,
            unknownDescriptionLines: $unknownDescriptionLines,
        );
        $group->ldapEntry = $entry;
        return $group;
    }
}
