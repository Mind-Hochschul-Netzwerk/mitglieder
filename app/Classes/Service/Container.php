<?php
declare(strict_types=1);
namespace App\Service;

use App\Controller\Controller;
use App\Model\Agreement;
use App\Model\User;
use App\Repository\AgreementRepository;
use App\Repository\UserAgreementRepository;
use App\Repository\UserRepository;
use Hengeb\Db\Db;
use Hengeb\Router\Exception\InvalidRouteException;
use Hengeb\Router\Router;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

/**
 * Service container
 */
class Container
{
    private array $instances;

    public function getAgreementRepository(): AgreementRepository
    {
        return $this->createService('AgreementRepository', fn() => new AgreementRepository(
            $this->getDb(),
        ));
    }

    public function getCurrentUser(): CurrentUser
    {
        return $this->createService('CurrentUser', fn() => new CurrentUser($this->getRequest(), $this->getUserRepository()));
    }

    public function getDb(): Db
    {
        return $this->createService('Db', fn() => new Db([
            'host' => getenv('MYSQL_HOST') ?: 'localhost',
            'port' => getenv('MYSQL_PORT') ?: 3306,
            'user' => getenv('MYSQL_USER'),
            'password' => getenv('MYSQL_PASSWORD'),
            'database' => getenv('MYSQL_DATABASE') ?: 'database',
        ]));
    }

    public function getEmailService(): EmailService
    {
        return $this->createService('EmailService', function () {
            $emailService = new EmailService(
                host: getenv('SMTP_HOST'),
                user: getenv('SMTP_USER'),
                password: getenv('SMTP_PASSWORD'),
                secure: getenv('SMTP_SECURE'),
                port: getenv('SMTP_PORT'),
                fromAddress: getenv('FROM_ADDRESS'),
                domain: getenv('DOMAINNAME'),
            );
            $emailService->setLdap($this->getLdap());
            return $emailService;
        });
    }

    public function getLdap(): Ldap
    {
        return $this->createService('Ldap', fn() => new Ldap(
            host: getenv('LDAP_HOST'),
            bindDn: getenv('LDAP_BIND_DN'),
            bindPassword: getenv('LDAP_BIND_PASSWORD'),
            peopleDn: getenv('LDAP_PEOPLE_DN'),
            groupsDn: getenv('LDAP_GROUPS_DN'),
        ));
    }

    public function getRequest(): Request
    {
        return $this->createService('Request', function () {
            $request = Request::createFromGlobals();
            $request->setSession(new Session());
            return $request;
        });
    }

    public function getRouter(): Router
    {
        return $this->createService('Router', function () {
            $router = new Router(__DIR__ . '/../Controller');

            $router->addExceptionHandler(InvalidRouteException::class, [Controller::class, 'handleException']);

            $router->addService(self::class, $this);
            $router->addService(CurrentUser::class, fn() => $this->getCurrentUser());
            $router->addService(Db::class, fn() => $this->getDb());
            $router->addService(Tpl::class, fn() => $this->getTpl());
            $router->addService(Request::class, fn() => $this->getRequest());
            $router->addService(EmailService::class, fn() => $this->getEmailService());
            $router->addService(UserRepository::class, fn() => $this->getUserRepository());
            $router->addService(UserAgreementRepository::class, fn() => $this->getUserAgreementRepository());
            $router->addService(AgreementRepository::class, fn() => $this->getAgreementRepository());
            $router->addService(Ldap::class, fn() => $this->getLdap());

            $router->addType(User::class, fn($name) => match($name) {
                '_', 'self' => $this->getCurrentUser()->getWrappedUser(),
                default => $this->getUserRepository()->findOneByUsername($name)
            }, 'username');
            $router->addType(User::class, fn($id) => $this->getUserRepository()->findOneById((int) $id), 'id');
            $router->addType(Agreement::class, fn($id) => $this->getAgreementRepository()->findOneById((int) $id));

            return $router;
        });
    }

    public function getTpl(): Tpl
    {
        return $this->createService('Tpl', fn() => new Tpl('/var/www/Resources/Private/Templates'));
    }

    public function getUserAgreementRepository(): UserAgreementRepository
    {
        return $this->createService('UserAgreementRepository', fn() => new UserAgreementRepository(
            $this->getDb(),
            $this->getUserRepository(),
            $this->getAgreementRepository()
        ));
    }

    public function getUserRepository(): UserRepository
    {
        return $this->createService('UserRepository', fn() => new UserRepository($this->getLdap(), $this->getDb()));
    }

    public function getService(string $class): object
    {
        $class = basename(str_replace('\\', '/', $class)); // C
        return $this->{'get' . $class}();
    }

    private function createService(string $classname, ?callable $setup = null): object
    {
        return $this->instances[$classname] ??= ($setup ? $setup() : new $classname);
    }
}
