<?php
declare(strict_types=1);
namespace MHN\Mitglieder;

Tpl::set('title', "Mitglied löschen", false);

if (!empty($errorMessage)) {
    \MHN\Mitglieder\Tpl::set('alert_type', 'warning');
    \MHN\Mitglieder\Tpl::set('alert_text', $errorMessage);
    \MHN\Mitglieder\Tpl::render('Layout/alert');
    echo "Beim Löschen ist ein Fehler aufgetreten.";
} else {
    echo "Das Mitglied wurde gelöscht. Die Mitglieder der Mitgliederbetreuung wurden informiert.";
}
