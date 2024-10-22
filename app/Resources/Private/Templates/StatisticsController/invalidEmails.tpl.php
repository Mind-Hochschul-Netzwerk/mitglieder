<?php
$this->extends('Layout/layout', [
    'htmlTitle' => 'Personen mit nicht-erreichbaren E-Mail-Adressen',
    'title' => 'Personen mit nicht-erreichbaren E-Mail-Adressen',
    'navId' => 'statistics',
]);
?>

<p>Die folgenden Personen sind nicht mehr per E-Mail erreichbar. Sie wurden manuell markiert, nachdem E-Mails nicht zugestellt werden konnten (z.B. Server unbekannt, User unbekannt, Postfach voll).

<table class="table">
    <tr><th>ID</th><th>Profil / Name</th><th>Ort</th><th>E-Mail</th><th>Aufnahmedatum</th><th>letzter Login</th><th>Moodle freigeschaltet</th></tr>
<?php foreach($invalidEmailsList as $u) {
    echo "<tr><td>$u[id]</td><td><a href='/user/$u[id]/edit'>$u[fullName]</a></td><td>$u[ort]</td><td>$u[email]</td><td>$u[aufnahmedatum]</td><td>$u[lastLogin]</td><td>$u[moodle]</td>\n";
}?>
</table>
