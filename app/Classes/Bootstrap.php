<?php
/**
 * @author Henrik Gebauer <henrik@mind-hochschul-netzwerk.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

declare(strict_types=1);
namespace App;

use App\Controller\Controller;
use App\Model\Agreement;
use App\Model\User;
use App\Repository\AgreementRepository;
use App\Repository\UserAgreementRepository;
use App\Repository\UserRepository;
use App\Service\CurrentUser;
use App\Service\EmailService;
use App\Service\LatteExtension;
use App\Service\Ldap;
use Hengeb\Db\Db;
use Hengeb\Router\Exception\InvalidRouteException;
use Hengeb\Router\Router;
use \Latte\Engine as Latte;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Tracy\Debugger;

/**
 * Service container
 */
class Bootstrap {
    private array $instances = [];

    public function run() {
        $this->startDebugger();

        $router = $this->getRouter();

        $router->addService(Db::class, fn() => $this->getDb());
        $router->addService(EmailService::class, fn() => $this->getEmailService());
        $router->addService(UserRepository::class, fn() => $this->getUserRepository());
        $router->addService(UserAgreementRepository::class, fn() => $this->getUserAgreementRepository());
        $router->addService(AgreementRepository::class, fn() => $this->getAgreementRepository());
        $router->addService(Ldap::class, fn() => $this->getLdap());

        $router->dispatch($this->getRequest(), $this->getCurrentUser())->send();
    }

    private function startDebugger(): void
    {
        Debugger::enable(str_ends_with(getenv('DOMAINNAME'), 'localhost') ? Debugger::Development : Debugger::Production);
    }

    private function createService(string $classname, ?callable $setup = null): object
    {
        return $this->instances[$classname] ??= ($setup ? $setup() : new $classname);
    }

    public function getService(string $class): object
    {
        $class = basename(str_replace('\\', '/', $class)); // C
        return $this->{'get' . $class}();
    }

    public function getAgreementRepository(): AgreementRepository
    {
        return $this->createService(AgreementRepository::class, fn() => new AgreementRepository(
            $this->getDb(),
        ));
    }

    public function getCurrentUser(): CurrentUser
    {
        return $this->createService(CurrentUser::class, fn() => new CurrentUser($this->getRequest(), $this->getUserRepository()));
    }

    public function getDb(): Db
    {
        return $this->createService(Db::class, fn() => new Db([
            'host' => getenv('MYSQL_HOST') ?: 'localhost',
            'port' => getenv('MYSQL_PORT') ?: 3306,
            'user' => getenv('MYSQL_USER'),
            'password' => getenv('MYSQL_PASSWORD'),
            'database' => getenv('MYSQL_DATABASE') ?: 'database',
        ]));
    }

    public function getEmailService(): EmailService
    {
        return $this->createService(EmailService::class, function () {
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
            $emailService->setUserRepository($this->getUserRepository());
            return $emailService;
        });
    }

    public function getLatte(): Latte
    {
        return $this->createService(Latte::class, function () {
            $latte = new Latte;
            $latte->setTempDirectory('/tmp/latte');
            $latte->setLoader(new \Latte\Loaders\FileLoader('/var/www/templates'));
            $latte->addExtension(new LatteExtension($this->getRouter(), $this->getCurrentUser()));
            return $latte;
        });
    }

    public function getLdap(): Ldap
    {
        return $this->createService(Ldap::class, fn() => new Ldap(
            host: getenv('LDAP_HOST'),
            bindDn: getenv('LDAP_BIND_DN'),
            bindPassword: getenv('LDAP_BIND_PASSWORD'),
            peopleDn: getenv('LDAP_PEOPLE_DN'),
            groupsDn: getenv('LDAP_GROUPS_DN'),
        ));
    }

    public function getRequest(): Request
    {
        return $this->createService(Request::class, function () {
            $request = Request::createFromGlobals();
            $request->setSession(new Session());
            return $request;
        });
    }

    public function getRouter(): Router
    {
        return $this->createService(Router::class, function () {
            $router = new Router(__DIR__ . '/Controller');

            $router->addExceptionHandler(InvalidRouteException::class, [Controller::class, 'handleException']);

            $router->addService(self::class, $this);
            $router->addService(CurrentUser::class, fn() => $this->getCurrentUser());
            $router->addService(Request::class, fn() => $this->getRequest());
            $router->addService(Latte::class, fn() => $this->getLatte());

            $router->addType(User::class, fn($name) => match($name) {
                '_', 'self' => $this->getCurrentUser()->getWrappedUser(),
                default => $this->getUserRepository()->findOneByUsername($name)
            }, 'username');
            $router->addType(User::class, fn($id) => $this->getUserRepository()->findOneById((int) $id), 'id');
            $router->addType(Agreement::class, fn($id) => $this->getAgreementRepository()->findOneById((int) $id));

            return $router;
        });
    }

    public function getUserAgreementRepository(): UserAgreementRepository
    {
        return $this->createService(UserAgreementRepository::class, fn() => new UserAgreementRepository(
            $this->getDb(),
            $this->getUserRepository(),
            $this->getAgreementRepository()
        ));
    }

    public function getUserRepository(): UserRepository
    {
        return $this->createService(UserRepository::class, fn() => new UserRepository($this->getLdap(), $this->getDb()));
    }
}
