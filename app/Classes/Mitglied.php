<?php
namespace MHN\Mitglieder;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

use DateTime;
use MHN\Mitglieder\Config;
use MHN\Mitglieder\Service\EmailService;
use MHN\Mitglieder\Service\Ldap;
use MHN\Mitglieder\Service\Db;

/**
 * Repräsentiert ein Mitglied
 */
class Mitglied
{
    private $data = null;

    private $ldapEntry = null;
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

    private $hashedPassword = '';

    /**
     * privater Konstruktor, um das direkte Erstellen von Objekten zu verhindern
     * Benutze die Funktion Mitglied::lade($uid)
     * @param int $uid
     */
    private function __construct(int $uid)
    {
        if ($uid === 0) { // new member
            return;
        }

        $data = Db::getInstance()->query('SELECT '. implode(',', array_keys(self::felder)) . ' FROM mitglieder WHERE id=:id', ['id' => $uid])->getRow();
        if (!$data) {
            return;
        }

        $this->hashedPassword = $data['password'];

        $this->ldapEntry = Ldap::getInstance()->getEntryByUsername($data['username']);
        if ($this->ldapEntry) {
            $data['vorname'] = $this->ldapEntry->getAttribute('givenName')[0];
            $data['nachname'] = $this->ldapEntry->getAttribute('sn')[0];
            $data['email'] = $this->ldapEntry->getAttribute('mail')[0];
            $ldapPassword = $this->ldapEntry->getAttribute('userPassword')[0];
            if (substr($ldapPassword, 0, strlen('{CRYPT}!')) !== '{CRYPT}!') { // starts with "{CRYPT}!" => no LDAP login
                $this->hashedPassword = $ldapPassword;
            }
        }

        // typsicheres Setzen der Daten
        foreach ($data as $key => $value) {
            $this->setData($key, $value, false);
        }
    }

    /**
     * Erzeugt ein Mitglied-Objekt für ein neues Mitglied
     */
    public static function neu(string $username, string $password, string $email): Mitglied
    {
        $m = new self(0, false);
        $m->data = self::felder;

        $m->setUsername($username);
        $m->set('password', $password);
        $m->setEmail($email);

        $m->setData('aufnahmedatum', 'now');
        $m->setData('db_modified', 'now');

        return $m;
    }

    /**
     * Lädt ein Mitglied aus der Datenbank und gibt ein Mitglied-Objekt zurück (oder null)
     */
    public static function lade(int $uid): ?Mitglied
    {
        $m = new self($uid);

        if (!$m->data) {
            return null;
        }

        return $m;
    }

    /**
     * Lädt ein Mitglied zu einer gegebenen E-Mail-Adresse
     */
    public static function getOneByEmail(string $email): ?Mitglied
    {
        $entry = Ldap::getInstance()->getEntryByEmail($email);
        if (!$entry) {
            return null;
        }
        $uid = $entry->getAttribute('uid')[0];
        return self::lade($uid, true);
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
            return 'profil.php?id=' . $this->data['id'];
        case 'bearbeitenUrl':
            return 'bearbeiten.php?id=' . $this->data['id'];
        case 'profilLink':
            return '<a href="' . $this->get('profilUrl') . '">' . htmlspecialchars($this->get('fullName')) . '</a>';
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
    private function setData(string $key, $value, $strictTypes = true)
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
                } elseif ($defaultType === 'float') {
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

    public function isUsernameAvailable(string $username): bool
    {
        $existingId = self::getIdByUsername($username);
        if ($existingId !== null) {
            return false;
        }
        $id = Db::getInstance()->query('SELECT id FROM deleted_usernames WHERE username = :username', ['username' => $username])->get();
        if ($id) {
            return false;
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
        if (!$this->isUsernameAvailable($username)) {
            throw new \UnexpectedValueException('username already used', 1614368197);
        }
        $this->setData('username', $username);
    }

    /**
     * Setzt die E-Mail-Adresse.
     *
     * @param string $email
     * @throws \RuntimeException falls schon ein anderes Mitglied diese Adresse verwendet.
     * @return void
     */
    public function setEmail(string $email): void
    {
        $id = self::getIdByEmail($email);
        if ($id !== null && $id !== $this->get('id')) {
            throw new \RuntimeException('Doppelte Verwendung der E-Mail-Adresse ' . $email, 1494003025);
        }

        $this->setData('email', $email);
    }

    /**
     * Erstellt ein DateTime-Objekt (oder null)
     *
     * @var null|string|int|DateTime $dateTime string (für strtotime), int (Timestamp) oder DateTime
     * @throws \TypeError wenn $dateTime einen nicht unterstützten Datentyp hat
     */
    private function makeDateTime($dateTime): ?\DateTime
    {
        $type = gettype($dateTime);
        if ($type === 'NULL') {
            return null;
        } elseif ($type === 'integer') {
            if ($dateTime === 0) {
                return null;
            }
            return new \DateTime('@' . $dateTime);
        } elseif ($type === 'string') {
            if ($dateTime === '') {
                return null;
            }
            return new \DateTime($dateTime);
        } elseif ($type === 'object' && get_class($dateTime) === 'DateTime') {
            return $dateTime;
        } else {
            throw new \TypeError("Value is expected to be DateTime, null, string or integer. $type given.", 1494775564);
        }
    }

    /**
     * Gibt eine User-ID zu einer E-Mail-Adresse zurück.
     * Durchsucht *nur* die aktuellen Adressen, nicht die noch zu setzenden.
     */
    public static function getIdByEmail(string $email): ?int
    {
        $entry = Ldap::getInstance()->getEntryByEmail($email);
        if (!$entry) {
            return null;
        }
        return (int)$entry->getAttribute('uid')[0];
    }

    /**
     * Gibt eine User-ID zu einem Benutzernamen zurück.
     */
    public static function getIdByUsername(string $username): ?int
    {
        $id = Db::getInstance()->query('SELECT id FROM mitglieder WHERE username=:username', ['username' => $username])->get();
        if ($id === null) {
            return null;
        }
        return (int)$id;
    }

    public function isMemberOfGroup(string $groupName): bool
    {
        return Ldap::getInstance()->isUserMemberOfGroup($this->get('username'), $groupName);
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

        $changed = false;
        $groupNames = array_map('strtolower', $groupNames);
        $oldGroupNames = array_map('strtolower', $this->getGroups());

        foreach(array_diff($oldGroupNames, $groupNames) as $groupName) {
            $ldap->removeUserFromGroup($username, $groupName);
            $changed = true;
        }

        foreach(array_diff($groupNames, $oldGroupNames) as $groupName) {
            $changed = true;
            $ldap->addUserToGroup($username, $groupName);
        }
    }

    /**
     * Remove user data
     *
     * @throws \RuntimeException if the user is a privileged user (cannot be deleted) or if there is a problem with sending mails
     * @return void
     */
    public function delete(string $quiet = 'not quiet'): void
    {
        if ($this->isMemberOfGroup('rechte')) {
            throw new \RuntimeException('Ein Benutzer mit den Rechten zur Rechteverwaltung darf nicht gelöscht werden.', 1637336416);
        }

        $admin = Mitglied::lade(Auth::getUID());

        if ($quiet === 'not quiet') {
            Tpl::set('adminName', $admin->get('fullName'));
            Tpl::set('adminId', $admin->get('id'));
            Tpl::set('adminUsername', $admin->get('username'));
            Tpl::set('deletedName', $this->get('fullName'));
            Tpl::set('deletedId', $this->get('id'));
            Tpl::set('deletedUsername', $this->get('username'));
            Tpl::set('deletedEmail', $this->get('email'));
            $mailText = Tpl::render('mails/MvEdit-Info-Mitglied-Geloescht', false);

            // Alle Mitglieder der Mitgliederbetreuung (mvedit) informieren
            $ids = Ldap::getInstance()->getIdsByGroup('mvedit');
            foreach ($ids as $id) {
                $user = Mitglied::lade($id, false);
                if ($user === null) {
                    continue;
                }
                try {
                    $user->sendEmail('Information über gelöschtes Mitglied', $mailText);
                } catch (\RuntimeException $e) {
                    throw $e;
                }
            }
        }

        // Profilbild-Datei löschen
        if ($this->get('profilbild') && is_file('profilbilder/' . $this->get('profilbild'))) {
            unlink('profilbilder/' . $this->get('profilbild'));
            unlink('profilbilder/thumbnail-' . $this->get('profilbild'));
        }

        // delete LDAP entry (will also delete memberships in groups)
        Ldap::getInstance()->deleteUser($this->get('username'));
        $this->ldapEntry = null;

        $db = Db::getInstance();
        $db->query('INSERT INTO deleted_usernames SET id = :id, username = :username', [
            'id' => $this->get('id'),
            'username' => $this->get('username')
        ]);
        $db->query('DELETE FROM mitglieder WHERE id = :id', ['id' => $this->get('id')]);

        $this->deleted = true;
    }

    /**
     * Speichert den Benutzer in der Datenbank
     *
     * @return void
     */
    public function save()
    {
        if ($this->deleted) {
            throw new \LogicException('user deleted', 1612567531);
        }

        $ldapData = [
            'firstname' => $this->get('vorname'),
            'lastname' => $this->get('nachname'),
            'email' => $this->get('email'),
        ];

        // Query bauen
        $values = [];
        foreach (array_keys(self::felder) as $feld) {
            if (in_array($feld, ['id'], true)) {
                continue;
            }

            $value = $this->data[$feld];

            if ($feld === 'password' && $this->passwordChanged) {
                $ldapData['password'] = $value;
                $value = 'in ldap';
            }

            $values[$feld] = $value;
        }

        $setQuery = implode(', ', array_map(function($i) {
            return "$i = :$i";
        }, array_keys($values)));

        // neuen Benutzer anlegen
        if ($this->data['id'] === null) {
            $id = (int) Db::getInstance()->query("INSERT INTO mitglieder SET $setQuery", $values)->getInsertId();
            $this->setData('id', $id);

            $ldapData['id'] = $this->get('id');
            $this->ldapEntry = Ldap::getInstance()->addUser($this->get('username'), $ldapData);
        } else {
            $values['id'] = (int)$this->get('id');
            Db::getInstance()->query("UPDATE mitglieder SET $setQuery WHERE id=:id", $values);
            Ldap::getInstance()->modifyUser($this->get('username'), $ldapData);
        }
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
}
