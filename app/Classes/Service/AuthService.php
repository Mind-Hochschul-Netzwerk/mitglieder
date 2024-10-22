<?php
declare(strict_types=1);
namespace App\Service;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

use App\Mitglied;

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
     * Loggt den User $uid ein
     * @param int $uid
     */
    public static function logIn(int $uid)
    {
        $u = Mitglied::lade($uid, true);

        assert($u !== null);

        // neue Session-ID zuweisen, um Session-Hijacking-Gefahr zu minimieren
        Session::getInstance()->regenerateId();

        $_SESSION['uid'] = $uid;

        $u->set('last_login', 'now');

        $u->save();

        return $u;
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
     * @param int|null $uid User-ID oder null für den Session-Benutzer
     */
    public static function hatRecht(string $recht, $uid = null): bool
    {
        if ($uid === null) {
            $uid = self::getUID();
        }

        // nicht eingloggte Benutzer haben keine Rechte
        if (!self::istEingeloggt()) {
            return false;
        }

        // schreiben impliziert lesen
        if (in_array($recht, ['rechte', 'mvread'], true) && self::hatRecht('mvedit', $uid)) {
            return true;
        }

        $m = Mitglied::lade($uid, true);
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
     * @param int $uid
     */
    public static function ist(int $uid) : bool
    {
        return self::getUID() === $uid;
    }

    /**
     * überprüft ein Passwort
     * Gibt zurück, ob es sich bei dem Passwort um das normale Passwort oder um ein Einmalpasswort handelt
     * @param string $password im Klartext
     */
    public static function checkPassword(string $password, $uid = null): bool
    {
        if (!$password) {
            return false;
        }

        if ($uid === null) {
            $uid = self::getUID();
        }

        $u = Mitglied::lade($uid, true);

        $username = $u->get('username');
        $ldapCheck = Ldap::getInstance()->checkPassword($username, $password);
        if ($ldapCheck) {
            return true;
        }

        $hash = Db::getInstance()->query('SELECT password FROM mitglieder WHERE id=:id', ['id' => $uid])->get();
        if (PasswordService::check((string)$hash, $password, $uid)) {
            // store password in ldap
            $u->set('password', $password);
            $u->save();
            return true;
        }
        return false;
    }
}
