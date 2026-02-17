<?php
declare(strict_types=1);
namespace App\Service;

use App\Model\User;
use App\Repository\UserRepository;
use Hengeb\Router\Exception\NotLoggedInException;
use Hengeb\Router\Interface\CurrentUserInterface;
use Symfony\Component\HttpFoundation\Request;
use Tracy\Debugger;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

/**
 * represents the Current User
 */
class CurrentUser implements CurrentUserInterface {
    private ?User $user = null;

    public function __construct(
        private Request $request,
        private UserRepository $userRepository,
    ) {
        $id = $request->getSession()->get('id');
        $this->user = $id ? $this->userRepository->findOneById($id) : null;
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

    public function isProductionMode(): bool
    {
        return Debugger::$productionMode;
    }

    public function hasRole(string $roleName): bool
    {
        static $cache = [];
        if (!$this->user) {
            return false;
        }
        return $cache[$roleName] ??= $this->user->hasRole($roleName);
    }

    public function hasId(int $id): bool
    {
        if (!$this->user) {
            return false;
        }
        return $this->user->get('id') === $id;
    }

    public function logIn(User $user)
    {
        $this->request->getSession()->set('id', $user->get('id'));
        $this->user = $user;
        $this->user->set('last_login', 'now');
        $this->userRepository->save($user);
    }

    public function logOut(): void
    {
        $this->request->getSession()->remove('id');
        $this->user = null;
    }

    public function getWrappedUser(): ?User
    {
        return $this->user;
    }
}
