<?php
declare(strict_types=1);

/**
 * front controller
 */

namespace App;

use App\Controller\AdminController;
use App\Controller\AufnahmeController;
use App\Controller\AuthController;
use App\Controller\GroupController;
use App\Controller\SearchController;
use App\Controller\StatisticsController;
use App\Controller\UserController;
use App\Controller\WahlleitungController;
use App\Service\Router;
use Symfony\Component\HttpFoundation\Request;

require_once '../vendor/autoload.php';

date_default_timezone_set('Europe/Berlin');
setlocale(LC_TIME, 'german', 'deu_deu', 'deu', 'de_DE', 'de');

Service\Session::getInstance()->start();

$request = Request::createFromGlobals();

$router = new Router();

$router->addType(Mitglied::class, [UserController::class, 'retrieve']);

// TODO: use class attributes
// TODO: CSRF tokens
$router->add('GET /', SearchController::class, 'form');
$router->add('GET /search', SearchController::class, 'form');
$router->add('GET /?q={query}', SearchController::class, 'search');
$router->add('GET /search?q={query}', SearchController::class, 'search');
$router->add('GET /search/resigned', SearchController::class, 'showResigned');

$router->add('GET /admin', AdminController::class, 'show');
$router->add('GET /statistics', StatisticsController::class, 'show');
$router->add('GET /statistics/invalidEmails', StatisticsController::class, 'showInvalidEmails');
$router->add('GET /wahlleitung', WahlleitungController::class, 'show');

$router->add('GET /aufnahme?token={token}', AufnahmeController::class, 'show');
$router->add('POST /aufnahme?token={token}', AufnahmeController::class, 'submit');

$router->add('GET /login', AuthController::class, 'loginForm');
$router->add('POST /login', AuthController::class, 'loginSubmitted');
$router->add('GET /logout', AuthController::class, 'logout');
$router->add('GET /lost-password?token={token}', AuthController::class, 'resetPasswordForm');
$router->add('POST /lost-password?token={token}', AuthController::class, 'resetPassword');

$router->add('GET /user', UserController::class, 'showSelf');
$router->add('GET /user/{[0-9]+:id=>m}', UserController::class, 'show'); // TODO testen
$router->add('GET /user/{username=>m}', UserController::class, 'show');
$router->add('GET /user/{username=>m}/edit', UserController::class, 'edit');
$router->add('POST /user/{username=>m}/update', UserController::class, 'update');

$router->add('GET /email_auth?token={token}', UserController::class, 'emailAuth');

$router->add('GET /groups', GroupController::class, 'index');

$router->dispatch($request)->send();
