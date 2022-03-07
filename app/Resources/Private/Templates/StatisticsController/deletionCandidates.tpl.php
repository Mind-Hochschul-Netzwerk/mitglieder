<p>Die folgenden <?=count($deletionCandidates)?> Personen:</p>
<table class="table">
    <tr><th>ID</th><th>Profil / Name</th><th>Ort</th><th>E-Mail</th><th>Aufnahmedatum</th><th>letzter Login</th><th>Moodle freigeschaltet</th></tr>
<?php foreach($deletionCandidates as $u) {
    echo "<tr><td>$u[id]</td><td><a href='bearbeiten.php?id=$u[id]'>$u[fullName]</a></td><td>$u[ort]</td><td>$u[email]</td><td>$u[aufnahmedatum]</td><td>$u[lastLogin]</td><td>$u[moodle]</td>\n";
}?>
</table>