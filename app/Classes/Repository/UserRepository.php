<?php
namespace App\Repository;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

use App\Model\User;
use App\Service\CurrentUser;
use App\Service\Ldap;
use App\Service\Db;

/**
 * Repräsentiert ein User
 */
class UserRepository extends Repository
{
    /**
     * Lädt einen User aus der Datenbank und gibt ein User-Objekt zurück (oder null)
     */
    public function findOneById(int $id): ?User
    {
        $user = new User();

        $data = Db::getInstance()->query('SELECT '. implode(',', array_keys(User::felder)) . ' FROM mitglieder WHERE id=:id', ['id' => $id])->getRow();
        if (!$data) {
            return null;
        }

        $user->hashedPassword = $data['password'];

        $user->ldapEntry = Ldap::getInstance()->getEntryByUsername($data['username']);

        if ($user->ldapEntry) {
            $data['vorname'] = $user->ldapEntry->getAttribute('givenName')[0];
            $data['nachname'] = $user->ldapEntry->getAttribute('sn')[0];
            $data['email'] = $user->ldapEntry->getAttribute('mail')[0];
            $ldapPassword = $user->ldapEntry->getAttribute('userPassword')[0];
            if (substr($ldapPassword, 0, strlen('{CRYPT}!')) !== '{CRYPT}!') { // starts with "{CRYPT}!" => no LDAP login
                $user->hashedPassword = $ldapPassword;
            }
        }

        // typsicheres Setzen der Daten
        foreach ($data as $key => $value) {
            $user->setData($key, $value, false);
        }

        return $user;
    }

    /**
     * Lädt ein User zu einer gegebenen E-Mail-Adresse
     */
    public function findOneByEmail(string $email): ?User
    {
        $entry = Ldap::getInstance()->getEntryByEmail($email);
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
        if ($username === '_') {
            return CurrentUser::getInstance()->getWrappedUser();
        }
        $entry = Ldap::getInstance()->getEntryByUsername($username);
        if (!$entry) {
            return null;
        }
        $id = intval($entry->getAttribute('employeeNumber')[0]);
        return $this->findOneById($id);
    }

    public function isUsernameAvailable(string $username): bool
    {
        $existingId = $this->getIdByUsername($username);
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
     * Gibt eine User-ID zu einer E-Mail-Adresse zurück.
     * Durchsucht *nur* die aktuellen Adressen, nicht die noch zu setzenden.
     */
    public function getIdByEmail(string $email): ?int
    {
        $entry = Ldap::getInstance()->getEntryByEmail($email);
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
        $id = Db::getInstance()->query('SELECT id FROM mitglieder WHERE username=:username', ['username' => $username])->get();
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
        Ldap::getInstance()->deleteUser($user->get('username'));

        $db = Db::getInstance();
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

        // Query bauen
        $values = [];
        foreach (array_keys(User::felder) as $feld) {
            if (in_array($feld, ['id'], true)) {
                continue;
            }

            $value = $user->data[$feld];

            if ($feld === 'password' && $user->hasPasswordChanged()) {
                $ldapData['password'] = $value;
                $value = 'in ldap';
            }

            $values[$feld] = $value;
        }

        $setQuery = implode(', ', array_map(function($i) {
            return "$i = :$i";
        }, array_keys($values)));

        // neuen Benutzer anlegen
        if ($user->data['id'] === null) {
            $id = (int) Db::getInstance()->query("INSERT INTO mitglieder SET $setQuery", $values)->getInsertId();
            $user->setData('id', $id);

            $ldapData['id'] = $user->get('id');
            $user->ldapEntry = Ldap::getInstance()->addUser($user->get('username'), $ldapData);
        } else {
            $values['id'] = (int)$user->get('id');
            Db::getInstance()->query("UPDATE mitglieder SET $setQuery WHERE id=:id", $values);
            Ldap::getInstance()->modifyUser($user->get('username'), $ldapData);
        }
    }
}
