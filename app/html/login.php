<?php
declare(strict_types=1);
namespace App;

/**
 * Login
 *
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

use \App\Auth;
use \App\Mitglied;
use \App\Tpl;
use \App\Service\Db;
use \App\Service\Ldap;
use \Hengeb\Token\Token;

require_once '../lib/base.inc.php';

Tpl::set('htmlTitle', 'Login');
Tpl::set('navId', 'login');

ensure($_REQUEST['id'], ENSURE_STRING);
ensure($_REQUEST['a'], ENSURE_STRING);

if (!$_REQUEST['id'] && !empty($_REQUEST['password'])) {
    Tpl::set('error_username_leer', true);
}

if ($_REQUEST['id']) {
    $id = DB::getInstance()->query('SELECT id FROM mitglieder WHERE id=:id OR username=:username OR email=:email', [
        'id' => (int)$_REQUEST['id'],
        'username' => $_REQUEST['id'],
        'email' => $_REQUEST['id'],
    ])->get();

    // keinen Hinweis geben, ob die ID gefunden wurde!

    // Passwort vergessen?
    if (isset($_REQUEST['passwort_vergessen'])) {
        if ($id !== null) {
            $m = Mitglied::lade((int)$id, true);
            $token = Token::encode([
                time(),
                $m->get('id')
            ], $m->get('hashedPassword'), getenv('TOKEN_KEY'));

            Tpl::set('fullName', $m->get('fullName'), false);
            Tpl::set('url', 'https://mitglieder.' . getenv('DOMAINNAME') . '/lost-password.php?token=' . $token);
            $text = Tpl::render('mails/lost-password', false);

            try {
                $m->sendEmail('Passwort vergessen', $text);
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

                Tpl::pause();

                $uri = empty($_GET['uri']) ? '/' : ((string) $_GET['uri']);
                $uri = preg_replace('/[\r\n]/', '', $uri); // prohibit header insertion
                if ($uri[0] !== '/') {
                    $uri = '/';
                }
                header('Location: ' . $uri);
                exit;
            }
        }
        Tpl::set('error_passwort_falsch', true);
    }
}

Tpl::sendHead();
Tpl::render('Auth/login');

Tpl::submit();
