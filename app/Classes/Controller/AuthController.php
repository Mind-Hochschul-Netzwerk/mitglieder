<?php
declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use App\Auth;
use App\Controller\Exception\InvalidUserDataException;
use App\Mitglied;
use App\Service\AuthService;
use App\Service\Db;
use App\Service\Tpl;
use \Hengeb\Token\Token;

class AuthController extends Controller {
    public function getResponse(): Response {
        if ($this->path[1] === 'login' && $this->request->isMethod('POST')) {
            return $this->loginSubmitted();
        } elseif ($this->path[1] === 'logout') {
            return $this->logout();
        } elseif ($this->path[1] === 'lost-password') {
            $token = $this->request->getPayload()->getString('token');
            $user = $this->validatePasswordToken($token);
            if ($this->request->isMethod('POST')) {
                return $this->resetPassword($user);
            } else {
                return $this->resetPasswordForm();
            }
        } else {
            return $this->loginForm();
        }
    }

    public function loginForm(): Response {
        return $this->render('AuthController/login');
    }

    public function login(): Response {
        if ($this->request->isMethod('POST')) {
            return $this->loginSubmitted();
        } else {
            return $this->loginForm();
        }
    }

    public function loginSubmitted(): Response {
        $input = $this->validatePayload([
            'id' => 'required string',
            'password' => 'required string untrimmed',
            'passwort_vergessen' => 'set',
        ]);

        if (!$input['id'] && $input['password']) {
            $this->setTemplateVariable('error_username_leer', true);
            return $this->loginForm();
        }

        $id = Db::getInstance()->query('SELECT id FROM mitglieder WHERE id=:id OR username=:username OR email=:email', [
            'id' => intval($input['id']),
            'username' => $input['id'],
            'email' => $input['id'],
        ])->get();

        if ($input['passwort_vergessen']) {
            return $this->lostPassword($id);
        }

        if ($id === null || !AuthService::checkPassword($input['password'], intval($id))) {
            $this->setTemplateVariable('error_passwort_falsch', true);
            return $this->loginForm();
        }

        AuthService::logIn(intval($id));
        return $this->redirect($this->request->getUri());
    }

    public function logout(): Response {
        AuthService::logOut();
        return $this->render('AuthController/logout');
    }

    private function lostPassword(?int $id): Response {
        if ($id !== null) {
            $m = Mitglied::lade((int)$id, true);
            $token = Token::encode([
                time(),
                $m->get('id')
            ], $m->get('hashedPassword'), getenv('TOKEN_KEY'));

            $text = Tpl::getInstance()->render('mails/lost-password', [
                'fullName' => $m->get('fullName'),
                'url' => 'https://mitglieder.' . getenv('DOMAINNAME') . '/lost-password?token=' . $token,
            ]);

            try {
                $m->sendEmail('Passwort vergessen', $text);
            } catch (\RuntimeException $e) {
                return new Response("Fehler beim Versenden der E-Mail.");
            }
        }

        $this->setTemplateVariable('lost_password', true);
        return $this->loginForm();
    }

    private function validatePasswordToken(string $token): Mitglied {
        try {
            Token::decode($_REQUEST['token'], function ($data) use (&$user) {
                if (time() - $data[0] > 24*60*60) {
                    throw new \Exception('token expired');
                }
                $user = Mitglied::lade($data[1], true);
                return $user->get('hashedPassword');
            }, getenv('TOKEN_KEY'));
        } catch (\Exception $e) {
            throw new InvalidUserDataException('Der Link ist abgelaufen oder ungültig.');
        }
        return $user;
    }

    private function resetPasswordForm(): Response {
        return $this->render('AuthController/lost-password');
    }

    private function resetPassword(Mitglied $m): Response {
        $input = $this->validatePayload([
            'password' => 'required string untrimmed',
            'password2' => 'required string untrimmed',
        ]);

        if ($input['password'] !== $input['password2']) {
            $this->setTemplateVariable('wiederholung_falsch', true);
            return $this->resetPasswordForm();
        }

        $m->set('password', $input['password']);
        $m->save();
        AuthService::login($m->get('id'));
        return $this->redirect('/');
    }
}
