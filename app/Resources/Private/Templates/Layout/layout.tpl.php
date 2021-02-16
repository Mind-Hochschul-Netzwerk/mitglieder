<?php declare(strict_types=1); namespace MHN\Mitglieder; ?>

<?php Tpl::render('Layout/navigation'); ?>

<div class="main"><div class="container-fluid">
    <?=!empty($title) ? "<h1>$title</h1>" : ''?>
    
    <?=$htmlBody?>

    <hr />
</div></div> <!-- /main -->
