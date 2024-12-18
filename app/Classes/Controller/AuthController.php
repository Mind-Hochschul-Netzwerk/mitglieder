<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\User;
use App\Repository\UserRepository;
use App\Service\CurrentUser;
use App\Service\Tpl;
use Hengeb\Db\Db;
use Hengeb\Router\Attribute\Route;
use Hengeb\Router\Exception\InvalidUserDataException;
use Hengeb\Token\Token;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller {
    #[Route('GET /login', allow: true)]
    public function loginForm(): Response {
        if (CurrentUser::getInstance()->isLoggedIn()) {
            return $this->redirect('/');
        }
        $redirect = $this->request->getPathInfo();
        return $this->render('AuthController/login', [
            'redirect' => $redirect,
            'id' => '',
            'password' => '',
        ]);
    }

    #[Route('POST /login', allow: true)]
    public function loginSubmitted(Db $db, CurrentUser $currentUser): Response {
        $input = $this->validatePayload([
            'id' => 'required string',
            'password' => 'required string untrimmed',
            'passwort_vergessen' => 'set',
            'redirect' => 'required string',
        ]);

        if (!$input['id'] && $input['password']) {
            $this->setTemplateVariable('error_username_leer', true);
            return $this->render('AuthController/login', [
                'redirect' => $input['redirect'],
                'id' => '',
                'password' => '',
            ]);
        }

        $id = $db->query('SELECT id FROM mitglieder WHERE id=:id OR username=:username OR email=:email', [
            'id' => intval($input['id']),
            'username' => $input['id'],
            'email' => $input['id'],
        ])->get();

        if ($input['passwort_vergessen']) {
            return $this->lostPassword($id);
        }

        $user = null;
        if ($id !== null) {
            $user = UserRepository::getInstance()->findOneById(intval($id));
            if ($user && !$user->checkPassword($input['password'])) {
                $user = null;
            }
        }

        if (!$user) {
            $this->setTemplateVariable('error_passwort_falsch', true);
            return $this->render('AuthController/login', [
                'redirect' => $input['redirect'],
                'id' => $input['id'],
                'password' => '',
            ]);
        }

        $redirectUrl = preg_replace('/\s/', '', $input['redirect']);

        $currentUser->logIn($user);
        return $this->redirect($redirectUrl);
    }

    #[Route('GET /logout', allow: true)]
    public function logout(CurrentUser $user): Response {
        $user->logOut();
        return $this->render('AuthController/logout');
    }

    private function lostPassword(?int $id): Response {
        // do not tell the user if $id === null
        if ($id !== null) {
            $user = UserRepository::getInstance()->findOneById((int)$id);
            $token = Token::encode([
                time(),
                $user->get('id')
            ], $user->get('hashedPassword'), getenv('TOKEN_KEY'));

            $text = Tpl::getInstance()->render('mails/lost-password', [
                'fullName' => $user->get('fullName'),
                'url' => 'https://mitglieder.' . getenv('DOMAINNAME') . '/lost-password?token=' . $token,
            ], $subject);

            try {
                $user->sendEmail($subject, $text);
            } catch (\RuntimeException $e) {
                return new Response("Fehler beim Versenden der E-Mail.");
            }
        }

        $this->setTemplateVariable('lost_password', true);
        return $this->loginForm();
    }

    private function validatePasswordToken(string $token): User {
        try {
            Token::decode($token, function ($data) use (&$user) {
                if (time() - $data[0] > 24*60*60) {
                    throw new \Exception('token expired');
                }
                $user = UserRepository::getInstance()->findOneById($data[1], true);
                return $user->get('hashedPassword');
            }, getenv('TOKEN_KEY'));
        } catch (\Exception $e) {
            throw new InvalidUserDataException('Der Link ist abgelaufen oder ungültig.');
        }
        return $user;
    }

    #[Route('GET /lost-password?token={token}', allow: true)]
    public function resetPasswordForm(string $token): Response {
        $user = $this->validatePasswordToken($token);
        return $this->render('AuthController/lost-password', [
            'password' => '',
            'password2' => '',
        ]);
    }

    #[Route('POST /lost-password?token={token}', allow: true)]
    public function resetPassword(string $token, CurrentUser $currentUser): Response {
        $user = $this->validatePasswordToken($token);

        $input = $this->validatePayload([
            'password' => 'required string untrimmed',
            'password2' => 'required string untrimmed',
        ]);

        if ($input['password'] !== $input['password2']) {
            $this->setTemplateVariable('wiederholung_falsch', true);
            return $this->resetPasswordForm($token);
        }

        $user->set('password', $input['password']);
        UserRepository::getInstance()->save($user);
        $currentUser->logIn($user);

        return $this->redirect('/');
    }

    public static function handleNotLoggedInException(\Exception $e, Request $request): Response {
        return (new self($request))->loginForm();
    }
}
