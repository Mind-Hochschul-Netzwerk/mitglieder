<?php
declare(strict_types=1);
namespace App;

\App\Tpl::set('alert_type', 'danger');
\App\Tpl::set('alert_text', 'Die E-Mail-Adresse ' . $email . ' wird bereits von einem anderen Mitglied verwendet. Bitte wende dich an die Mitgliederbetreuung.');
\App\Tpl::render('Layout/alert');
