<?php
declare(strict_types=1);
namespace MHN\Mitglieder;

/**
* @author Henrik Gebauer <mensa@henrik-gebauer.de>
*/

use MHN\Mitglieder\Service\Ldap;
use MHN\Mitglieder\DB;

const no_output_buffering = true; // Mitteilung an tpl.inc.php, dass es sich um ein Backend-Skript handelt

require_once '/var/www/lib/base.inc.php';

$ldap = Ldap::getInstance();

$result = DB::query('SELECT id, username, password, email, vorname, nachname, aktiviert FROM mitglieder ORDER BY id');

echo "\n";
while ($member = $result->get_row()) {
    $entry = $ldap->getEntryByUsername($member['username']);
    if ($entry) {
        // ldap is already initialized
        // return;
        continue;
    }
    echo "ldap migration: $member[id] $member[username]\n";
    $ldap->addUser($member['username'], [
        'firstname' => $member['vorname'],
        'lastname' => $member['nachname'],
        'email' => $member['email'],
        'id' => $member['id'],
        'suspended' => "" . (1 - $member['aktiviert']),
        'hashedPassword' => '{CRYPT}! unknown',
    ]);

    $rechte = DB::query('SELECT recht FROM rechte WHERE uid = %d', (int)$member['id'])->get_column();
    foreach ($rechte as $recht) {
        $ldap->addRole($member['username'], $recht);
    }

    $ldap->addMoodleCourse($member['username'], 'alleMitglieder');
    $ldap->addMoodleCourse($member['username'], 'listen');
}
