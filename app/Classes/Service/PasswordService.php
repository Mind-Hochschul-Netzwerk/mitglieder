<?php
declare(strict_types=1);
namespace App\Service;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

/**
* Passwort-Hashing und Token-Generierung
*/
class PasswordService
{
    const CHARS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ01234567890';
    const ITERATIONS = 100000;

    public static function hash(string $password) : string
    {
        // $5$ = SHA256 (LDAP compatible)
        // salt length = 8
        return crypt($password, '$5$rounds=' . self::ITERATIONS . '$'. self::randomString(8) . '$');
    }

    /**
     * gibt einen zufälligen String zurück. Zeichensatz: a-z A-Z 0-9
     */
    public static function randomString(int $length) : string
    {
        $string = '';
        do {
            $string .= self::CHARS[rand(0, strlen(self::CHARS) - 1)];
        } while (strlen($string) < $length);
        return $string;
    }
}
