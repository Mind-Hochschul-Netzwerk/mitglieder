<?php
$returnValue = $subject = 'E-Mail-Änderung';
?>
Hallo <?=$fullName?>,

Bitte rufe die folgende Adresse auf, um die Änderung deiner E-Mail-Adresse in der MHN-Datenbank zu bestätigen:

https://mitglieder.<?=getenv('DOMAINNAME')?>/email_auth/?token=<?="$token\n"?>

Wenn dich diese E-Mail überrascht, dann nimm bitte mit der Mitgliederbetreuung Kontakt auf.
