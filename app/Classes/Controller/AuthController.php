<?php
declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use App\Controller\Exception\InvalidUserDataException;
use App\Mitglied;
use App\Service\AuthService;
use App\Service\Db;
use App\Service\Tpl;
use \Hengeb\Token\Token;

class AuthController extends Controller {
    public function loginForm(): Response {
        return $this->render('AuthController/login', ['redirectUrl' => $this->request->getPathInfo()]);
    }

    public function loginSubmitted(): Response {
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

        $redirectUrl = preg_replace('/\s/', '', $input['redirect']);

        AuthService::logIn(intval($id));
        return $this->redirect($redirectUrl);
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

    public function resetPasswordForm(string $token): Response {
        $user = $this->validatePasswordToken($token);
        return $this->render('AuthController/lost-password');
    }

    public function resetPassword(string $token): Response {
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
        $user->save();
        AuthService::login($user->get('id'));
        return $this->redirect('/');
    }
}
