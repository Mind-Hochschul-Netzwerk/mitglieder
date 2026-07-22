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
        // public host of the IdP (e.g. "sso.example.com"), used only to make Authelia (or a similar
        // IdP behind a TLS-terminating reverse proxy) accept and correctly resolve requests that are
        // actually sent to the internal $providerUrl; leave null if $providerUrl is directly public
        ?string $publicProviderHost = null,
    ) {
        $this->client = new class($providerUrl, $clientId, $clientSecret, $publicProviderHost) extends OpenIDConnectClient {
            public function __construct(
                string $providerUrl,
                string $clientId,
                string $clientSecret,
                private readonly ?string $publicProviderHost,
            ) {
                parent::__construct($providerUrl, $clientId, $clientSecret);
            }

            // capture the authorization redirect instead of header()+exit so it fits the framework's response flow
            public function redirect(string $url): never {
                throw new OidcRedirectException($url);
            }

            // Authelia derives and validates its issuer strictly from the Host header + X-Forwarded-Proto
            // (must match session.cookies[].authelia_url). Since we talk to it directly on its internal,
            // TLS-free address, spoof both so it treats the request as coming through the public HTTPS host.
            protected function fetchURL(string $url, ?string $post_body = null, array $headers = []): bool|string {
                if ($this->publicProviderHost !== null) {
                    $headers[] = 'Host: ' . $this->publicProviderHost;
                    $headers[] = 'X-Forwarded-Proto: https';
                }
                return parent::fetchURL($url, $post_body, $headers);
            }

            // authorization_endpoint is correctly public already (browser-facing, discovered via the
            // spoofed headers above); token/jwks/userinfo are server-to-server and must stay on the
            // internally reachable host, so pull their path back onto $providerUrl
            /**
             * @return string|string[]|bool
             */
            protected function getProviderConfigValue(string $param, $default = null): string|array|bool {
                $value = parent::getProviderConfigValue($param, $default);
                if ($this->publicProviderHost !== null && is_string($value)
                    && in_array($param, ['token_endpoint', 'jwks_uri', 'userinfo_endpoint'], true)
                ) {
                    $value = rtrim($this->getProviderURL(), '/') . parse_url($value, PHP_URL_PATH);
                }
                return $value;
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
