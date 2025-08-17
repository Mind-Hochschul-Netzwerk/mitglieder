<?php
/**
 * @author Henrik Gebauer <henrik@mind-hochschul-netzwerk.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

declare(strict_types=1);
namespace App;

use App\Model\User;
use App\Repository\UserRepository;
use App\Service\Container;
use App\Service\Tpl;

class Bootstrap {
    private Container $container;

    public function __construct() {
        $this->container = new Container;
    }

    public function run() {
        $router = $this->container->getRouter();

        $request = $this->container->getRequest();
        $currentUser = $this->container->getCurrentUser();

        $tpl = $this->container->getTpl();
        $tpl->set('currentUser', $currentUser);
        $tpl->set('_csrfToken', fn() => $router->createCsrfToken());
        $tpl->set('_timeZone', new \DateTimeZone('Europe/Berlin'));

        $router->dispatch($request, $currentUser)->send();
    }
}
