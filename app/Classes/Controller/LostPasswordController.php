<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\User;
use App\Repository\UserRepository;
use App\Service\EmailService;
use App\Service\RateLimiter;
use Hengeb\Router\Attribute\AllowIf;
use Hengeb\Router\Attribute\PublicAccess;
use Hengeb\Router\Attribute\RequestValue;
use Hengeb\Router\Attribute\Route;
use Hengeb\Router\Exception\InvalidUserDataException;
use Hengeb\Token\Token;
use Symfony\Component\HttpFoundation\Response;

class LostPasswordController extends Controller {
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    #[Route('GET /lost-password/'), PublicAccess]
    public function lostPasswordRequest(): Response {
        return $this->render('LostPasswordController/request', [
            'sent' => $this->request->query->getBoolean('sent'),
        ]);
    }

    #[Route('POST /lost-password'), PublicAccess]
    public function submitLostPasswordRequest(#[RequestValue] string $login, EmailService $emailService, RateLimiter $rateLimiter): Response {
        try {
             $user = match(true) {
                str_contains($login, '@') => $this->userRepository->findOneByEmail($login),
                ctype_digit($login) => $this->userRepository->findOneById((int) $login),
                default => $this->userRepository->findOneByUsername($login),
            };

            if (!$user) {
                throw new InvalidUserDataException('user not found');
            }

            // wirft ebenfalls InvalidUserDataException, wenn zu viele Versuche in kurzer Zeit stattfinden
            $rateLimiter->attempt('lost-password', $user->get('username'), 5, 60*60);

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
                $emailService->sendToUser($user, $return->subject, $text);
            } catch (\RuntimeException $e) {
                return new Response("Fehler beim Versenden der E-Mail.");
            }
        } catch (InvalidUserDataException $e) {
            // don't reveal whether the username exists or not
        }

        if ($this->isJsonResponse) {
            return $this->json(['success' => true]);
        } else {
            return $this->redirect('/lost-password?sent=1');
        }
    }

    private function validatePasswordToken(string $token): User {
        try {
            Token::decode($token, function ($data) use (&$user) {
                if (time() - $data[0] > 24*60*60) {
                    throw new \Exception('token expired');
                }
                $user = $this->userRepository->findOneById($data[1]);
                return $user->get('hashedPassword');
            }, getenv('TOKEN_KEY'));
        } catch (\Exception $e) {
            throw new InvalidUserDataException('Der Link ist abgelaufen oder ungültig.');
        }
        assert($user instanceof User);
        return $user;
    }

    #[Route('GET /lost-password/test'), AllowIf(productionMode: false)]
    public function lostPasswordFormTest(): Response {
        return $this->render('LostPasswordController/new-password', [
            'vorname' => 'Max',
        ]);
    }

    #[Route('GET /lost-password?token={token}'), PublicAccess]
    public function resetPasswordForm(string $token): Response {
        $user = $this->validatePasswordToken($token);
        return $this->render('LostPasswordController/new-password', [
            'vorname' => $user->get('vorname'),
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
