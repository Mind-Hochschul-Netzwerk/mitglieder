<?php
declare(strict_types=1);

/**
 * front controller
 */

namespace App;

use Symfony\Component\HttpFoundation\Request;

require_once '../vendor/autoload.php';

date_default_timezone_set('Europe/Berlin');
setlocale(LC_TIME, 'german', 'deu_deu', 'deu', 'de_DE', 'de');

Service\Session::getInstance()->start();

$request = Request::createFromGlobals();
$path = explode('/', $request->getPathInfo() . '///');

$controller = $path[1];

if (in_array($path[1], ['', 'search'])) {
    (new \App\Controller\SearchController($request))->route()->send();
} elseif ($path[1] === 'admin') {
    (new \App\Controller\AdminController($request))->route()->send();
} elseif ($path[1] === 'aufnahme') {
    (new \App\Controller\AufnahmeController($request))->route()->send();
} elseif ($path[1] === 'statistics') {
    (new \App\Controller\StatisticsController($request))->route()->send();
} elseif ($path[1] === 'wahlleitung') {
    (new \App\Controller\WahlleitungController($request))->route()->send();
} elseif (in_array($path[1], ['login', 'logout', 'lost-password'], true)) {
    (new \App\Controller\AuthController($request))->route()->send();
} elseif (in_array($path[1], ['user', 'users', 'email_auth'], true)) {
    (new \App\Controller\UserController($request))->route()->send();
} elseif (in_array($path[1], ['group', 'groups'], true)) {
    (new \App\Controller\GroupController($request))->route()->send();
} else {
    http_response_code(404);
    die('invalid route');
}
