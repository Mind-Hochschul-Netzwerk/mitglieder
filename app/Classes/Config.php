<?php
declare(strict_types=1);
namespace MHN\Mitglieder;

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

    const newEmailTokenExpireTime = '+24 hours'; // Zeit, in der eine neue E-Mail-Adresse aktiviert werden muss. Format für strtodate.
    const newPasswordLength = 12; // Anzahl der Zeichen für ein automatisch generiertes Passwort
    const newPasswordExpireTime = '+24 hours'; // Gültigkeitsdauer des Tokens für Passwortreset

    const rootURL = 'http://mitglieder.mind-hochschul-netzwerk.de/'; // mit / am Ende
    const emailFrom = 'Mein MHN <noreply@mitglieder.mind-hochschul-netzwerk.de>'; // Absendeadresse von E-Mails

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
