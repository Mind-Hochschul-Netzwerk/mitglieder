<?php
declare(strict_types=1);
namespace MHN\Mitglieder;

require_once '../lib/base.inc.php';

// LDAP-Test

use MHN\Mitglieder\Service\Ldap;

$ldap = Ldap::getInstance();

header('Content-Type: text/plain');

# test.user : MHNTest0#
# test.manager : MHNTest0#    {CRYPT}$5$euSCUMlb$iMi.J0ghBqdQC.6WSIBy00Vzup538cU89oTaGz62w0A
$username = 'test.manager';
$password = 'MHNTest0#';

echo "\n";

if ($ldap->checkPassword($username, $password)) {
    echo "Passwort $password ist korrekt für Benutzer $username\n";
} else {
    echo "Passwort $password ist FALSCH für Benutzer $username\n";
}

echo "Eingeloggt als Admin\n";

echo "Hole Benutzereinträge:\n";

/*
$ldap->addUser('test.add2', [
    'firstname' => 'neu',
    'lastname' => 'hinzugefügt',
    'email' => 'test@example.com.invalid',
    'id' => 234,
    'description' => 'hallo',
    'password' => 'test',
]);
*/

$entry = $ldap->getEntryByUsername('test.manager');
var_dump($entry);

// $query = $ldap->query(getenv('LDAP_PEOPLE_DN'), '(&(objectclass=inetOrgPerson))');
// $results = $query->execute();

// foreach ($results as $entry) {
//     // Do something with the results
//     var_dump($entry);
// }

// Update Password
$ldap->modifyUser('test.manager', ['lastname' => 'neuer Nachname', 'password' => $password]);

#$entry->setAttribute('userPassword', ['{CRYPT}$5$euSCUMlb$iMi.J0ghBqdQC.6WSIBy00Vzup538cU89oTaGz62w0A']);

#var_dump($entry);

var_dump($ldap->getRoles());

echo "Ende";

