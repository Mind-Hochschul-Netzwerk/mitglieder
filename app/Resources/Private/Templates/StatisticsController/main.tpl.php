<pre>
<?=<<<EOT
Einträge in der Mitgliedertabelle: $countAllEntries
zusätzlich $countDeleted gesperrte Benutzernamen (= gelöschte Mitglieder)

Von den $countAllEntries Mitgliedern:
... $countAfterOct2018 erst nach der Vereinsgründung eingetreten
... $countConfirmedMembership haben ihre Mitgliedschaft bestätigt
... insgesamt $countMembers offizielle Vereinsmitglieder
... $countDeletionCandidates müssen also gelöscht werden

Von den $countMembers offiziellen Vereinsmitgliedern:
... $countResignations haben ihren Austritt zum Ende des Jahres erklärt. (<a href="/?resigned=1">Liste</a>)

Mitglieder mit nicht erreichbarer E-Mail-Adresse: $countInvalidEmails (<a href="?a=invalidEmails">Liste</a>)
EOT?>
</pre>
