<?php
$this->extends('Layout/layout', [
    'htmlTitle' => 'Statistik',
    'title' => 'Statistik',
    'navId' => 'statistics',
]);
?>

<pre>
<?=<<<EOT
Einträge in der Mitgliedertabelle: $countAllEntries
zusätzlich $countDeleted gesperrte Benutzernamen (= gelöschte Mitglieder)

Von den $countAllEntries Mitgliedern:
... $countAfterOct2018 erst nach der Vereinsgründung eingetreten
... $countConfirmedMembership haben ihre Mitgliedschaft bestätigt
... $countResignations haben ihren Austritt zum Ende des Jahres erklärt. (<a href="/search/resigned">Liste</a>)
... $countInvalidEmails haben keine erreichbare E-Mail-Adresse: (<a href="/statistics/invalidEmails">Liste</a>)
EOT?>
</pre>

<table class="table">
    <tr><th>Eintritt bis zum 3.10. des Jahres</th><th>Anzahl<sup>1</sup></th><th>Letzte Mitgliedsnummer<sup>1</sup></th></tr>
<?php
    foreach ($eintritte as $entry) {
        echo "<tr><td>{$entry['eintrittsjahr']}</td><td>{$entry['anzahl']}</td><td>{$entry['max_id']}</td></tr>\n";
    }
?>
</table>
<p><sup>1</sup> In der Statistik sind nur Mitglieder enthalten, die aktuell noch Mitglied sind.</p>