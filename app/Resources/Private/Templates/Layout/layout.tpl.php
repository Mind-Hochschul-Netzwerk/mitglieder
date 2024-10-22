<?php
$this->extends('Layout/html');
$this->include('Layout/navigation')
?>

<div class="main"><div class="container-fluid">
    <?=!empty($title) ? "<h1>" . $this->get('title') . "</h1>" : ''?>

    <?=$this->get('@@contents')?>

    <hr />
</div></div> <!-- /main -->
