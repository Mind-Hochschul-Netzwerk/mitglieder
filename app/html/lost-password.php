<?php
declare(strict_types=1);
namespace MHN\Mitglieder;

/**
 * Passwortwechsel (bei Benutzeraktivierung oder Passwort vergessen)
 *
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

use MHN\Mitglieder\Auth;
use MHN\Mitglieder\Tpl;
use \Hengeb\Token\Token;

require_once '../lib/base.inc.php';

Tpl::set('htmlTitle', 'Passwort festlegen');
Tpl::set('title', 'Neues Passwort festlegen');
Tpl::set('navId', 'start');

ensure($_REQUEST['token'], ENSURE_SET);
ensure($_REQUEST['password'], ENSURE_SET);
ensure($_REQUEST['password2'], ENSURE_SET);

try {
    Token::decode($_REQUEST['token'], function ($data) use (&$m) {
        if (time() - $data[0] > 24*60*60) {
            throw new \Exception('token expired');
        }
        $m = Mitglied::lade($data[1], true);
        return $m->get('hashedPassword');
    }, getenv('TOKEN_KEY'));
} catch (\Exception $e) {
    die('Der Link ist abgelaufen oder ungültig.');
}

if ($_REQUEST['password']) {
    if ($_REQUEST['password'] !== $_REQUEST['password2']) {
        Tpl::set('wiederholung_falsch', true);
    } else {
        $m->set('password', $_REQUEST['password']);
        $m->save();
        Auth::login($m->get('id'));
        Tpl::pause();
        header('Location: /');
        exit;
    }
}

Tpl::set('token', $_REQUEST['token']);
Tpl::render('Auth/lost-password');
Tpl::submit();
