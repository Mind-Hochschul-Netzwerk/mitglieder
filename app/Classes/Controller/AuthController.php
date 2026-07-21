<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\User;
use App\Repository\AgreementRepository;
use App\Repository\UserAgreementRepository;
use App\Repository\UserRepository;
use App\Service\EmailService;
use App\Service\OpenIdConnect;
use App\Service\RateLimiter;
use Hengeb\Router\Attribute\AllowIf;
use Hengeb\Router\Attribute\PublicAccess;
use Hengeb\Router\Attribute\RequestValue;
use Hengeb\Router\Attribute\Route;
use Hengeb\Router\Exception\InvalidUserDataException;
use Hengeb\Token\Token;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller {
    public function __construct(
        private UserRepository $userRepository,
        private AgreementRepository $agreementRepository,
        private UserAgreementRepository $userAgreementRepository,
        private OpenIdConnect $openIdConnect,
    ) {}

    /**
     * Single route for both the OIDC login initiation and the callback (redirect_uri).
     * It is also invoked by Controller::handleException for unauthenticated HTML requests,
     * in which case the originally requested path is preserved as the post-login target.
     */
    #[Route('GET /login'), PublicAccess]
    public function login(): Response {
        $session = $this->request->getSession();
        $isCallback = $this->request->query->has('code') || $this->request->query->has('error');

        if (!$isCallback) {
            $isStepUp = $this->request->query->getBoolean('stepup');
            if ($this->currentUser->isLoggedIn() && !$isStepUp) {
                return $this->redirect('/');
            }
            // remember where to send the user after a successful login
            $target = $this->request->getPathInfo() !== '/login'
                ? $this->request->getRequestUri()
                : ($this->request->query->getString('redirect') ?: '/');
            $session->set('oidc_redirect', $target);
            $session->set('oidc_stepup', $isStepUp);

            return $this->openIdConnect->authenticate(forceReauth: $isStepUp);
        }

        // callback from the IdP: validate the tokens
        $this->openIdConnect->authenticate();

        $isStepUp = (bool) $session->get('oidc_stepup', false);
        $session->remove('oidc_stepup');

        if (!$this->currentUser->isLoggedIn()) {
            $username = $this->openIdConnect->getUsername();
            $user = $username ? $this->userRepository->findOneByUsername($username) : null;
            if (!$user) {
                return $this->showError('Es wurde kein Mitgliedskonto zu diesem Login gefunden. Bitte wende dich an die Mitgliederverwaltung.', 403);
            }
            $this->currentUser->logIn($user);
        }

        if ($isStepUp) {
            $this->currentUser->recordStepUp();
        }

        $redirectUrl = $this->sanitizeLocalPath((string) $session->get('oidc_redirect', '/'));
        $session->remove('oidc_redirect');

        // apply the post-login special-case redirects only for the default target
        if ($redirectUrl === '/') {
            $redirectUrl = $this->getPostLoginRedirect($this->currentUser->getWrappedUser());
        }

        return $this->redirect($redirectUrl);
    }

    /**
     * Logs out of the app first, then routes the browser through Authelia's own logout page
     * (which clears its SSO session cookie) before landing back here to show the confirmation.
     * Without this detour, Authelia's session survives and the next login is silent SSO.
     */
    #[Route('GET /logout'), PublicAccess]
    public function logout(): Response {
        $this->currentUser->logOut();

        if (!$this->request->query->getBoolean('idpDone')) {
            $returnUrl = 'https://mitglieder.' . getenv('DOMAINNAME') . '/logout?idpDone=1';
            return $this->redirect('https://sso.' . getenv('DOMAINNAME') . '/logout?rd=' . urlencode($returnUrl));
        }

        return $this->render('AuthController/logout');
    }

    /**
     * Only allow local absolute paths as redirect targets to prevent open redirects.
     */
    private function sanitizeLocalPath(string $target): string {
        $target = preg_replace('/\s/', '', $target);
        if ($target === '' || $target[0] !== '/' || str_starts_with($target, '//')) {
            return '/';
        }
        return $target;
    }

    /**
     * Determines where to send the user right after login when no explicit target was requested.
     */
    private function getPostLoginRedirect(User $user): string {
        // 1. Priorität: E-Mail-Adresse als ungültig markiert
        if (str_ends_with(strtolower($user->get('email')), '.invalid')) {
            return '/user/self/edit';
        }
        // 2. Priorität: neue Version der Datenschutztexte
        if ($this->isAgreementsRedirectNeeded($user)) {
            return '/user/self/agreements';
        }
        // 3. Priorität: Profil wurde seit einem Jahr nicht aktualisiert
        if ($user->get('db_modified')->diff(new \DateTimeImmutable('now - 1 year'))->invert === 0) {
            return '/user/self/edit';
        }
        return '/';
    }

    private function isAgreementsRedirectNeeded(User $user): bool
    {
        // Kenntnisnahme und Einwilligung: Weiterleitung wenn noch nie zugestimmt oder neue Version vorliegt
        foreach (['Kenntnisnahme', 'Einwilligung'] as $name) {
            $latest = $this->agreementRepository->findLatestByName($name);
            if ($latest === null) {
                continue;
            }
            // findLatestByUserAndName gibt null zurück wenn nie zugestimmt oder widerrufen
            $userAgreement = $this->userAgreementRepository->findLatestByUserAndName($user, $name);
            if ($userAgreement === null || $userAgreement->agreement->version < $latest->version) {
                return true;
            }
        }

        // Datenschutzverpflichtung: nur Weiterleitung wenn bereits zugestimmt, aber eine neue Version vorliegt
        // (nie zugestimmt wird separat über das inline-Formular auf der Seite behandelt)
        $latest = $this->agreementRepository->findLatestByName('Datenschutzverpflichtung');
        $userAgreement = $this->userAgreementRepository->findLatestByUserAndName($user, 'Datenschutzverpflichtung');
        if ($latest !== null && $userAgreement !== null && $userAgreement->agreement->version < $latest->version) {
            return true;
        }

        return false;
    }

    #[Route('GET /lost-password/'), PublicAccess]
    public function lostPasswordRequest(): Response {
        return $this->render('AuthController/lost-password-request', [
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
        return $user;
    }

    #[Route('GET /lost-password/test'), AllowIf(productionMode: false)]
    public function lostPasswordFormTest(): Response {
        return $this->render('AuthController/lost-password', [
            'vorname' => 'Max',
        ]);
    }

    #[Route('GET /lost-password?token={token}'), PublicAccess]
    public function resetPasswordForm(string $token): Response {
        $user = $this->validatePasswordToken($token);
        return $this->render('AuthController/lost-password', [
            'vorname' => $user->get('vorname'),
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
