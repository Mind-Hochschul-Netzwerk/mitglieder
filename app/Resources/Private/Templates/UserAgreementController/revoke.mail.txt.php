<?php
$returnValue = $subject = 'Verpflichtungserklärung zurückgezogen';
?>
Liebes Mitglied des Vorstands, liebe*r Datenschutzbeauftragte*r,

das Mitglied <?=$user->get('fullName')?> hat
die Verpflichtungserklärung zum Datenschutz zurückgezogen.
(Eintragung vorgenommen durch: <?=$recorderName?>)

Falls dem Mitglied Aufgaben bzw. Rechte übertragen wurden, durch die es
einen zusätzlichen Zugriff auf persönliche Daten anderer Mitglieder hat,
müssen ihm diese entzogen werden.

Bitte prüfe vor der Umsetzung des Rechteentzugs allerdings, ob das Mitglied die
Verpflichtungserklärung zwischenzeitlich wieder angenommen hat.

<?=$url?>
