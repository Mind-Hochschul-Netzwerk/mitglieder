<?php
declare(strict_types=1);
namespace App;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

/**
 * Konfiguration
 * Lädt teilweise Umgebungsvariablen
 */
class Config
{
    const passwordIterations = 100000;

    // Maximale Größe von Profilbildern
    const profilbildMaxWidth = 800;
    const profilbildMaxHeight = 800;
    const thumbnailMaxWidth = 300;
    const thumbnailMaxHeight = 300;

    // MySQL-Zugangsdaten (werden als Environment-Variablen übergeben, s.u.)
    public static $mysqlHost;
    public static $mysqlUser;
    public static $mysqlPassword;
    public static $mysqlDatabase;
}

Config::$mysqlHost = getenv('MYSQL_HOST');
Config::$mysqlUser = getenv('MYSQL_USER');
Config::$mysqlPassword = getenv('MYSQL_PASSWORD');
Config::$mysqlDatabase = getenv('MYSQL_DATABASE');

setlocale(LC_TIME, 'german', 'deu_deu', 'deu', 'de_DE', 'de');
