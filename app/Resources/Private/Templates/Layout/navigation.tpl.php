<?php declare(strict_types=1); namespace MHN\Mitglieder; ?>
<?php

if (Auth::istEingeloggt()) {
    $navItems = [
        'home' => ['/', 'Startseite', 'home'],
        'bearbeiten' => ['bearbeiten.php', 'Mein Profil', 'user'],
        'suche' => ['suche.php', 'Mitgliedersuche', 'search'],
        'veranstaltungen' => ['https://veranstaltungen.mind-hochschul-netzwerk.de/', 'Veranstaltungen', 'calendar'],
        'wiki' => ['https://wiki.mind-hochschul-netzwerk.de/index.php?title=Spezial:Anmelden&returnto=Hauptseite', 'Wiki', 'globe'],
        'admin' => Auth::hatRecht('mvedit') ? ['admin.php', 'Mitgliederverwaltung', 'wrench'] : null,
        'logout' => ['logout.php', 'Logout', 'log-out'],
        'datenschutz' => ['https://www.mind-hochschul-netzwerk.de/index.php/datenschutz/', 'Datenschutz', 'paragraph'],
        'impressum' => ['https://www.mind-hochschul-netzwerk.de/index.php/impressum/', 'Impressum', 'globe'],
    ];
} else {
    $navItems = [
        'homepage' => ['https://www.mind-hochschul-netzwerk.de/', 'Homepage', 'home'],
        'login' => ['/', 'Login', 'log-in'],
        'aufnahme' => ['https://mind-hochschul-netzwerk.de/index.php/aufnahme/', 'Mitglied werden', 'plus'],
        'logout' => ['logout.php', 'Logout', 'log-out'],
        'datenschutz' => ['https://www.mind-hochschul-netzwerk.de/index.php/datenschutz/', 'Datenschutz', 'paragraph'],
        'impressum' => ['https://www.mind-hochschul-netzwerk.de/index.php/impressum/', 'Impressum', 'globe'],
    ];
}
?>
<nav class="navbar navbar-mhn sidebar" role="navigation">
    <div class="container-fluid">
        <!-- Brand and toggle get grouped for better mobile display -->
        <div class="navbar-header">
            <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#bs-sidebar-navbar-collapse-1">
                <span class="sr-only">Navigation aufklappen</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="/"><img src="/img/mhn-logo-small.png" id="mhn-logo"><span class="logo-text"> Mein MHN
            </span>
                <span class='pull-right showopacity glyphicon'><img src="/img/mhn-logo-small.png" id="mhn-icon"></span>
            </a>
        </div>
        <div class="collapse navbar-collapse" id="bs-sidebar-navbar-collapse-1">
            <ul class="nav navbar-nav">
<?php

foreach ($navItems as $itemname => $item) {
    if (!$item) {
        continue;
    }
    $class = (!empty($navId) and $navId === $itemname) ? 'active' : '';
    echo "<li class='$class'><a href='$item[0]'>$item[1]<span class='pull-right showopacity glyphicon glyphicon-$item[2]'></span></a></li>\n";
}
?>
            </ul>
        </div>
    </div>
</nav>
