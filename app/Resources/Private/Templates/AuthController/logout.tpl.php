<?php
$this->extends('Layout/layout', [
    'htmlTitle' => 'Logout',
    'title' => 'Logout',
    'navId' => 'logout',
]);
?>

<p>Du wurdest erfolgreich ausgeloggt.</p>

<p><a href="https://www.<?=getenv('DOMAINNAME')?>">Zurück zur MHN-Homepage gehen.</a></p>
