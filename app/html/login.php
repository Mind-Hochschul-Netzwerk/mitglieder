<?php
declare(strict_types=1);
namespace MHN\Mitglieder;

/**
 * Login
 *
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

use MHN\Mitglieder\Auth;
use MHN\Mitglieder\DB;
use MHN\Mitglieder\Mitglied;
use MHN\Mitglieder\Tpl;
use MHN\Mitglieder\Service\Ldap;
use MHN\Mitglieder\Service\Token;

require_once '../lib/base.inc.php';

Tpl::set('htmlTitle', 'Login');
Tpl::set('navId', 'login');

ensure($_REQUEST['id'], ENSURE_STRING);
ensure($_REQUEST['a'], ENSURE_STRING);

if (!$_REQUEST['id'] && !empty($_REQUEST['password'])) {
    Tpl::set('error_username_leer', true);
}

if ($_REQUEST['id']) {
    $id = DB::query('SELECT id FROM mitglieder WHERE id=%d OR username="%s" OR email="%s"', (int)$_REQUEST['id'], $_REQUEST['id'], $_REQUEST['id'])->get();

    // keinen Hinweis geben, ob die ID gefunden wurde!

    // Passwort vergessen?
    if (isset($_REQUEST['passwort_vergessen'])) {
        if ($id !== null) {
            $m = Mitglied::lade((int)$id, true);
            $token = Token::encode([
                time(),
                $m->get('id')
            ], $m->get('hashedPassword'));
            // E-Mail mit dem Zugangslink Passwort schicken (nur an die alte Adresse)
            try {
                $m->sendEmail('Neues Passwort',
    'Hallo ' . $m->get('fullName') . ",

    Du hast angegeben, dass du dein Passwort für deinen Zugang zum MinD-Hochschul-Netzwerk
    vergessen hast. Unter dem folgenden Link kannst du ein neues Passwort vergeben:

    " . Config::rootURL . "reset-password.php?token=$token

    Falls du diese E-Mail nicht selbst angefordert hast, kannst du sie ignorieren.
    ", [], 'email');
            } catch (\RuntimeException $e) {
                die("Fehler beim Versenden der E-Mail.");
            }
        }

        Tpl::set('lost_password', true);
    // Login
    } elseif (isset($_REQUEST['password'])) {
        if ($id !== null) {
            if (Auth::checkPassword($_REQUEST['password'], (int)$id)) {
                $u = Auth::logIn((int)$id);

                // zur Startseite. Von dort aus wird ggf. auf aktivieren.php weiter geleitet (Auth::intern()).
                Tpl::pause();

                header('Location: /');
                exit;
            }
        }
        Tpl::set('error_passwort_falsch', true);
    }
}

Tpl::sendHead();
Tpl::render('Auth/login');

Tpl::submit();
