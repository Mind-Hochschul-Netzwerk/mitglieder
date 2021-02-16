<?php
declare(strict_types=1);
namespace MHN\Mitglieder;

/**
* Lädt die anderen PHP-Dateien, die überall benötigt werden
*
* @author Henrik Gebauer <mensa@henrik-gebauer.de>
*/

set_include_path(__DIR__ . ':' . get_include_path());
date_default_timezone_set('Europe/Berlin');

// Composer
require_once __DIR__ . '/../vendor/autoload.php';

require_once 'ensure.inc.php';      // Benutzereingaben prüfen

Service\Session::getInstance()->start();
