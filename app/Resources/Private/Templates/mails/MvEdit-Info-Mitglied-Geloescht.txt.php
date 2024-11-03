<?php
$returnValue = $subject = 'Information über gelöschtes Mitglied';
?>
Liebes Mitglied der Mitgliederbetreuung,

<?=$deletedName?> (ID <?=$deletedId?>, Benutzername <?=$deletedUsername?>, <?=$deletedEmail?>) wurde von
<?=$adminName?> (ID <?=$adminId?>, Benutzername <?=$adminUsername?>) gelöscht.

Außer dem Benutzernamen wurden alle Daten in der Mitgliederdatenbank gelöscht.
