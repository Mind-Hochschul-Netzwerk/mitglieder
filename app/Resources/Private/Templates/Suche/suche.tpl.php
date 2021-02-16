<?php declare(strict_types=1); namespace MHN\Mitglieder; ?>
<form>

<div class="form-group row">
    <div class="col-sm-10">
        <input name="q" type="search" placeholder="Suche" class="form-control" value="<?=$query?>" />
    </div>
    <div class="col-sm-2">
        <button type="submit" class="btn btn-success" onclick="return suchen();"><span class="glyphicon glyphicon-search"></span> Suchen</button>
    </div>
</div>

</form>

<?php if (isset($ergebnisse)): ?>
    <div id="suchergebnisse">
    <h2>Suchergebnisse</h2>

    <?php if (!$countResults): ?>
        <p>Die Suche erbrachte kein Ergebnis.</p>
    <?php else: ?>
        <p>Es <?=$countResults===1?'wurde ein Mitglied':"wurden $countResults Mitglieder"?> gefunden.
            <?php if (count($ergebnisse) < $countResults): ?>
                Es werden nur die ersten <?=count($ergebnisse)?> Ergebnisse angezeigt. Verfeinere die Suche, wenn das gesuchte Mitglied nicht dabei ist.
            <?php elseif (count($ergebnisse) < $countResults): ?>
                In dieser Zahl sind nicht aktivierte oder als gelöscht markierte Mitglieder nicht enthalten.
            <?php endif; ?>
        <div class="table-responsive"><table class="table vertical-center" id="suchergebnisse">
            <tr><th>#</th><th>Profilbild</th><th>Name</th><th>Ort</th><th>Benutzerseite im Wiki</th></tr>
            <?php

            $n = 0;
            $graue = false;
            foreach ($ergebnisse as $e) {
                ++$n;
                $class = '';
                $thumbnail = "<div class='thumbnail-container'><a href='profil.php?id=$e[id]'><img class='img-thumbnail' src='" . ($e['profilbild'] ? 'profilbilder/' . $e['profilbild'] : 'img/thumbnail-profilbild-default.png') . "' alt='Profilbild'  ></a></div>";
                if ($e['last_login'] === null || $e['last_login'] < new \DateTime('-6 months')) {
                    $class = 'inaktiv';
                    $graue = true;
                }
                echo "<tr class='$class'>
                    <td>$n</td>
                    <td>$thumbnail</td>
                    <td><a href='profil.php?id=$e[id]'>$e[fullName]</a></td>
                    <td>$e[orte]</td>
                    <td><a href='https://wiki.mind-hochschul-netzwerk.de/wiki/Benutzer:$e[username]'>$e[username]</a></td>
                </tr>\n";
            }
            ?>
            
        </table></div>
        <?php if ($graue): ?>
            <p>Mitglieder, die sich seit mehr als 6 Monaten nicht mehr eingeloggt haben, werden ausgegraut dargestellt.</p>
        <?php endif; ?>
        
        <?php endif; ?>   
    </div>
<?php endif; ?>
