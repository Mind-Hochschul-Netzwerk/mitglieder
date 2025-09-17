<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\User;
use App\Repository\UserRepository;
use App\Service\EmailService;
use Hengeb\Router\Attribute\PublicAccess;
use Hengeb\Router\Attribute\RequestValue;
use Hengeb\Router\Attribute\Route;
use Hengeb\Router\Exception\InvalidUserDataException;
use Hengeb\Token\Token;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller {
    public function __construct(
        private UserRepository $userRepository,
        private EmailService $emailService,
    ) {}

    #[Route('GET /login'), PublicAccess]
    public function loginForm(): Response {
        if ($this->currentUser->isLoggedIn()) {
            return $this->redirect('/');
        }
        $redirect = $this->request->getPathInfo();
        return $this->render('AuthController/login', [
            'redirect' => $redirect,
            'login' => '',
            'password' => '',
        ]);
    }

    #[Route('POST /login'), PublicAccess]
    public function loginSubmitted(#[RequestValue] string $login, #[RequestValue] string $password, #[RequestValue] string $redirect, #[RequestValue] bool $passwort_vergessen = false): Response {
        if (!$login) {
            $this->setTemplateVariable('error_username_leer', true);
            return $this->render('AuthController/login', [
                'redirect' => $redirect,
                'login' => '',
                'password' => '',
            ]);
        }

        $user = match(true) {
            str_contains($login, '@') => $this->userRepository->findOneByEmail($login),
            ctype_digit($login) => $this->userRepository->findOneById((int) $login),
            default => $this->userRepository->findOneByUsername($login),
        };

        if ($passwort_vergessen) {
            return $this->lostPassword($user);
        }

        if (!$user?->checkPassword($password)) {
            $user = null;
        }

        if (!$user) {
            return $this->render('AuthController/login', [
                'redirect' => $redirect,
                'login' => $login,
                'password' => '',
                'error_passwort_falsch' => true,
            ]);
        }

        $redirectUrl = preg_replace('/\s/', '', $redirect);

        $this->currentUser->logIn($user);
        return $this->redirect($redirectUrl);
    }

    #[Route('GET /logout'), PublicAccess]
    public function logout(): Response {
        $this->currentUser->logOut();
        return $this->render('AuthController/logout');
    }

    private function lostPassword(?User $user): Response {
        if ($user) {
            $token = Token::encode([
                time(),
                $user->get('id')
            ], $user->get('hashedPassword'), getenv('TOKEN_KEY'));

            $text = $this->renderToString('mails/lost-password', [
                'fullName' => $user->get('fullName'),
                'url' => 'https://mitglieder.' . getenv('DOMAINNAME') . '/lost-password?token=' . $token,
                'return' => $return = new \stdclass,
            ]);

            try {
                $this->emailService->sendToUser($user, $return->subject, $text);
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
                $user = $this->userRepository->findOneById($data[1], true);
                return $user->get('hashedPassword');
            }, getenv('TOKEN_KEY'));
        } catch (\Exception $e) {
            throw new InvalidUserDataException('Der Link ist abgelaufen oder ungültig.');
        }
        return $user;
    }

    #[Route('GET /lost-password?token={token}'), PublicAccess]
    public function resetPasswordForm(string $token): Response {
        $user = $this->validatePasswordToken($token);
        return $this->render('AuthController/lost-password', [
            'password' => '',
            'password2' => '',
        ]);
    }

    #[Route('POST /lost-password?token={token}'), PublicAccess]
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

        $user->setPassword($input['password']);
        $this->userRepository->save($user);
        $this->currentUser->logIn($user);

        return $this->redirect('/');
    }
}
