<?php
declare(strict_types=1);
namespace MHN\Mitglieder;

/**
 * Auflistung aller E-Mail-Adressen für die Wahlleitung
 *
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

use MHN\Mitglieder\Auth;
use MHN\Mitglieder\Tpl;
use MHN\Mitglieder\Service\Db;

require_once '../lib/base.inc.php';

Auth::intern();

Tpl::set('htmlTitle', 'Wahlleitung');
Tpl::set('title', 'Wahlleitung');

if (!Auth::hatRecht('wahlleitung')) {
    die('Fehlende Rechte.');
}

$emails = Db::getInstance()->query('SELECT email FROM mitglieder WHERE aktiviert = true ORDER BY email')->getColumn();

Tpl::pause();

header('Content-Type: text/plain; charset=utf-8');

echo implode("\r\n", $emails);

