<?php declare(strict_types=1); namespace App; ?>
<form method="post">

<p>Bitte nimm dir die Zeit, die Datenschutzbestimmungen und unsere Regeln zu lesen.</p>

<div style="margin: 0em; padding: 0.2em 1em 1em 1em; background-color: #eef; border-radius: 1em;">

<?php Tpl::render('Auth/terms') ?>

</div>

<div class='form-group row '>
    <label for='input-terms' class='col-sm-12 col-form-label'>
        <input id="input-terms" name="input-terms" value="1" type="checkbox" class=""> Ich stimme den Datenschutzbestimmungen und Nutzungsbedingungen zu.
    </label>
</div>

<div class="form-group row">
    <div class="col-sm-12">
        <button type="submit" class="btn btn-success">Speichern</button>
    </div>
</div>

</form>

