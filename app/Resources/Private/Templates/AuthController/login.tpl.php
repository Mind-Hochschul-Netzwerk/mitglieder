<?php
$this->extends('Layout/layout', [
  'htmlTitle' => 'Login',
  'navId' => 'login',
]);
?>
<div id="loginModal" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="false">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
<form method="post" action="/login">
    <input type="hidden" name="redirect" value="<?=$redirectUrl?>" >
      <div class="modal-header">
        <h4 class="modal-title" id="myModalLabel">Login ins MHN-Mitgliederverzeichnis</h4>
      </div>
      <div class="modal-body">
        <h4>Gib deine Mitgliedsdaten ein, um dich anzumelden.</h4>

<?php if (!empty($lost_password)) {
    $this->include('Layout/alert', [
      'alert_type' => 'success',
      'alert_text' => "Falls ein entsprechendes Benutzerkonto gefunden wurde, wurde eine E-Mail an deine E-Mail-Adresse gesendet.",
    ]);
} ?>
        <?php if (!empty($error_passwort_falsch)): ?>
            <div id="alertFalsch" class="alert alert-danger">Die Benutzerkennung oder das Passwort ist falsch.</div>
        <?php endif; ?>

        <div id="alertBenutzerkennung" class="alert alert-danger <?=(empty($error_username_leer)) ? 'hide' : ''?>">Gib deinen Benutzernamen oder E-Mail-Adresse an.</div>
        <div class="row form-group">
            <div class="col-sm-12">
                <input id="uid" name="id" type="text" placeholder="Benutzername, Mitgliedsnummer oder E-Mail-Adresse" class="form-control" />
            </div>
        </div>
        <div class="row form-group">
            <div class="col-sm-12">
                <input name="password" type="password" placeholder="Passwort" class="form-control" />
            </div>
        </div>

        <button type="submit" onclick="return check_username();" class="hidden-default-button"></button>
        <p><button class="link btn btn-secondary" onclick="return check_username();" name="passwort_vergessen">Ich habe mein Passwort vergessen.</button></p>
      </div>
      <div class="modal-footer">
        <button type="submit" onclick="return check_username();" class="btn btn-primary">Login</button>
      </div>
</form>

    </div><!-- /.modal-content -->
  </div><!-- /.modal-dialog -->
</div>

<script>
function check_username() {
    $("#alertBenutzerkennung").addClass("hide");
    $("#alertFalsch").addClass("hide");
    if ($("#uid").val() == "") {
        $("#alertBenutzerkennung").removeClass("hide");
        return false;
    }
    return true;
}
</script>
