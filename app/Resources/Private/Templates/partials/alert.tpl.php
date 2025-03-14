<?php declare(strict_types=1); namespace App; ?>
<div <?=$this->check($alertId) ? "id='$alertId'" : ''?> class="alert alert-<?=$type?> alert-dismissible fade in <?=$this->check($hide) ? 'hide' : ''?>" role="alert">
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button        >
    <?=$type == 'danger' ? '<strong>Fehler:</strong> ' : ''?> <?=$text?>
</div>
