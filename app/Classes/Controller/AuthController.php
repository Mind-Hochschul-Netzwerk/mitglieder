<?php
declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use App\Model\User;
use App\Repository\UserRepository;
use App\Router\Attribute\Route;
use App\Router\Exception\InvalidUserDataException;
use App\Service\CurrentUser;
use App\Service\Db;
use App\Service\Tpl;
use \Hengeb\Token\Token;

class AuthController extends Controller {
    #[Route('GET /login')]
    public function loginForm(): Response {
        return $this->render('AuthController/login', ['redirectUrl' => $this->request->getPathInfo()]);
    }

    #[Route('POST /login')]
    public function loginSubmitted(Db $db, CurrentUser $currentUser): Response {
        $input = $this->validatePayload([
            'id' => 'required string',
            'password' => 'required string untrimmed',
            'passwort_vergessen' => 'set',
            'redirect' => 'required string',
        ]);

        if (!$input['id'] && $input['password']) {
            $this->setTemplateVariable('error_username_leer', true);
            return $this->loginForm();
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
            return $this->loginForm();
        }

        $redirectUrl = preg_replace('/\s/', '', $input['redirect']);

        $currentUser->logIn($user);
        return $this->redirect($redirectUrl);
    }

    #[Route('GET /logout')]
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
            ]);

            try {
                $user->sendEmail('Passwort vergessen', $text);
            } catch (\RuntimeException $e) {
                return new Response("Fehler beim Versenden der E-Mail.");
            }
        }

        $this->setTemplateVariable('lost_password', true);
        return $this->loginForm();
    }

    private function validatePasswordToken(string $token): User {
        try {
            Token::decode($_REQUEST['token'], function ($data) use (&$user) {
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

    #[Route('GET /lost-password?token={token}')]
    public function resetPasswordForm(string $token): Response {
        $user = $this->validatePasswordToken($token);
        return $this->render('AuthController/lost-password');
    }

    #[Route('POST /lost-password?token={token}')]
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
}
