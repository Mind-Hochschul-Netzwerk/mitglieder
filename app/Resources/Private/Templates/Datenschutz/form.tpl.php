<?php declare(strict_types=1); namespace MHN\Mitglieder; ?>

<div style="margin: 0em; padding: 1em 1em 1em 1em; background-color: #eef; border-radius: 1em;">

    <?php Tpl::render('Datenschutz/kenntnisnahme-text'); ?>

</div>

<?php if ($kenntnisnahme_datenverarbeitung === null): ?>
<form method="post">
    <div class='form-group row '>
        <label for="kenntnisnahme_datenverarbeitung" class='col-sm-12 col-form-label'>
            <input id="kenntnisnahme_datenverarbeitung" name="kenntnisnahme_datenverarbeitung" value="1" type="checkbox"> Ja, ich nehme zur Kenntnis, dass meine personenbezogenen Daten wie obenstehend verarbeitet und gespeichert werden.
        </label>
    </div>
    <div class="form-group row">
        <div class="col-sm-12">
            <button type="submit" name="submit" class="btn btn-success">Speichern</button>
        </div>
    </div>
</form>
<?php else: ?>
    <p>Du hast am <?=$kenntnisnahme_datenverarbeitung->format('d.m.Y')?> zur Kenntnis genommen, dass deine personenbezogenen Daten wie obenstehend verarbeitet und gespeichert werden.</p>
<?php endif; ?>


