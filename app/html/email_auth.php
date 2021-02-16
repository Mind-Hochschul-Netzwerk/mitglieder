<?php
declare(strict_types=1);
namespace MHN\Mitglieder;

/**
 * E-Mail-Token verifizieren
 *
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

use MHN\Mitglieder\Auth;
use MHN\Mitglieder\Mitglied;
use MHN\Mitglieder\Tpl;
use MHN\Mitglieder\Service\Token;

require_once '../lib/base.inc.php';

Tpl::set('htmlTitle', 'E-Mail-Verifikation');
Tpl::set('title', 'E-Mail-Verifikation');
Tpl::set('navId', 'bearbeiten');

ensure($_REQUEST['token'], ENSURE_SET);

try {
    Token::decode($_REQUEST['token'], function ($data) use (&$m, &$email) {
        if (time() - $data[0] > 24*60*60) {
            throw new \Exception('token expired');
        }
        $email = $data[2];
        $m = Mitglied::lade($data[1], true);
        return $m->get('email');
    }, getenv('TOKEN_KEY'));
} catch (\Exception $e) {
    die('Der Link ist abgelaufen oder ungültig.');
}

Auth::login($m->get('id'));

$oldMail = $m->get('email');

try {
    $m->setEmail($email);
} catch (\Exception $e) {
    die('Diese E-Mail-Adresse ist bereits bei einem anderen Mitglied eingetragen.');
}
$m->save();

try {
    Tpl::set('fullName', $m->get('fullName'));
    Tpl::set('email', $email);
    $text = Tpl::render('mails/email-changed', false);
    $m->sendEmail('E-Mail-Änderung', $text, [], $oldMail);
} catch (\RuntimeException $e) {
    // the mail to the old address could not be sent
    // but do not inform the user because the mail is only important if she/he is not the one who would have received it
}

echo "Deine E-Mail-Adresse wurde erfolgreich zu $email geändert.";

Tpl::submit();
