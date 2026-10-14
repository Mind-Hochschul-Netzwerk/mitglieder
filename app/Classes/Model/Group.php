<?php
declare(strict_types=1);
namespace App\Model;

use App\Model\Enum\ArchiveMode;
use App\Model\Enum\FromAddress;
use App\Model\Enum\GroupVisibility;
use App\Model\Enum\JoinPolicy;
use App\Model\Enum\LeavePolicy;
use App\Model\Enum\MemberVisibility;
use App\Model\Enum\PostAccessLevel;
use App\Model\Enum\ReplyToBehavior;
use App\Model\Enum\SenderAddressHeader;
use Symfony\Component\Ldap\Entry;

class Group
{
    /** Standard der Absender-Umschreibung laut listig-Konfiguration (nur als Platzhalter im Formular) */
    public const string DEFAULT_FROM_NAME = '{sender-name} (via MHN)';

    /**
     * Listig-Standards: wirksam, wenn eine Einstellung nicht gesetzt ist (kein description-Eintrag), also
     * die Werte, die listig bzw. dessen config.yml dann verwendet (memberVisibility kennt nur diese Anwendung)
     */
    public const JoinPolicy LISTIG_DEFAULT_JOIN_POLICY = JoinPolicy::Invite;
    public const LeavePolicy LISTIG_DEFAULT_LEAVE_POLICY = LeavePolicy::Direct;
    public const MemberVisibility LISTIG_DEFAULT_MEMBER_VISIBILITY = MemberVisibility::Members;
    // laut config.yml (list-defaults); der Code-Default von listig wäre `members`
    public const GroupVisibility LISTIG_DEFAULT_VISIBILITY = GroupVisibility::Authenticated;
    public const PostAccessLevel LISTIG_DEFAULT_POST_ACCESS_MEMBERS = PostAccessLevel::Allow;
    public const PostAccessLevel LISTIG_DEFAULT_POST_ACCESS_PUBLIC = PostAccessLevel::Deny;
    public const ReplyToBehavior LISTIG_DEFAULT_REPLY_TO = ReplyToBehavior::List;
    public const ArchiveMode LISTIG_DEFAULT_ARCHIVE = ArchiveMode::Off;

    /**
     * Alle Listig-Standards als Anzeigewerte, nach listig-Schlüssel (description-Key). Quelle: listig-Code-Defaults
     * (ListConfig::DEFAULTS) überlagert von der config.yml der Installation (list-defaults, globale Werte).
     * Werden von Hand nachgezogen, wenn sich dort etwas ändert. null = kein Wert (Einstellung greift nicht).
     * Platzhalter wie {display-name} sind nur Anzeige und werden hier nicht aufgelöst.
     */
    public const array LISTIG_DEFAULTS = [
        'display-name'                => null, // Gruppenname (cn)
        'description'                 => null,
        'join-policy'                 => self::LISTIG_DEFAULT_JOIN_POLICY->value,
        'leave-policy'                => self::LISTIG_DEFAULT_LEAVE_POLICY->value,
        'member-visibility'           => self::LISTIG_DEFAULT_MEMBER_VISIBILITY->value, // nur diese Anwendung
        'visibility'                  => self::LISTIG_DEFAULT_VISIBILITY->value,
        'list-label'                  => '[{display-name}]',
        'post-access-members'         => self::LISTIG_DEFAULT_POST_ACCESS_MEMBERS->value,
        'post-access-public'          => self::LISTIG_DEFAULT_POST_ACCESS_PUBLIC->value,
        'post-access-unauthenticated' => PostAccessLevel::Moderate->value, // config.yml; listig-Code-Default: allow
        'from-name'                   => self::DEFAULT_FROM_NAME,
        'reply-to'                    => self::LISTIG_DEFAULT_REPLY_TO->value,
        'from-address'                => 'masked', // listig-Code-Default: list
        'sender-address-header'       => 'never',
        'archive'                     => self::LISTIG_DEFAULT_ARCHIVE->value,
        'archive-max-age'             => '12 months', // listig-Code-Default: unbegrenzt
        'footer'                      => "-- \n<p>Diese Mail wurde über die MHN-Liste {display-name} verschickt.</p>"
            . '<p>Falls du keine Mails über diese Liste mehr bekommen möchtest, kannst du sie hier abbestellen: '
            . '<a href="{group-url}">Zur Liste</a></p>{archive-block}',
        'max-size'                    => '5M',
        'max-per-sender'              => '5', // pro 10 Minuten
        'personalize'                 => null, // nur {list-url}
    ];

    /**
     * Unsere Standards für neue Gruppen, soweit sie bewusst vom Listig-Standard abweichen (Property => Wert):
     * nur diese Keys werden beim Anlegen explizit gespeichert (siehe applyNewGroupDefaults()), alle anderen
     * bleiben nicht gesetzt, es gilt dann der Listig-Standard. Aktuell weicht keiner ab.
     */
    public const array NEW_GROUP_OVERRIDES = [];

    public ?Entry $ldapEntry = null;

    // wirksame Werte (Listig-Standard, falls nicht gesetzt) für die Anwendungslogik; die Properties selbst
    // enthalten den gespeicherten Wert (null = nicht gesetzt)
    public JoinPolicy $effectiveJoinPolicy { get => $this->joinPolicy ?? self::LISTIG_DEFAULT_JOIN_POLICY; }
    public LeavePolicy $effectiveLeavePolicy { get => $this->leavePolicy ?? self::LISTIG_DEFAULT_LEAVE_POLICY; }
    public MemberVisibility $effectiveMemberVisibility { get => $this->memberVisibility ?? self::LISTIG_DEFAULT_MEMBER_VISIBILITY; }
    public GroupVisibility $effectiveVisibility { get => $this->visibility ?? self::LISTIG_DEFAULT_VISIBILITY; }
    public PostAccessLevel $effectivePostAccessMembers { get => $this->postAccessMembers ?? self::LISTIG_DEFAULT_POST_ACCESS_MEMBERS; }
    public PostAccessLevel $effectivePostAccessPublic { get => $this->postAccessPublic ?? self::LISTIG_DEFAULT_POST_ACCESS_PUBLIC; }
    public ReplyToBehavior $effectiveReplyTo { get => $this->replyTo ?? self::LISTIG_DEFAULT_REPLY_TO; }
    public ArchiveMode $effectiveArchive { get => $this->archive ?? self::LISTIG_DEFAULT_ARCHIVE; }

    /**
     * Alle Einstellungen, die listig-Schlüssel sind, haben null = nicht gesetzt (kein description-Eintrag,
     * listig-/globaler Standard gilt) und '' = bewusst leer gesetzt (Eintrag "key:").
     */
    public function __construct(
        public readonly string $name,
        public ?string $displayName = null,
        public ?string $description = null,
        public string $category = '',
        public private(set) array $memberUsernames = [],
        public private(set) array $ownerUsernames = [],
        public ?string $mailAddress = null,
        public ?JoinPolicy $joinPolicy = null,
        public ?LeavePolicy $leavePolicy = null,
        public ?MemberVisibility $memberVisibility = null,
        public ?GroupVisibility $visibility = null,
        public bool $privileged = false,
        public ?string $listLabel = null,
        public ?PostAccessLevel $postAccessMembers = null,
        public ?PostAccessLevel $postAccessPublic = null,
        public ?string $fromName = null,
        public ?ReplyToBehavior $replyTo = null,
        public ?ArchiveMode $archive = null,
        public string $listPasswordCiphertext = '',
        public string $apiToken = '',
        public ?PostAccessLevel $postAccessUnauthenticated = null,
        public ?FromAddress $fromAddress = null,
        public ?SenderAddressHeader $senderAddressHeader = null,
        public ?string $footer = null,
        public ?string $maxSize = null,
        public ?string $maxPerSender = null,
        public ?string $archiveMaxAge = null,
        public ?string $personalize = null,
        public private(set) array $unknownDescriptionLines = [],
    ) {}

    /** Setzt die Einstellungen, bei denen wir für neue Gruppen vom Listig-Standard abweichen (NEW_GROUP_OVERRIDES) */
    public function applyNewGroupDefaults(): void
    {
        foreach (self::NEW_GROUP_OVERRIDES as $property => $value) {
            $this->$property = $value;
        }
    }

    public function isMailingList(): bool
    {
        return $this->mailAddress !== null && $this->hasListPassword();
    }

    public function hasListPassword(): bool
    {
        return $this->listPasswordCiphertext !== '';
    }

    /**
     * Anschreiben darf, wer die Gruppenadresse tatsächlich nutzen kann: bei normalen Adressen jede*r,
     * bei Mailinglisten Owner, Mitglieder (sofern Mitglieder-Posting erlaubt) bzw. alle (sofern externes Posting erlaubt)
     */
    public function canWriteMail(string $username): bool
    {
        return $this->mailAddress !== null && $this->mailAddress !== '' && (
            !$this->hasListPassword()
            || ($this->isMember($username) && $this->effectivePostAccessMembers !== PostAccessLevel::Deny)
            || $this->effectivePostAccessPublic !== PostAccessLevel::Deny
            || $this->isOwner($username)
        );
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
