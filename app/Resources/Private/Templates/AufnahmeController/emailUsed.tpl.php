<?php
declare(strict_types=1);
namespace MHN\Mitglieder;

\MHN\Mitglieder\Tpl::set('alert_type', 'danger');
\MHN\Mitglieder\Tpl::set('alert_text', 'Die E-Mail-Adresse ' . $email . ' wird bereits von einem anderen Mitglied verwendet. Bitte wende dich an die Mitgliederbetreuung.');
\MHN\Mitglieder\Tpl::render('Layout/alert');
