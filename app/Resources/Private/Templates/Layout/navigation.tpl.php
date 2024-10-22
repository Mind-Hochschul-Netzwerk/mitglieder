<?php

use App\Service\AuthService;

if (AuthService::istEingeloggt()) {
    $navItems = [
        'suche' => ['/', 'Mitgliedersuche', 'search'],
        'bearbeiten' => ['/user/_/edit', 'Meine Daten', 'user'],
        'statistics' => AuthService::hatRecht('mvread') ? ['/statistics', 'Statistik', 'stats'] : null,
        'admin' => AuthService::hatRecht('mvedit') ? ['/admin', 'Mitgliederverwaltung', 'wrench'] : null,
        'logout' => ['/logout', 'Logout', 'log-out'],
        'homepage' => ['https://www.' . getenv('DOMAINNAME'), 'MHN-Webseite', 'home'],
        'datenschutz' => ['https://www.' . getenv('DOMAINNAME') . '/mod/book/view.php?id=253&chapterid=4', 'Datenschutz', 'paragraph'],
        'impressum' => ['https://www.' . getenv('DOMAINNAME') . '/mod/book/view.php?id=253&chapterid=5', 'Impressum', 'globe'],
    ];
} else {
    $navItems = [
        'homepage' => ['https://www.' . getenv('DOMAINNAME'), 'Startseite', 'home'],
        'login' => ['/', 'Login', 'log-in'],
        'logout' => ['/logout', 'Logout', 'log-out'],
        'datenschutz' => ['https://www.' . getenv('DOMAINNAME') . '/mod/book/view.php?id=253&chapterid=4', 'Datenschutz', 'paragraph'],
        'impressum' => ['https://www.' . getenv('DOMAINNAME') . '/mod/book/view.php?id=253&chapterid=5', 'Impressum', 'globe'],
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
            <a class="navbar-brand" href="/"><img src="/img/mhn-logo-small.png" id="mhn-logo"><span class="logo-text"> Mitglieder
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
