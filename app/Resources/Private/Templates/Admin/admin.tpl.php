<?php declare(strict_types=1); namespace MHN\Mitglieder; ?>
<h2>Mitgliederdaten bearbeiten</h2>
<p>Suche das zu bearbeitende Mitglied mit der <a href="/">Mitgliedersuche</a> und klicke im Profil auf "Daten bearbeiten".</p>

<?php if (Auth::hatRecht('rechte')): ?>
<h2>Rollen verwalten</h2>
<p>Suche das zu bearbeitende Mitglied mit der <a href="/">Mitgliedersuche</a> und klicke im Profil auf "Daten bearbeiten".</p>
<p>Aktuell implementierte Rollen:</p>
<ul>
<?php
    foreach ($roles as $role) {
        echo '<li><code>' . $role['name'] . '</code>: ' . $role['description'] . '</li>';
    }
?>
</ul>
<h3>Übersicht über gesetzte Rollen</h3>
<div id="panels" class="row">
<?php
    $recht_prev = null;
    foreach ($roles as $n=>$role) {
        echo "<div class='col-sm-2'><div class='panel panel-default'><div class='panel-heading'>$role[name]</div><div id='panel-$n' class='panel-body'>\n";
        foreach ($role['users'] as $user) {
            echo "<div><a href='bearbeiten.php?id=$user[id]'>$user[firstname] $user[lastname]</a></div>";
        }
        echo '</div></div></div>';
    }

?>
</div>
<?php endif; ?>
