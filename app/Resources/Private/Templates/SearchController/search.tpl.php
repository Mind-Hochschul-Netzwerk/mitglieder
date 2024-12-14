<?php
$this->extends('Layout/layout', [
    'htmlTitle' => 'MHN-Mitgliederverzeichnis',
    'navId' => 'suche',
    'title' => 'MHN-Mitgliederverzeichnis',
]);

?>

<p>Willkommen im Mitgliederverzeichnis des Mind-Hochschul-Netzwerks. Hier kannst du deine Daten in unserer Mitgliederdatenbank aktualisieren und andere Mitglieder finden. Das Netzwerk lebt von deiner aktiven Beteiligung und den Kontakten unter den Mitgliedern.</p>

<form>

<div class="form-group row">
    <div class="col-sm-10">
        <?=$query->input(name: 'q', type: 'search', placeholder: 'Suche')?>
    </div>
    <div class="col-sm-2">
        <button type="submit" class="btn btn-success" onclick="return suchen();"><span class="glyphicon glyphicon-search"></span> Suchen</button>
    </div>
</div>

</form>

<?php if ($this->check($ergebnisse)): ?>
    <div id="suchergebnisse">
    <h2>Suchergebnisse</h2>

    <?php if (!count($ergebnisse)): ?>
        <p>Die Suche erbrachte kein Ergebnis.</p>
    <?php else: ?>
        <div class="table-responsive"><table class="table vertical-center" id="suchergebnisse">
            <tr><th>#</th><th>Profilbild</th><th>Name</th><th>Ort</th></tr>
            <?php

            $n = 0;
            foreach ($ergebnisse as $e) {
                ++$n;
                $thumbnail = "<div class='thumbnail-container'><a href='/user/$e[username]'><img class='img-thumbnail' src='/" . ($e['profilbild'] ? 'profilbilder/' . $e['profilbild'] : 'img/thumbnail-profilbild-default.png') . "' alt='Profilbild'  ></a></div>";
                echo "<tr>
                    <td>$n</td>
                    <td>$thumbnail</td>
                    <td><a href='/user/$e[username]'>$e[fullName]</a></td>
                    <td>$e[orte]</td>
                </tr>\n";
            }
            ?>

        </table></div>
        <?php if (count($ergebnisse) >= 50): ?>
            <p>Es werden nicht alle Ergebnisse angezeigt. Grenze deine Suchkriterien weiter ein.</p>
        <?php endif; ?>

        <?php endif; ?>
    </div>
<?php endif; ?>
