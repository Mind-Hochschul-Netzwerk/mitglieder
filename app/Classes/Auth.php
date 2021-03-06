<?php
declare(strict_types=1);
namespace MHN\Mitglieder;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

use MHN\Mitglieder\Config;
use MHN\Mitglieder\Mitglied;
use MHN\Mitglieder\Service\Session;
use MHN\Mitglieder\Tpl;
use MHN\Mitglieder\Service\Ldap;

Auth::init();

/**
 * Rechteverwaltung, Login und Logout
 *
 * Namen der Rechte:
 * - rechte      (darf alles, was mvedit darf und zusätzlich auch Rechte vergeben)
 * - mvedit      (Mitglieder-Admins: dürfen alles sehen und bearbeiten und Mitglieder löschen)
 * - mvread      (dürfen gesperrte Felder sehen, aber nicht bearbeiten)
 */
class Auth
{
    /**
     * @return void
     */
    public static function init()
    {
        if (!isset($_SESSION['uid'])) {
            $_SESSION['uid'] = null;
            $_SESSION['aktiviert'] = false;
        }
    }

    public static function istEingeloggt() : bool
    {
        return $_SESSION['uid'] !== null;
    }

    public static function istAktiviert() : bool
    {
        return $_SESSION['aktiviert'];
    }

    /**
     * Prüft, ob ein Mitglied interne Seiten sehen darf und leitet sonst zu den entsprechenden Formularen
     * @param string $context
     * @return void
     */
    public static function intern(string $context = 'intern')
    {
        if (!self::istEingeloggt()) {
            Tpl::pause();
            header('Location: /login.php');
            exit;
        } elseif (!self::istAktiviert()) {
            if ($context === 'aktivieren') {
                return;
            }
            Tpl::pause();
            header('Location: /aktivieren.php');
            exit;
        }
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
        $_SESSION['aktiviert'] = ($u->get('aktiviert') === true && $u->get('username') !== '');

        $u->save();

        return $u;
    }

    /**
     * Loggt den User aus
     *
     * @param string $redirectUrl ist URL (beginnend mit /), auf die nach dem Logout weitergeleitet wird.
     * @return void
     * @throws \UnexpectedValueException, wenn $redirectUrl nicht mit / beginnt
     */
    public static function logOut(string $redirectUrl)
    {
        $_SESSION = [];
        self::init();

        if ($redirectUrl === '' || $redirectUrl{0} !== '/') {
            throw new \UnexpectedValueException('$redirectUrl has to start with /', 1494925574);
        }

        Tpl::pause();
        header('Location: ' . $redirectUrl);
        exit;
    }

    /**
     * Prüft, ob der User oder ein anderes Mitglied ein Recht hat
     * @param string $recht
     * @param int|null $uid User-ID oder null für den Session-Benutzer
     */
    public static function hatRecht(string $recht, $uid = null)
    {
        if ($uid === null) {
            $uid = self::getUID();
        }

        // nicht eingloggte Benutzer haben keine Rechte
        if (!self::istEingeloggt()) {
            return false;
        }

        // Rechtverwaltung impliziert alle Rechte
        if ($recht !== 'rechte' && self::hatRecht('rechte', $uid)) {
            return true;
        }

        // schreiben impliziert lesen
        if ($recht === 'mvread' && self::hatRecht('mvedit', $uid)) {
            return true;
        }

        return Mitglied::lade($uid, true)->hasRole($recht);
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

        $hash = DB::query('SELECT password FROM mitglieder WHERE id=%d', $uid)->get();
        if (Password::check((string)$hash, $password, $uid)) {
            // store password in ldap
            $u->set('password', $password);
            $u->save();
            return true;
        }
        return false;
    }
}
