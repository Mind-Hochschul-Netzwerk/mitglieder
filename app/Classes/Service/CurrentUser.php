<?php
declare(strict_types=1);
namespace App\Service;

use App\Interfaces\Singleton;
use App\Model\User;
use App\Repository\UserRepository;
use App\Router\Exception\NotLoggedInException;
use App\Router\Interface\CurrentUserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

/**
 * represents the Current User
 */
class CurrentUser implements Singleton, CurrentUserInterface {
    use \App\Traits\Singleton;

    private ?Request $request = null;

    private ?User $user = null;

    public function setRequest(Request $request): void
    {
        $this->request = $request;

        if (!$request->hasSession()) {
            $request->setSession(new Session());
        }

        $id = $request->getSession()->get('id');
        $this->user = $id ? UserRepository::getInstance()->findOneById($id) : null;
    }

    private function assertLogin(): void
    {
        if (!$this->user) {
            throw new NotLoggedInException();
        }
    }

    public function __call($method, $arguments)
    {
        $this->assertLogin();
        return call_user_func_array([$this->user, $method], $arguments);
    }

    public function __get($property)
    {
        if (!$this->user) {
            return null;
        }
        return $this->user->$property;
    }

    public function __set($property, $value)
    {
        $this->assertLogin();
        $this->user->$property = $value;
    }

    public function isLoggedIn(): bool
    {
        return boolval($this->user);
    }

    public function hasRole(string $roleName): bool
    {
        if (!$this->user) {
            throw new NotLoggedInException();
        }
        return $this->user->hasRole($roleName);
    }

    public function hasId(int $id): bool
    {
        if (!$this->user) {
            throw new NotLoggedInException();
        }
        return $this->user->get('id') === $id;
    }

    public function logIn(User $user)
    {
        if (!$this->request) {
            throw new \LogicException('request is not set', 1729975906);
        }
        $this->request->getSession()->set('id', $user->get('id'));
        $this->user = $user;
        $this->user->set('last_login', 'now');
        UserRepository::getInstance()->save($user);
    }

    public function logOut(): void
    {
        if (!$this->request) {
            throw new \LogicException('request is not set', 1729975906);
        }
        $this->request->getSession()->remove('id');
        $this->user = null;
    }

    public function getWrappedUser(): ?User
    {
        return $this->user;
    }
}
