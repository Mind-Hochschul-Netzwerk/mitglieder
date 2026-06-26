<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\User;
use App\Repository\AgreementRepository;
use App\Repository\UserAgreementRepository;
use App\Repository\UserRepository;
use App\Service\OpenIdConnect;
use Hengeb\Router\Attribute\PublicAccess;
use Hengeb\Router\Attribute\Route;
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

    #[Route('GET /logout'), PublicAccess]
    public function logout(): Response {
        $this->currentUser->logOut();
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
}
