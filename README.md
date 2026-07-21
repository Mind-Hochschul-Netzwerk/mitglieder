# Mitglieder-Verwaltung

Die MHN-Mitgliederverwaltung (http://mitglieder.mind-hochschul-netzwerk.de)

## Container lokal bauen und starten

### Target "dev" (Entwicklung)

    $ composer install -d app
    $ make rebuild
    $ make dev

Die Anwendung ist dann im Browser unter [https://mitglieder.docker.localhost/](https://mitglieder.docker.localhost/) erreichbar. Die Sicherheitswarnung wegen des Zertifikates kann weggeklickt werden.

### Login (OpenID Connect)

Die Anmeldung erfolgt über einen externen OpenID-Connect-Provider (Identity Provider).
Dazu müssen in der `.env` folgende Werte gesetzt sein (siehe `env.sample`):

* `OIDC_PROVIDER_URL` – Issuer-URL des IdP, wie sie **aus dem app-Container heraus** erreichbar ist
  (muss `/.well-known/openid-configuration` bereitstellen); Discovery, Token-Exchange, JWKS- und
  Userinfo-Abruf laufen serverseitig gegen diese URL
* `OIDC_PUBLIC_URL` – optional; öffentliche Basis-URL des IdP, falls diese von `OIDC_PROVIDER_URL`
  abweicht (z.B. wenn der IdP intern nur ohne TLS unter seinem Docker-Servicenamen erreichbar ist,
  siehe `docker-compose.yml`). Nur der `authorization_endpoint` (Redirect des Browsers zum Login)
  wird auf diese URL umgeschrieben; fehlt sie, wird `OIDC_PROVIDER_URL` unverändert verwendet.
* `OIDC_CLIENT_ID`
* `OIDC_CLIENT_SECRET`

Die beim IdP zu registrierende Redirect-URI lautet `https://mitglieder.<DOMAINNAME>/login`.
Mitglieder werden über den Claim `preferred_username` (= LDAP-`cn` = Benutzername) dem
lokalen Konto zugeordnet.

Für die lokale Entwicklung ohne produktiven IdP empfiehlt es sich, einen Test-IdP
(z.B. Keycloak/Authentik) zu konfigurieren, dessen `preferred_username` zu einem
LDAP-Benutzernamen passt.

### Target "prod" (Production)

    $ make prod
