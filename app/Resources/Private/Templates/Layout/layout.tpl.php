<?php
$this->extends('Layout/html');
$this->include('Layout/navigation')
?>

<div class="main"><div class="container-fluid">
    <?=$title ? ("<h1>" . $title->raw() . "</h1>") : ''?>

    <?=$_contents->raw?>

    <hr />
</div></div> <!-- /main -->
