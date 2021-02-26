<?php
declare(strict_types=1);
namespace MHN\Mitglieder;

\MHN\Mitglieder\Tpl::set('alert_type', 'danger');
\MHN\Mitglieder\Tpl::set('alert_text', 'Der Link ist ungültig. Möglicherweise wurde der Zugang schon aktiviert.');
\MHN\Mitglieder\Tpl::render('Layout/alert');
