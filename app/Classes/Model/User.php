<?php
namespace App\Model;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

use App\Repository\UserRepository;
use App\Service\EmailService;
use App\Service\Ldap;
use App\Service\Tpl;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Repräsentiert ein User
 */
class User extends Model
{
    protected static $repositoryClass = UserRepository::class;

    public $data = null;

    public $ldapEntry = null;
    private $passwordChanged = false;
    private $deleted = false;

    // Felder und Defaults
    const felder = [
        'id' => null, 'username' => '', 'vorname' => '', 'nachname' => '', 'password' => '', 'email' => '', 'sichtbarkeit_email' => false, 'titel' => '', 'geburtstag' => null, 'aufnahmedatum' => null, 'sichtbarkeit_geburtstag' => false, 'profilbild' => '', 'profilbild_x' => 0, 'profilbild_y' => 0, 'mensa_nr' => '', 'sichtbarkeit_mensa_nr' => false, 'strasse' => '', 'sichtbarkeit_strasse' => false, 'adresszusatz' => '', 'sichtbarkeit_adresszusatz' => false, 'plz' => '', 'ort' => '', 'sichtbarkeit_plz_ort' => false, 'land' => '', 'sichtbarkeit_land' => false, 'strasse2' => '', 'adresszusatz2' => '', 'plz2' => '', 'ort2' => '', 'land2' => '', 'telefon' => '', 'sichtbarkeit_telefon' => false, 'homepage' => '', 'sprachen' => '', 'hobbys' => '', 'interessen' => '',
        'beschaeftigung' => 'Sonstiges', 'sichtbarkeit_beschaeftigung' => false, 'studienort' => '', 'sichtbarkeit_studienort' => false, 'studienfach' => '', 'sichtbarkeit_studienfach' => false, 'unityp' => '', 'sichtbarkeit_unityp' => false, 'schwerpunkt' => '', 'sichtbarkeit_schwerpunkt' => false, 'nebenfach' => '', 'sichtbarkeit_nebenfach' => false, 'abschluss' => '', 'sichtbarkeit_abschluss' => false, 'zweitstudium' => '', 'sichtbarkeit_zweitstudium' => false, 'hochschulaktivitaeten' => '', 'sichtbarkeit_hochschulaktivitaeten' => false, 'stipendien' => '', 'sichtbarkeit_stipendien' => false, 'auslandsaufenthalte' => '', 'sichtbarkeit_auslandsaufenthalte' => false, 'praktika' => '', 'sichtbarkeit_praktika' => false, 'beruf' => '', 'sichtbarkeit_beruf' => false,
        'auskunft_studiengang' => false, 'auskunft_stipendien' => false, 'auskunft_auslandsaufenthalte' => false, 'auskunft_praktika' => false, 'auskunft_beruf' => false, 'mentoring' => false, 'aufgabe_ma' => false, 'aufgabe_orte' => false, 'aufgabe_vortrag' => false, 'aufgabe_koord' => false, 'aufgabe_graphisch' => false, 'aufgabe_computer' => false, 'aufgabe_texte_schreiben' => false, 'aufgabe_texte_lesen' => false, 'aufgabe_vermittlung' => false, 'aufgabe_ansprechpartner' => false, 'aufgabe_hilfe' => false, 'aufgabe_sonstiges' => false, 'aufgabe_sonstiges_beschreibung' => '',
        'db_modified' => null, 'last_login' => null,
        'db_modified_user_id' => null, 'kenntnisnahme_datenverarbeitung_aufnahme' => null, 'kenntnisnahme_datenverarbeitung_aufnahme_text' => '', 'einwilligung_datenverarbeitung_aufnahme' => null, 'einwilligung_datenverarbeitung_aufnahme_text' => '',
        'resignation' => null, 'membership_confirmation' => null,
    ];

    public $hashedPassword = '';

    public function __construct(string $username = '', string $password = '', string $email = '')
    {
        if (!$username && !$password && !$email) {
            // data will be filled in by UserRepository
            return;
        }

        $this->data = self::felder;

        $this->setUsername($username);
        $this->set('password', $password);
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
            return (int)$this->data['id'];
        case 'fullName':
            $vorname = $this->data['vorname'];
            $nachname = $this->data['nachname'];
            $fn = $vorname;
            if ($fn) {
                $fn .= ' ';
            }
            $fn .= $nachname;
            if (!$fn) {
                $fn = '#' . $this->data['id'];
            }
            return $fn;
        case 'hashedPassword':
            return $this->hashedPassword;
        case 'profilUrl':
            return '/user/' . $this->data['username'];
        case 'bearbeitenUrl':
            return '/user/' . $this->data['username'] . '/edit';
        case 'profilLink':
            return '<a href="' . $this->get('profilUrl') . '">' . Tpl::htmlEscape($this->get('fullName')) . '</a>';
        case 'dateOfJoining';
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
        if (!in_array($key, array_keys(self::felder), true)) {
            throw new \OutOfRangeException("user property unknown: $key", 1493682897);
        }

        $defaultType = gettype(self::felder[$key]);
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
            case 'kenntnisnahme_datenverarbeitung_aufnahme':
            case 'einwilligung_datenverarbeitung_aufnahme':
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
            $this->setData('password', $wert);
            $this->passwordChanged = true;
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
        if (!UserRepository::getInstance()->isUsernameAvailable($username)) {
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
        $id = UserRepository::getInstance()->getIdByEmail($email);

        if ($id !== null && $id !== $this->get('id')) {
            throw new \RuntimeException('Doppelte Verwendung der E-Mail-Adresse ' . $email, 1494003025);
        }

        $this->setData('email', $email);
    }

    /**
     * Erstellt ein DateTimeInterface-Objekt (oder null)
     *
     * @param null|string|int|DateTimeInterface $dateTime string (für strtotime), int (Timestamp) oder DateTimeInterface
     * @throws \TypeError wenn $dateTime einen nicht unterstützten Datentyp hat
     */
    private function makeDateTime(null|string|int|DateTimeInterface $dateTime): ?DateTimeInterface
    {
        if ($dateTime === null || $dateTime === '1970-01-01 00:00:00') {
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
        return Ldap::getInstance()->isUserMemberOfGroup($this->get('username'), $groupName);
    }

    public function hasRole(string $roleName): bool
    {
        if ($roleName === 'user') {
            return true;
        } elseif ($roleName === 'mvread' && $this->hasRole('mvedit')) {
            return true;
        } elseif ($roleName === 'mvedit' && $this->hasRole('rechte')) {
            return true;
        } else {
            return $this->isMemberOfGroup($roleName);
        }
    }

    public function getGroups(): array
    {
        return Ldap::getInstance()->getGroupsByUsername($this->get('username'));
    }

    /**
     * @throws \OutOfRangeException one of the new groups is invalid
     */
    public function setGroups(array $groupNames): void
    {
        // shortcuts
        $ldap = Ldap::getInstance();
        $username = $this->get('username');

        $groupNames = array_map('strtolower', $groupNames);
        $oldGroupNames = array_map('strtolower', $this->getGroups());

        foreach(array_diff($oldGroupNames, $groupNames) as $groupName) {
            $ldap->removeUserFromGroup($username, $groupName);
        }

        foreach(array_diff($groupNames, $oldGroupNames) as $groupName) {
            $ldap->addUserToGroup($username, $groupName);
        }
    }

    public function deleteResources(): void
    {
        // Profilbild-Datei löschen
        if ($this->get('profilbild') && is_file('profilbilder/' . $this->get('profilbild'))) {
            unlink('profilbilder/' . $this->get('profilbild'));
            unlink('profilbilder/thumbnail-' . $this->get('profilbild'));
        }

        $this->ldapEntry = null;
        $this->deleted = true;
    }

    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    public function hasPasswordChanged(): bool
    {
        return $this->passwordChanged;
    }

    /**
     * Sendet eine E-Mail
     * @param string $subject
     * @param string $body
     * @throws \RuntimeException wenn eine E-Mail nicht versandt werden konnte.
     */
    public function sendEmail($subject, $body)
    {
        if (!(EmailService::getInstance()->send($this->get('email'), $subject, $body))) {
            throw new \RuntimeException('Beim Versand der E-Mail an ' . $this->get('email') . ' (ID ' . $this->data['id'] . ') ist ein Fehler aufgetreten.', 1522422201);
        }
    }

    public function checkPassword(string $password): bool
    {
        if (!$password) {
            return false;
        }

        return Ldap::getInstance()->checkPassword($this->get('username'), $password);
    }

    /**
     * check if a username has a valid format
     * the username has to start with a letter and may only contain letters, numbers and the symbols '.' '_' '-'
     * @param $username string to check
     */
    public static function isUsernameFormatValid(string $username): bool
    {
        return preg_match('/^[a-z][a-z0-9\-_.]*$/i', $username);
    }
}
