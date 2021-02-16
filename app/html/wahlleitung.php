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
use MHN\Mitglieder\DB;

require_once '../lib/base.inc.php';

Auth::intern();

Tpl::set('htmlTitle', 'Wahlleitung');
Tpl::set('title', 'Wahlleitung');

if (!Auth::hatRecht('wahlleitung')) {
    die('Fehlende Rechte.');
}

$emails = DB::query('SELECT email FROM mitglieder WHERE aktiviert = true AND deleted = false ORDER BY email')->get_column();

Tpl::pause();

header('Content-Type: text/plain; charset=utf-8');

echo implode("\r\n", $emails);

