<?php declare(strict_types=1); namespace MHN\Mitglieder; ?>
<div class="table-responsive"><table class="table">
    <tr><th>Datum</th><th>Titel der Veranstaltung</th><th>Ort</th></tr>
    <?php if (!count($veranstaltungen)): ?>
        <tr><td colspan='4'>Keine</td></tr>
    <?php else: foreach ($veranstaltungen as $v): ?>
        <tr>
            <td><?=$v['datum']?></td>
            <td><a href="<?=$v['url']?>"><?=$v['titel']?></a></td>
            <td><?=$v['ort']?></td>
        </tr>
    <?php endforeach; endif; ?>
</table></div>
