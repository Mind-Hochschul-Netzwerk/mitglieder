<?php
$this->extends('Layout/layout', [
    'htmlTitle' => 'Mitgliederverwaltung',
    'title' => 'Mitgliederverwaltung',
    'navId' => 'admin',
]);
?>

<h2>Mitgliederdaten bearbeiten</h2>
<p>Suche das zu bearbeitende Mitglied mit der <a href="/search">Mitgliedersuche</a> und klicke im Profil auf "Daten bearbeiten".</p>

<h2>Austrittsersuche bearbeiten</h2>
<p>Zeige die <a href="/search/resigned">Liste der ausgetretenen Mitglieder</a> und klicke im Profil auf "Daten bearbeiten".</p>

<?php if ($this->check($groups)): ?>
    <h2>Gruppenzuordnungen verwalten</h2>
    <p>Suche das zu bearbeitende Mitglied mit der <a href="/search">Mitgliedersuche</a> und klicke im Profil auf "Daten bearbeiten".</p>
    <ul>
    <?php foreach ($groups as $group): ?>
        <li><code><?=$group->name?></code>: <?=$group['description']?></li>
    <?php endforeach; ?>
    </ul>
    <h3>Übersicht über die Gruppen</h3>
    <div id="panels" class="row">
    <?php
        foreach ($groups as $n=>$group) {
            echo "<div class='col-sm-2'><div class='panel panel-default'><div class='panel-heading'>$group[name]</div><div id='panel-$n' class='panel-body'>\n";
            if (in_array($group['name'], ['alleMitglieder', 'listen'], true)) {
                echo  "<div>alle Mitglieder</div>";
            } else {
                foreach ($group['users'] as $user) {
                    echo "<div><a href='/user/$user[username]/edit'>$user[firstname] $user[lastname]</a></div>";
                }
            }
            echo '</div></div></div>';
        }
    ?>
    </div>
<?php endif; ?>
