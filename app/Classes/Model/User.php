<?php
namespace App\Model;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

use App\Repository\UserRepository;
use App\Service\Ldap;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\Ldap\Entry;

/**
 * Repräsentiert ein User
 */
class User
{
    const string PROFILE_PICUTRE_DIRECTORY = '/var/www/profilbilder/';

    public array $data = [];

    public ?Entry $ldapEntry = null;
    private $deleted = false;

    // Felder und Defaults
    const felder = [
        'id' => null,
        'username' => '',
        'vorname' => '',
        'nachname' => '',
        'sichtbarkeit_email' => false,
        'titel' => '',
        'geburtstag' => null,
        'aufnahmedatum' => null,
        'sichtbarkeit_geburtstag' => false,
        'profilbild' => '',
        'mensa_nr' => '',
        'sichtbarkeit_mensa_nr' => false,
        'strasse' => '',
        'sichtbarkeit_strasse' => false,
        'adresszusatz' => '',
        'sichtbarkeit_adresszusatz' => false,
        'plz' => '',
        'ort' => '',
        'sichtbarkeit_plz_ort' => false,
        'land' => '',  // ISO 3166-1 alpha-2 code
        'sichtbarkeit_land' => false,
        'strasse2' => '',
        'adresszusatz2' => '',
        'plz2' => '',
        'ort2' => '',
        'land2' => '', // ISO 3166-1 alpha-2 code
        'telefon' => '',
        'sichtbarkeit_telefon' => false,
        'homepage' => '',
        'sprachen' => '',
        'hobbys' => '',
        'interessen' => '',
        'studienort' => '',
        'sichtbarkeit_studienort' => false,
        'studienfach' => '',
        'sichtbarkeit_studienfach' => false,
        'unityp' => '',
        'sichtbarkeit_unityp' => false,
        'schwerpunkt' => '',
        'sichtbarkeit_schwerpunkt' => false,
        'nebenfach' => '',
        'sichtbarkeit_nebenfach' => false,
        'abschluss' => '',
        'sichtbarkeit_abschluss' => false,
        'zweitstudium' => '',
        'sichtbarkeit_zweitstudium' => false,
        'ehrenamt' => '',
        'sichtbarkeit_ehrenamt' => false,
        'stipendien' => '',
        'sichtbarkeit_stipendien' => false,
        'auslandsaufenthalte' => '',
        'sichtbarkeit_auslandsaufenthalte' => false,
        'praktika' => '',
        'sichtbarkeit_praktika' => false,
        'beruf' => '',
        'sichtbarkeit_beruf' => false,
        'fruehere_taetigkeiten' => '',
        'mentoring' => false,
        'aufgabe_freitext' => '',
        'db_modified' => null,
        'last_login' => null,
        'db_modified_user_id' => null,
        'resignation' => null,
        'membership_confirmation' => null,
    ];

    public const AUFGABEN = [
        'aufgabe_ma' => "Akademie-Organisation mit ca. 250 Teilnehmenden, z.B. Referent*innen-Suche, Material, Teilnehmendenbetreuung, Technik",
        'aufgabe_veranstaltungen' => "Organisation von Veranstaltungen mit ca. 50 Teilnehmenden, z.B. Material, Küchenorganisation, Finanzen, ... bei Camp, VAN-Conference, Unconference oder einem neuen Format",
        'aufgabe_seminar' => "Organisation von Wochenend-Seminaren mit ca. 20 Teilnehmenden zu einem Schwerpunktthema. Von maximaler Wissensvermittlung bis zu Praxisseminar ist alles möglich.",
        'aufgabe_lokal' => "Organisation von lokalen Treffen in größeren Städten",
        'aufgabe_vortrag' => "Anbieten von Workshops und Vorträgen z.B. auf Wochenend-Seminaren oder der Akademie",
        'aufgabe_it' => "IT-Team, d.h. Open-Source-Tools für Server, Mitgliederdatenbank, Veranstaltungssoftware, Webseite ... implementieren und hosten",
        'aufgabe_finanzen' => "Finanz-Team, d.h. Kalkulieren und Abrechnen von Veranstaltungen",
        'aufgabe_mitgliederbetreuung' => "Mitgliederbetreuung, d.h. Daten verwalten und/oder Personen ansprechen und zusammenbringen, z.B. für neue Veranstaltungen",
        'aufgabe_seminarteam' => "Seminarteam, d.h. Seminare anstoßen und den Überblick darüber behalten",
        'aufgabe_oeffentlichkeitsarbeit' => "Öffentlichkeitsarbeit, d.h. Webseiten-Inhalte, Social Media, Kooperationen mit Partnervereinen",
        'aufgabe_mentoringprogramm' => "Das Mentoring-Programm organisieren, d.h. Mentor*innen und Mentees zusammenbringen",
    ];

    public $hashedPassword = '';
    private $newPassword = '';
    private string $email = '';

    public string $profilePicturePath {
        get => User::PROFILE_PICUTRE_DIRECTORY . '/' . $this->get('profilbild');
    }

    public string $thumbnailPath {
        get => User::PROFILE_PICUTRE_DIRECTORY . '/thumbnail-' . $this->get('profilbild');
    }

    public string $profilePictureUrl {
        get => $this->get('profilbild') ? '/user/' . $this->get('username') . '/profile-picture?' . filemtime($this->profilePicturePath) : '';
    }

    public string $thumbnailUrl {
        get => $this->get('profilbild') ? '/user/' . $this->get('username') . '/profile-picture?size=thumbnail&' . filemtime($this->thumbnailPath) : '';
    }

    public string $fullName {
        get => $this->get('fullName');
    }

    public string $initials {
        get => preg_replace('/[^A-Z]/', '', strtoupper(preg_replace('/\B\w|\s+/u', '', $this->fullName)));
    }

    public static function getAllKeys(): array
    {
        return [...array_keys(User::felder), ...array_keys(User::AUFGABEN)];
    }

    public static function getDefaults(): array
    {
        return [...self::felder, ...array_fill_keys(array_keys(self::AUFGABEN), false)];
    }

    public function __construct(
        private Ldap $ldap,
        private UserRepository $userRepository,
        string $username = '',
        string $password = '',
        string $email = '',
    )
    {
        if (!$username && !$password && !$email) {
            // data will be filled in by UserRepository
            return;
        }

        $this->data = self::getDefaults();

        $this->setUsername($username);
        $this->setPassword($password);
        $this->setEmail($email);

        // TODO: Aufnahmedatum wird gespeichert mit Uhrzeit 0:00 Uhr UTC.
        //       Falls der Antrag kurz nach Mitternacht in Deutschland ausgefüllt wird, liegt das Aufnahmedatum daher um 1 Tag daneben
        $this->setData('aufnahmedatum', 'now');
        $this->setData('db_modified', 'now');
    }

    /**
     * Liest eine Eigenschaft
     *
     * @param string $feld
     * @throws \LogicException wenn die ID erfragt wird, obwohl sie noch nicht existiert.
     * @throws \OutOfRangeException wenn die Eigenschaft unbekannt ist
     */
    public function get($feld)
    {
        switch ($feld) {
        case 'id':
            return (int) $this->data['id'];
        case 'fullName':
            return implode(' ', array_filter([$this->data['vorname'], $this->data['nachname']])) ?? ('#' . $this->data['id']);
        case 'email':
            return $this->email;
        case 'hashedPassword':
            return $this->hashedPassword;
        case 'profilUrl':
            return '/user/' . $this->data['username'];
        case 'bearbeitenUrl':
            return '/user/' . $this->data['username'] . '/edit';
        case 'dateOfJoining':
            if ($this->get('membership_confirmation')) {
                return $this->get('membership_confirmation');
            }
            if ($this->get('aufnahmedatum') && $this->get('aufnahmedatum') > new \DateTime('2018-10-05')) {
                return $this->get('aufnahmedatum');
            }
            return null;
        default:
            if (in_array($feld, array_keys($this->data), true)) { // nicht über isset(), da dann Einträge mit Wert null nicht gefunden werden
                return $this->data[$feld];
            } else {
                throw new \OutOfRangeException('user property unknown: ' . $feld, 1493682787);
            }
        }
    }

    /**
     * typsicheres Setzen der Daten.
     *
     * @param bool $strictTypes Datentypen überprüfen. Bei false wird konvertiert.
     * @throws \TypeError, wenn $checkType === true ist und der Datentype nicht stimmt
     * @throws \OutOfRangeException wenn die Eigenschaft unbekannt ist
     */
    public function setData(string $key, mixed $value, $strictTypes = true): void
    {
        if (!in_array($key,self::getAllKeys(), true)) {
            throw new \OutOfRangeException("user property unknown: $key", 1493682897);
        }

        $defaultType = gettype(self::getDefaults()[$key]);
        if (in_array($key, array_keys(self::AUFGABEN), true)) {
            $defaultType = 'boolean';
        }
        switch ($key) {
            case 'id':
            case 'db_modified_user_id':
                $defaultType = 'integer';
        }

        $type = gettype($value);

        if ($type === 'NULL') {
            if ($defaultType !== 'NULL' && !in_array($key, ['db_modified_user_id'])) {
                throw new \TypeError('Value for ' . $key . ' may not be null.', 1494774389);
            } else {
                $this->data[$key] = null;
            }
            return;
        }

        if ($defaultType !== 'NULL' && $strictTypes && $defaultType !== $type) {
            throw new \TypeError("Value for $key is expected to be $defaultType, $type given.", 1494774567);
        }

        switch ($key) {
            case 'geburtstag':
            case 'aufnahmedatum':
            case 'db_modified':
            case 'last_login':
            case 'resignation':
            case 'membership_confirmation':
                $this->data[$key] = $this->makeDateTime($value);
                return;
            default:
                if ($defaultType === 'integer') {
                    $this->data[$key] = (int)$value;
                } elseif ($defaultType === 'string') {
                    $this->data[$key] = (string)$value;
                } elseif ($defaultType === 'boolean') {
                    $this->data[$key] = (bool)$value;
                } elseif ($defaultType === 'double') {
                    $this->data[$key] = (float)$value;
                } else {
                    throw new \TypeError("Invalid data type for $key: $type.", 1494775686);
                }
                return;
        }
    }

    /**
     * Ändert eine Eigenschaft, sofern sie nicht schreibgeschützt ist
     *
     * @param string $feld
     * @param mixed $wert
     * @throws \LogicException wenn versucht wird, eine schreibgeschützte Eigenschaft zu ändern
     * @throws \OutOfRangeException wenn die Eigenschaft unbekannt ist
     */
    public function set(string $feld, $wert)
    {
        switch ($feld) {
        case 'id':
        case 'username':
            throw new \LogicException("Eigenschaft $feld ist schreibgeschützt", 1493682836);
        case 'email':
            throw new \LogicException("Verwende setEmail(), um den Wert zu ändern.", 1494002758);
            break;
        case 'password':
            throw new \LogicException("Verwende setPassword(), um den Wert zu ändern.", 1755302008);
            break;
        default:
            $this->setData($feld,  $wert);
            break;
        }
        return true;
    }

    /**
     * @throws \UnexpectedValueException if username is already used by another user
     */
    private function setUsername(string $username)
    {
        if ($this->get('username') === 'username') {
            return;
        }
        if (!$this->userRepository->isUsernameAvailable($username)) {
            throw new \UnexpectedValueException('username already used', 1614368197);
        }
        $this->setData('username', $username);
    }

    /**
     * Setzt die E-Mail-Adresse.
     *
     * @param string $email
     * @throws \RuntimeException falls schon ein anderes User diese Adresse verwendet.
     * @return void
     */
    public function setEmail(string $email): void
    {
        $id = $this->userRepository->getIdByEmail($email);

        if ($id !== null && $id !== $this->get('id')) {
            throw new \RuntimeException('Doppelte Verwendung der E-Mail-Adresse ' . $email, 1494003025);
        }

        $this->email = $email;
    }

    public function setPassword(string $newPassword): void
    {
        $this->newPassword = $newPassword;
    }

    public function getPassword(): string
    {
        return $this->newPassword;
    }

    public function hasPasswordChanged(): bool
    {
        return $this->newPassword !== null;
    }

    /**
     * Erstellt ein DateTimeInterface-Objekt (oder null)
     *
     * @param null|string|int|DateTimeInterface $dateTime string (für strtotime), int (Timestamp) oder DateTimeInterface
     * @throws \TypeError wenn $dateTime einen nicht unterstützten Datentyp hat
     */
    private function makeDateTime(null|string|int|DateTimeInterface $dateTime): ?DateTimeInterface
    {
        if ($dateTime === null || $dateTime === '1970-01-01 00:00:00' || $dateTime === '0000-00-00' || $dateTime === '0000-00-00 00:00:00') {
            return null;
        } elseif (is_int($dateTime)) {
            if ($dateTime === 0) {
                return null;
            }
            return new DateTimeImmutable('@' . $dateTime);
        } elseif (is_string($dateTime)) {
            if ($dateTime === '') {
                return null;
            }
            return new DateTimeImmutable($dateTime);
        } elseif ($dateTime instanceof \DateTimeInterface) {
            return $dateTime;
        } else {
            throw new \TypeError("Value is expected to be DateTime, null, string or integer. " . gettype($dateTime) . " given.", 1494775564);
        }
    }

    public function isMemberOfGroup(string $groupName): bool
    {
        return $this->ldap->isUserMemberOfGroup($this->get('username'), $groupName);
    }

    public function hasRole(string $roleName): bool
    {
        if ($roleName === 'user') {
            return true;
        } elseif ($roleName === 'mvread' && $this->hasRole('mvedit')) {
            return true;
        } elseif ($roleName === 'mvedit' && $this->hasRole('rechte')) {
            return true;
        } elseif ($roleName === 'groupadmin' && $this->hasRole('rechte')) {
            return true;
        } else {
            return $this->isMemberOfGroup($roleName);
        }
    }

    public function getGroups(): array
    {
        return $this->ldap->getGroupsByUsername($this->get('username'));
    }

    public function deleteProfilePicture(): void
    {
        if ($this->get('profilbild') && is_file(User::PROFILE_PICUTRE_DIRECTORY . '/' . $this->get('profilbild'))) {
            unlink(User::PROFILE_PICUTRE_DIRECTORY . '/' . $this->get('profilbild'));
            unlink(User::PROFILE_PICUTRE_DIRECTORY . '/thumbnail-' . $this->get('profilbild'));
        }
        $this->set('profilbild', '');
    }

    public function deleteResources(): void
    {
        $this->deleteProfilePicture();
        $this->ldapEntry = null;
        $this->deleted = true;
    }

    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    public function checkPassword(string $password): bool
    {
        if (!$password) {
            return false;
        }

        return $this->ldap->checkPassword($this->get('username'), $password);
    }

    /**
     * check if a username has a valid format
     * the username has to start with a letter and may only contain letters, numbers and the symbols '.' '_' '-'
     * @param $username string to check
     */
    public static function isUsernameAllowed(string $username): bool
    {
        return preg_match('/^[a-z][a-z0-9\-_.]*$/i', $username) && !in_array(strtolower($username), [
            // reserved usernames ('_' and '_self' are already excluded by the pattern above)
            'admin', 'self', 'system', 'user', 'username',
        ]);
    }
}
