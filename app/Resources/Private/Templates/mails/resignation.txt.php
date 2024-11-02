<?php $returnValue = $subject = 'Austrittserklärung'; ?>
Liebes Mitglied des Vorstands / der Mitgliederbetreuung,

das Mitglied <?=$fullName?> (MHN-Mitgliedsnummer <?=$id?>) hat den Austritt aus dem Verein erklärt.

<?php if (!empty($adminFullName)): $returnValue = $subject = 'Austrittserklärung eingetragen'; ?>
Der Austritt wurde von <?=$adminFullName?> eingetragen.
<?php endif; ?>

Gemäß der Satzung ist ein Austritt zum Ende des Kalenderjahres möglich.

Das Profil muss daher von der Mitgliederbetreuung zum Ende des Kalenderjahres gelöscht werden.

https://mitglieder.<?=getenv('DOMAINNAME')?>/user/<?="$id\n"?>

Eine Auflistung aller noch nicht gelöschten Profile von Mitgliedern, die ihren Austritt erklärt haben, findest du hier:

https://mitglieder.<?=getenv('DOMAINNAME')?>/search/resigned
