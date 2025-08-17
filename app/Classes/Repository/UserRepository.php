<?php
namespace App\Repository;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

use App\Model\User;
use App\Service\Ldap;
use Hengeb\Db\Db;
use RuntimeException;

/**
 * Repräsentiert ein User
 */
class UserRepository
{
    public function __construct(
        private Ldap $ldap,
        private Db $db,
    )
    {
    }

    /**
     * Lädt einen User aus der Datenbank und gibt ein User-Objekt zurück (oder null)
     */
    public function findOneById(int $id): ?User
    {
        $user = new User(ldap: $this->ldap, userRepository: $this);

        $data = $this->db->query('SELECT '. implode(',', array_keys(User::felder)) . ' FROM mitglieder WHERE id=:id', ['id' => $id])->getRow();
        if (!$data) {
            return null;
        }

        $user->ldapEntry = $this->ldap->getEntryByUsername($data['username']);

        if (!$user->ldapEntry) {
            throw new RuntimeException("user with ID $id does not exist in LDAP", 1754852333);
        }

        $data['vorname'] = $user->ldapEntry->getAttribute('givenName')[0];
        $data['nachname'] = $user->ldapEntry->getAttribute('sn')[0];

        // typsicheres Setzen der Daten
        foreach ($data as $key => $value) {
            $user->setData($key, $value, false);
        }

        $user->hashedPassword = $user->ldapEntry->getAttribute('userPassword')[0];
        $user->setEmail($user->ldapEntry->getAttribute('mail')[0]);

        return $user;
    }

    /**
     * Lädt ein User zu einer gegebenen E-Mail-Adresse
     */
    public function findOneByEmail(string $email): ?User
    {
        $entry = $this->ldap->getEntryByEmail($email);
        if (!$entry) {
            return null;
        }
        $id = intval($entry->getAttribute('employeeNumber')[0]);
        return $this->findOneById($id);
    }

    /**
     * Lädt ein User zu einer gegebenen E-Mail-Adresse
     */
    public function findOneByUsername(string $username): ?User
    {
        $entry = $this->ldap->getEntryByUsername($username);
        if (!$entry) {
            return null;
        }
        $id = intval($entry->getAttribute('employeeNumber')[0]);
        return $this->findOneById($id);
    }

    public function isUsernameAvailable(string $username): bool
    {
        if (!User::isUsernameAllowed($username)) {
            return false;
        }
        $existingId = $this->getIdByUsername($username);
        if ($existingId !== null) {
            return false;
        }
        $id = $this->db->query('SELECT id FROM deleted_usernames WHERE username = :username', ['username' => $username])->get();
        if ($id) {
            return false;
        }
        return true;
    }

    /**
     * Gibt eine User-ID zu einer E-Mail-Adresse zurück.
     * Durchsucht *nur* die aktuellen Adressen, nicht die noch zu setzenden.
     */
    public function getIdByEmail(string $email): ?int
    {
        $entry = $this->ldap->getEntryByEmail($email);
        if (!$entry) {
            return null;
        }
        return intval($entry->getAttribute('employeeNumber')[0]);
    }

    /**
     * Gibt eine User-ID zu einem Benutzernamen zurück.
     */
    public function getIdByUsername(string $username): ?int
    {
        $id = $this->db->query('SELECT id FROM mitglieder WHERE username=:username', ['username' => $username])->get();
        if ($id === null) {
            return null;
        }
        return intval($id);
    }

    /**
     * Remove user data
     *
     * @throws \RuntimeException if the user is a privileged user (cannot be deleted) or if there is a problem with sending mails
     * @return void
     */
    public function delete(User $user): void
    {
        if ($user->isMemberOfGroup('rechte')) {
            throw new \RuntimeException('Ein Benutzer mit den Rechten zur Rechteverwaltung darf nicht gelöscht werden.', 1637336416);
        }

        $user->deleteResources();

        // delete LDAP entry (will also delete memberships in groups)
        $this->ldap->deleteUser($user->get('username'));

        $db = $this->db;
        $db->query('INSERT INTO deleted_usernames SET id = :id, username = :username', [
            'id' => $user->get('id'),
            'username' => $user->get('username')
        ]);
        $db->query('DELETE FROM mitglieder WHERE id = :id', ['id' => $user->get('id')]);
    }

    /**
     * Speichert den Benutzer in der Datenbank
     *
     * @return void
     */
    public function save(User $user): void
    {
        if ($user->isDeleted()) {
            throw new \LogicException('user deleted', 1612567531);
        }

        $ldapData = [
            'firstname' => $user->get('vorname'),
            'lastname' => $user->get('nachname'),
            'email' => $user->get('email'),
        ];
        if ($user->hasPasswordChanged()) {
            $ldapData['password'] = $user->getPassword();
        }

        // Query bauen
        $values = [];
        foreach (array_keys(User::felder) as $feld) {
            if (in_array($feld, ['id'], true)) {
                continue;
            }

            $value = $user->data[$feld];
            $values[$feld] = $value;
        }

        $setQuery = implode(', ', array_map(function($i) {
            return "$i = :$i";
        }, array_keys($values)));

        // neuen Benutzer anlegen
        if ($user->data['id'] === null) {
            $id = (int) $this->db->query("INSERT INTO mitglieder SET $setQuery", $values)->getInsertId();
            $user->setData('id', $id);

            $ldapData['id'] = $user->get('id');
            $user->ldapEntry = $this->ldap->addUser($user->get('username'), $ldapData);
        } else {
            $values['id'] = (int)$user->get('id');
            $this->db->query("UPDATE mitglieder SET $setQuery WHERE id=:id", $values);
            $this->ldap->modifyUser($user->get('username'), $ldapData);
        }
    }
}
