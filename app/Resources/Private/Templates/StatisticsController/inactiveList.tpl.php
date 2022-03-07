<p>Die folgenden <?=count($list)?> Personen wurden aufgenommen, aber haben ihr Konto nicht aktiviert.</p>

<table class="table">
    <tr><th>ID</th><th>Profil / Name</th><th>Aufnahmedatum</th></tr>
<?php foreach($list as $u) {
    echo "<tr><td>$u[id]</td><td><a href='bearbeiten.php?id=$u[id]'>$u[vorname] $u[nachname]</a></td><td>$u[aufnahmedatum]</td></tr>\n";
}?>
</table>
