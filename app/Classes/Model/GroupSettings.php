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

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

/**
 * Beschreibung der Gruppeneinstellungen für die Formulare (Einstellungsseite und Dialog der Gruppenverwalter*innen).
 */
class GroupSettings
{
    /** Auswahl für `archive-max-age` (relative Zeit für listig => Anzeige); ohne Eintrag = unbegrenzt */
    public const ARCHIVE_MAX_AGE_OPTIONS = [
        '6 months' => '6 Monate',
        '1 year'   => '1 Jahr',
        '2 years'  => '2 Jahre',
        '5 years'  => '5 Jahre',
    ];

    /** Empfänger-Variablen, die in `personalize` freigegeben werden können (Variable => Anzeige) */
    public const PERSONALIZE_OPTIONS = [
        'firstname' => 'Vorname',
        'lastname'  => 'Nachname',
        'username'  => 'Benutzername',
        'mail'      => 'E-Mail-Adresse',
    ];

    /** Einstellungen mit Enum-Wert: Property => Enum */
    public const ENUM_FIELDS = [
        'joinPolicy'                => JoinPolicy::class,
        'leavePolicy'               => LeavePolicy::class,
        'memberVisibility'          => MemberVisibility::class,
        'visibility'                => GroupVisibility::class,
        'postAccessMembers'         => PostAccessLevel::class,
        'postAccessPublic'          => PostAccessLevel::class,
        'postAccessUnauthenticated' => PostAccessLevel::class,
        'replyTo'                   => ReplyToBehavior::class,
        'fromAddress'               => FromAddress::class,
        'archive'                   => ArchiveMode::class,
    ];

    /**
     * Eine Zeile mit Enum-Wert. isSet = Wert ist gesetzt (sonst gilt der listig-Standard), selected = der
     * anzuzeigende Wert (gesetzter Wert, sonst der Standard).
     */
    private static function enumRow(Group $group, string $field, string $listigKey, string $label, ?string $help = null): array
    {
        $class = self::ENUM_FIELDS[$field];
        $value = $group->$field;
        $default = $class::from(Group::LISTIG_DEFAULTS[$listigKey]);
        return [
            'name' => $field, 'label' => $label, 'type' => 'enum', 'help' => $help,
            'isSet' => $value !== null, 'defaultText' => $default->label(),
            'cases' => $class::cases(), 'selected' => $value ?? $default,
        ];
    }

    private static function textRow(Group $group, string $field, string $listigKey, string $label, string $type = 'text', ?string $help = null): array
    {
        $value = $group->$field;
        $default = Group::LISTIG_DEFAULTS[$listigKey];
        return [
            'name' => $field, 'label' => $label, 'type' => $type, 'help' => $help,
            'isSet' => $value !== null, 'defaultText' => $default, 'text' => $value ?? $default,
        ];
    }

    private static function archiveMaxAgeRow(Group $group): array
    {
        $options = self::ARCHIVE_MAX_AGE_OPTIONS;
        if ($group->archiveMaxAge !== null && !isset($options[$group->archiveMaxAge])) {
            $options[$group->archiveMaxAge] = $group->archiveMaxAge;
        }
        return [
            'name' => 'archiveMaxAge', 'label' => 'Aufbewahrungsdauer des Archivs', 'type' => 'archiveMaxAge', 'help' => null,
            'isSet' => $group->archiveMaxAge !== null, 'defaultText' => 'unbegrenzt',
            'options' => $options, 'selected' => $group->archiveMaxAge,
        ];
    }

    /**
     * Zeilen der Einstellungsseite (alle listig-Einstellungen mit „Standard überschreiben“-Checkbox), nach Abschnitt:
     * 'always' immer, 'list' nur bei einer Mailingliste (Abschnitttitel => Zeilen).
     * Eine Zeile: name, label, type (enum|text|textarea|archiveMaxAge|personalize), isSet, defaultText, help und je
     * nach Typ cases/selected (enum), text (text, textarea), options/selected (archiveMaxAge, personalize).
     */
    public static function adminSections(Group $group): array
    {
        return [
            'always' => [
                'Beitritt und Sichtbarkeit' => [
                    self::enumRow($group, 'joinPolicy', 'join-policy', 'Beitrittspolitik'),
                    self::enumRow($group, 'leavePolicy', 'allow-leave', 'Verlassen'),
                    self::enumRow($group, 'visibility', 'visibility', 'Sichtbarkeit der Gruppe'),
                    self::enumRow($group, 'memberVisibility', 'member-visibility', 'Mitgliederliste sichtbar für'),
                ],
            ],
            'list' => [
                'Mailingliste: Beiträge' => [
                    self::textRow($group, 'listLabel', 'list-label', 'Betreff-Präfix'),
                    self::enumRow($group, 'postAccessMembers', 'post-access-members', 'Posten durch Gruppenmitglieder'),
                    self::enumRow($group, 'postAccessPublic', 'post-access-public', 'Posten durch Nicht-Mitglieder'),
                    self::enumRow($group, 'postAccessUnauthenticated', 'post-access-unauthenticated', 'Posten ohne bestätigte Absenderadresse',
                        'Betrifft Mails, deren Absenderadresse nicht per SPF/DKIM bestätigt ist, auch von Gruppenverwalter*innen.'),
                ],
                'Mailingliste: Absender und Antworten' => [
                    self::enumRow($group, 'replyTo', 'reply-to', 'Antwort geht an'),
                    self::enumRow($group, 'fromAddress', 'from-address', 'Absenderadresse der verteilten Mail'),
                    self::textRow($group, 'listSenderRewrite', 'smtp-from-name', 'Absender-Umschreibung', 'text',
                        'Platzhalter: {sender-name} = ursprüngliche*r Absender*in, {display-name} = Anzeigename der Liste.'),
                ],
                'Mailingliste: Archiv' => [
                    self::enumRow($group, 'archive', 'archive', 'Archiv'),
                    self::archiveMaxAgeRow($group),
                ],
                'Mailingliste: Inhalt der Mails' => [
                    self::textRow($group, 'footer', 'footer', 'Footer (HTML)', 'textarea',
                        'Ein leerer Footer (Haken gesetzt, Feld leer) schaltet ihn ab. Der Standard enthält den Abbestell-Link.'),
                    [
                        'name' => 'personalize', 'label' => 'Personalisierung', 'type' => 'personalize',
                        'help' => 'Diese Angaben der Empfänger*innen dürfen in Betreff und Text der Mails als {firstname}, {lastname}, {username} bzw. {mail} verwendet werden.',
                        'isSet' => $group->personalize !== null, 'defaultText' => 'keine',
                        'options' => self::PERSONALIZE_OPTIONS,
                        'selected' => array_values(array_filter(array_map('trim', explode(',', $group->personalize ?? '')))),
                    ],
                ],
            ],
        ];
    }

    /**
     * Zeilen des vereinfachten Dialogs der Gruppenverwalter*innen (Auswahl „Standard“ statt Checkbox):
     * 'always' immer, 'list' nur bei einer Mailingliste. Aufbau der Zeilen wie bei adminSections().
     */
    public static function ownerRows(Group $group): array
    {
        return [
            'always' => [
                self::enumRow($group, 'joinPolicy', 'join-policy', 'Beitrittspolitik'),
                self::enumRow($group, 'leavePolicy', 'allow-leave', 'Verlassen'),
                self::enumRow($group, 'visibility', 'visibility', 'Sichtbarkeit der Gruppe'),
                self::enumRow($group, 'memberVisibility', 'member-visibility', 'Mitgliederliste sichtbar für'),
            ],
            'list' => [
                self::textRow($group, 'listLabel', 'list-label', 'Betreff-Präfix'),
                self::enumRow($group, 'postAccessMembers', 'post-access-members', 'Posten durch Gruppenmitglieder'),
                self::enumRow($group, 'replyTo', 'reply-to', 'Antwort geht an'),
                self::enumRow($group, 'archive', 'archive', 'Archiv'),
                self::archiveMaxAgeRow($group),
            ],
        ];
    }
}
