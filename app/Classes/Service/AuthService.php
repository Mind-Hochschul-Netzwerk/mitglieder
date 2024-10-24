<?php
declare(strict_types=1);
namespace App\Service;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

use App\Repository\UserRepository;

AuthService::init();

/**
 * Rechteverwaltung, Login und Logout
 *
 * Namen der Rechte:
 * - rechte      (darf alles, was mvedit darf und zusätzlich auch Rechte vergeben)
 * - mvedit      (Mitglieder-Admins: dürfen alles sehen und bearbeiten und Mitglieder löschen)
 * - mvread      (dürfen gesperrte Felder sehen, aber nicht bearbeiten)
 */
class AuthService
{
    /**
     * @return void
     */
    public static function init()
    {
        if (!isset($_SESSION['uid'])) {
            $_SESSION['uid'] = null;
        }
    }

    public static function istEingeloggt() : bool
    {
        return $_SESSION['uid'] !== null;
    }

    /**
     * Loggt den User $id ein
     * @param int $id
     */
    public static function logIn(int $id)
    {
        $user = UserRepository::getInstance()->findOneById($id);

        assert($user !== null);

        // neue Session-ID zuweisen, um Session-Hijacking-Gefahr zu minimieren
        Session::getInstance()->regenerateId();

        $_SESSION['uid'] = $id;

        $user->set('last_login', 'now');

        UserRepository::getInstance()->save($user);

        return $user;
    }

    /**
     * Loggt den User aus
     */
    public static function logOut(): void
    {
        $_SESSION = [];
        self::init();
    }

    /**
     * Prüft, ob der User oder ein anderes Mitglied ein Recht hat
     * @param string $recht
     * @param int|null $id User-ID oder null für den Session-Benutzer
     */
    public static function hatRecht(string $recht, $id = null): bool
    {
        if ($id === null) {
            $id = self::getUID();
        }

        // nicht eingloggte Benutzer haben keine Rechte
        if (!self::istEingeloggt()) {
            return false;
        }

        // schreiben impliziert lesen
        if (in_array($recht, ['rechte', 'mvread'], true) && self::hatRecht('mvedit', $id)) {
            return true;
        }

        $m = UserRepository::getInstance()->findOneById($id);
        return $m ? $m->isMemberOfGroup($recht) : false;
    }

    /**
     * die ID des Users
     */
    public static function getUID() : int
    {
        return (int)$_SESSION['uid'];
    }

    /**
     * prüft, ob der User eine bestimmte ID hat
     * @param int $id
     */
    public static function ist(int $id) : bool
    {
        return self::getUID() === $id;
    }

    /**
     * überprüft ein Passwort
     * Gibt zurück, ob es sich bei dem Passwort um das normale Passwort oder um ein Einmalpasswort handelt
     * @param string $password im Klartext
     */
    public static function checkPassword(string $password, $id = null): bool
    {
        if (!$password) {
            return false;
        }

        if ($id === null) {
            $id = self::getUID();
        }

        $u = UserRepository::getInstance()->findOneById($id);

        $username = $u->get('username');
        $ldapCheck = Ldap::getInstance()->checkPassword($username, $password);
        if ($ldapCheck) {
            return true;
        }

        $hash = Db::getInstance()->query('SELECT password FROM mitglieder WHERE id=:id', ['id' => $id])->get();
        if (PasswordService::check((string)$hash, $password, $id)) {
            // store password in ldap
            $u->set('password', $password);
            UserRepository::getInstance()->save($u);
            return true;
        }
        return false;
    }
}
