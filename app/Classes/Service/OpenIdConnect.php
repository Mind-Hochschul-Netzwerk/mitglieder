<?php
declare(strict_types=1);
namespace App\Service;

use Jumbojett\OpenIDConnectClient;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Thin wrapper around jumbojett/openid-connect-php that drives the
 * Authorization-Code-with-PKCE flow against a discovery-capable OIDC provider.
 */
class OpenIdConnect
{
    private OpenIDConnectClient $client;

    public function __construct(
        string $providerUrl,
        string $clientId,
        string $clientSecret,
        string $redirectUrl,
        private Request $request,
    ) {
        $this->client = new class($providerUrl, $clientId, $clientSecret) extends OpenIDConnectClient {
            // capture the authorization redirect instead of header()+exit so it fits the framework's response flow
            public function redirect(string $url) {
                throw new OidcRedirectException($url);
            }
        };
        $this->client->setRedirectURL($redirectUrl);
        // jumbojett always appends the mandatory "openid" scope itself
        $this->client->addScope(['profile', 'email']);
        $this->client->setCodeChallengeMethod('S256');
    }

    /**
     * Drives the OIDC flow:
     *  - on the initial request (no ?code) returns a RedirectResponse to the IdP
     *  - on the callback (?code present) validates the tokens and returns null
     *
     * @param bool $forceReauth force the IdP to re-authenticate the user (prompt=login), used for step-up
     * @throws \Jumbojett\OpenIDConnectClientException on validation errors or an error response from the IdP
     */
    public function authenticate(bool $forceReauth = false): ?RedirectResponse
    {
        // make sure the PHP session shared with jumbojett is started before it writes state/nonce/PKCE
        $this->request->getSession()->start();

        if ($forceReauth) {
            $this->client->addAuthParam(['prompt' => 'login']);
        }

        try {
            $this->client->authenticate();
        } catch (OidcRedirectException $e) {
            return new RedirectResponse($e->getUrl());
        }

        return null;
    }

    /**
     * The username of the authenticated user (OIDC preferred_username claim, mapped to the local username).
     * Only meaningful after a successful callback.
     */
    public function getUsername(): string
    {
        return (string) ($this->client->getVerifiedClaims('preferred_username')
            ?? $this->client->requestUserInfo('preferred_username'));
    }
}
