<?php
/**
 * @author Henrik Gebauer <henrik@mind-hochschul-netzwerk.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

declare(strict_types=1);
namespace App;

use App\Controller\Controller;
use App\Model\Agreement;
use App\Model\Group;
use App\Model\User;
use App\Repository\AgreementRepository;
use App\Repository\GroupRepository;
use App\Repository\UserAgreementRepository;
use App\Repository\UserRepository;
use App\Service\CurrentUser;
use App\Service\EmailService;
use App\Service\Ldap;
use App\Service\RateLimiter;
use App\Service\Listmonk;
use Hengeb\Router\Exception\InvalidRouteException;
use Hengeb\Router\Interface\CurrentUserInterface;
use Hengeb\Router\LatteExtension;
use Hengeb\Router\ServiceContainer;

/**
 * Service container
 */
class Bootstrap extends ServiceContainer {
    public function __construct()
    {
        parent::__construct();
        $this->startDebugger();
        $this->registerService(CurrentUserInterface::class, fn() => $this->getCurrentUser());
        $this->getService(LatteExtension::class)->timezone = 'Europe/Berlin';
        $this->getRouter()
            ->addExceptionHandler(InvalidRouteException::class, [Controller::class, 'handleException'])
            ->addType(User::class, fn($name) => match($name) {
                '_', 'self' => $this->getCurrentUser()->getWrappedUser(),
                default => $this->getUserRepository()->findOneByUsername($name)
            }, 'username')
            ->addType(User::class, fn($id) => $this->getUserRepository()->findOneById((int) $id), 'id')
            ->addType(Agreement::class, fn($id) => $this->getAgreementRepository()->findOneById((int) $id))
            ->addType(Group::class, fn($name) => $this->getGroupRepository()->findOneByName($name), 'name');
    }

    public function getCurrentUser(): CurrentUser
    {
        return $this->createService(CurrentUser::class, fn() => new CurrentUser($this->getRequest(), $this->getUserRepository()));
    }

    public function getAgreementRepository(): AgreementRepository
    {
        return $this->createService(AgreementRepository::class, fn() => new AgreementRepository(
            $this->getService(\Hengeb\Db\Db::class),
        ));
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

    public function getListmonk(): Listmonk
    {
        return $this->createService(Listmonk::class, fn() => new Listmonk(
            baseUrl: getenv('LISTMONK_URL') ?: '',
            apiUser: getenv('LISTMONK_USER') ?: '',
            apiToken: getenv('LISTMONK_TOKEN') ?: '',
            listId: (int) (getenv('LISTMONK_LIST_ID') ?: 0),
        ));
    }

    public function getUserAgreementRepository(): UserAgreementRepository
    {
        return $this->createService(UserAgreementRepository::class, fn() => new UserAgreementRepository(
            $this->getService(\Hengeb\Db\Db::class),
            $this->getUserRepository(),
            $this->getAgreementRepository()
        ));
    }

    public function getGroupRepository(): GroupRepository
    {
        return $this->createService(GroupRepository::class, fn() => new GroupRepository(
            $this->getLdap()
        ));
    }

    public function getRateLimiter(): RateLimiter
    {
        return $this->createService(RateLimiter::class, fn() => new RateLimiter(
            $this->getService(\Hengeb\Db\Db::class)
        ));
    }

    public function getUserRepository(): UserRepository
    {
        return $this->createService(UserRepository::class, fn() => new UserRepository(
            $this->getLdap(),
            $this->getService(\Hengeb\Db\Db::class)
        ));
    }
}
