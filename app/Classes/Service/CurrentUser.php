<?php
declare(strict_types=1);
namespace App\Service;

use App\Interfaces\Singleton;
use App\Model\User;
use App\Repository\UserRepository;
use App\Router\Exception\NotLoggedInException;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

/**
 * represents the Current User
 */
class CurrentUser implements Singleton {
    use \App\Traits\Singleton;

    private static Request $request;
    private ?User $user = null;

    public static function setRequest(Request $request): void
    {
        static::$request = $request;
        $request->getSession()->start();
    }

    private function __construct()
    {
        $id = static::$request->getSession()->get('id');
        if ($id) {
            $this->user = UserRepository::getInstance()->findOneById($id);
        }
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
            return false;
        }
        return $this->user->hasRole($roleName);
    }

    public function logIn(User $user)
    {
        static::$request->getSession()->set('id', $user->get('id'));
        $this->user = $user;
        $this->user->set('last_login', 'now');
        UserRepository::getInstance()->save($user);
    }

    public function logOut(): void
    {
        $this->user = null;
        static::$request->getSession()->remove('id');
    }
}
