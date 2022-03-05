<pre>
<?=<<<EOT
Einträge in der Mitgliedertabelle: $countAllEntries
... davon $countNotActivated vor 2021 aus dem Aufnahmetool übernommen, aber bisher nicht aktiviert
   (seit 2021 werden nicht aktivierte Mitglieder bis zur Aktivierung nicht aus dem Aufnahmetool übernommen)
... also $countActivated aktivierte Mitglieder.
zusätzlich $countDeleted gesperrte Benutzernamen (= gelöschte Mitglieder)

Von den $countActivated aktivierten Mitgliedern:
... $countAfterOct2018 erst nach der Vereinsgründung eingetreten
... $countConfirmedMembership haben ihre Mitgliedschaft bestätigt
... insgesamt $countMembers offizielle Vereinsmitglieder
... $countDeletionCandidates müssen also gelöscht werden (<a href="?a=deletionCandidates">Liste</a>)

Von den $countMembers offiziellen Vereinsmitgliedern:
... $countResignations haben ihren Austritt zum Ende des Jahres erklärt. (<a href="/?resigned=1">Liste</a>)

Mitglieder mit nicht erreichbarer E-Mail-Adresse: $countInvalidEmails (<a href="?a=invalidEmails">Liste</a>)
EOT?>
</pre>
