<?php
declare(strict_types=1);
namespace App;

\App\Tpl::set('alert_type', 'danger');
\App\Tpl::set('alert_text', 'Der Link ist ungültig. Möglicherweise wurde der Zugang schon aktiviert.');
\App\Tpl::render('Layout/alert');
