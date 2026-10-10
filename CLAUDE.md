# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

**Pflege dieser Datei:** Wenn eine Änderung hier Beschriebenes ungültig macht (neuer Service/Controller, andere Rollen, neue Umgebungsvariablen, geänderte Datenhaltung oder Befehle), aktualisiere diese CLAUDE.md im selben Zug mit – knapp, ohne Dateilisten.

## Überblick

MHN-Mitgliederverwaltung (PHP ≥ 8.4, Deutsch als Projekt-/UI-Sprache). Läuft als Docker-Container hinter Traefik; hängt an MariaDB, LDAP, einem OIDC-Provider (Authelia), SMTP, Listmonk und dem Mailinglisten-Dienst „listig“. Es gibt keine Tests und keinen Linter/Build-Schritt für PHP; CI (`.github/workflows/ci.yaml`) baut nur das Docker-Image und pusht es nach ghcr.io.

## Befehle

Alles per `Makefile` (liest `.env`, Vorlage: `env.sample`; benötigt laufenden `traefik`-Container):

- `composer install -d app` – Abhängigkeiten (vendor/ liegt in `app/`)
- `make rebuild` – Image bauen; `make dev` – Dev-Stack starten (mountet `./app` nach `/var/www`, Änderungen sofort live → https://mitglieder.docker.localhost/)
- `make prod` – Produktion (zieht Image von ghcr.io); `make upgrade` = git pull + prod
- `make shell` / `make rootshell` – Shell im app-Container; `make mysql` – DB-Shell; `make logs`; `make adminer`
- Syntaxcheck einer Datei: `php -l app/Classes/...`
- DB-Schema/Testdaten: `docker/sql/1.db.sql`, `2.test-data.sql` (werden nur beim ersten Start einer leeren DB eingespielt; Tabellen: `mitglieder`, `agreements`, `user_agreements`, `deleted_usernames`, `rate_limit`)

## Architektur

- **Front Controller**: `app/html/index.php` → `App\Bootstrap::dispatchRequest()`. `Bootstrap` ([app/Classes/Bootstrap.php](app/Classes/Bootstrap.php)) ist der Service-Container (erbt von `Hengeb\Router\ServiceContainer`): ein `getXyz()` pro Service mit `createService()`-Singleton, Konfiguration über `getenv()`. Dort werden auch die Router-Typen registriert (Parameter-Auflösung `{username=>user}`, `{name=>group}`, `{id}` → Model; `_`/`self` = aktueller User).
- **Routing per Attribut** (Bibliothek `hengeb/router`): Controller in `app/Classes/Controller/` erben von `Controller` und annotieren Methoden mit `#[Route('GET /pfad?token={token}')]` plus Zugriffsregeln `#[PublicAccess]`, `#[RequireLogin]`, `#[AllowIf(role: '…')]` (Rollen z. B. `mvedit`, `mvread`, `newsletter-export`, `groupadmin`, `datenschutz`), `AllowIf(productionMode: false)` für Dev-only-Routen, `CheckCsrfToken(false)`. Abhängigkeiten per `#[Inject]`-Properties. Neue Controller werden automatisch gefunden – Routen stehen nur in den Attributen (`grep -rn "#\[Route" app/Classes`).
- **Templates**: Latte in `app/templates/<ControllerName>/<name>.latte`, Layout `layout.latte`, Mails in `templates/mails/` (`*.mail.latte` für Mails aus Controller-Ordnern). Rendering über `Controller::renderToString` / `setTemplateVariable`.
- **Datenhaltung ist zweigeteilt**: Mitgliederdaten in MariaDB (`mitglieder`), Account/Login-Identität und Gruppen in **LDAP** (`Service/Ldap`, Symfony LDAP). `UserRepository` lädt DB-Zeile *und* LDAP-Eintrag und fügt sie im `Model\User` zusammen (`DevController` `/users/ldap-sync` gleicht ab). Gruppen (`Model\Group`, `GroupRepository`) liegen komplett in LDAP; Gruppen-Policies sind Enums in `Model/Enum/`. DB-Zugriff über `hengeb/db` (`$db->query(sql, params)->getRow()/getAll()`).
- **User-Modell** ([Model/User.php](app/Classes/Model/User.php)): Alle DB-Spalten stehen mit Defaults in `User::felder` (plus `User::AUFGABEN` für Aufgaben-Checkboxen); Zugriff über `get()/set()/setData()`, nicht über Properties. Neues Profilfeld = Spalte in `docker/sql/1.db.sql` + Eintrag in `felder` (+ ggf. `SearchController::felder`, Formular-Template, `AufnahmeController::MAP`). `vorname`/`nachname`/E-Mail/Passwort kommen aus LDAP (`givenName`, `sn`, `mail`, `userPassword`), nicht aus der DB-Spalte. `mail` ist multi-valued: Index 0 = Org-Adresse (nur wenn zwei Werte), sonst private Adresse (`Ldap::splitMail`); Login/Passwort-Reset nur über die private Adresse. Das Passwort wird im IdP/LDAP gesetzt (`Ldap` nutzt `ldap_exop_passwd`).
- **Rollen** = LDAP-Gruppen (`User::hasRole`): `user` immer, `rechte` ⇒ `mvedit` ⇒ `mvread`, `rechte` ⇒ `groupadmin`; sonst Gruppenmitgliedschaft mit dem Rollennamen. Ein User in `rechte` kann nicht gelöscht werden. Gelöschte Usernames landen in `deleted_usernames` und werden nicht neu vergeben.
- **Gruppen** ([GroupRepository](app/Classes/Repository/GroupRepository.php)): LDAP `groupOfNames`; Konfiguration steckt als `key:value`-Zeilen im `description`-Attribut (Mapping `DESCRIPTION_KEY_MAP`, unbekannte Zeilen bleiben erhalten). Leere Gruppen bekommen die Bind-DN als Platzhalter-Member. Mailinglisten-Gruppen (`mail`-Attribut + Listen-Passwort) nutzen `ListigApi::encryptPassword`; Listig schreibt das Chiffrat selbst ins LDAP. In der Gruppenansicht ist die Adresse als `mailto:` klickbar (plus Action-Link „Mail schreiben“), wenn `Group::canWriteMail()` (genutzt in Einzelansicht und Karte der Übersicht) zutrifft: keine Mailingliste, oder Owner, oder Mitglied mit `postAccessMembers` ≠ deny, oder `postAccessPublic` ≠ deny. `ReplyToBehavior` spiegelt die `reply-to`-Werte von listig (inkl. `masked-sender`/`masked-both`); das Compose-Formular von listig ist noch nicht angebunden.
- **Fehlerbehandlung**: `Controller::handleException` (im Bootstrap registriert) mappt Router-Exceptions auf Statuscodes/Fehlerseite bzw. JSON (je nach `ResponseType`); nicht eingeloggte Zugriffe werden an `AuthController::login` weitergereicht. Eingaben validiert `Controller::validatePayload([...'string required'...])`. Template-Warnungen „Undefined variable/array key/property" werden in `renderToString` bewusst ignoriert, andere Fehler werfen.
- **Auth**: Login über OpenID Connect (`Service/OpenIdConnect`, `AuthController` Route `/login`); Zuordnung über Claim `preferred_username` = LDAP-`cn`. Session-ID des Users → `Service/CurrentUser` (Proxy auf `User`, wirft `NotLoggedInException` bei Methodenaufruf ohne Login). `/login` ist zugleich Start und OIDC-Callback; Redirect-Ziel in der Session (`sanitizeLocalPath` gegen Open Redirect). Step-up-Reauth (`?stepup=1`, `CurrentUser::hasRecentStepUp()`) wird vor sensiblen Aktionen wie Austritt verlangt. Logout läuft über `sso.<DOMAIN>/logout`. Tokens (E-Mail-Bestätigung, Passwort-Reset, Aufnahme) via `hengeb/token` mit `TOKEN_KEY`.
- **Mailbox-Login (Mailu)**: `app/html/mailu-login.php` ist bewusst *außerhalb* des Routers (zeitkritische Middleware, keine Session); verifiziert HMAC-Token (gleiches `TOKEN_KEY`-Format) und nimmt Authelias `Remote-User`-Header. Token-Ausgabe/Auswahl in `MailboxSelectorController` (Env `MAILBOX_EXCLUDE`: kommagetrennte Adressen/Aliase, die nicht als Postfach angeboten werden; die Auswahlseite ruft per JS vorab `mail.<DOMAIN>/sso/logout` auf, damit Mailu eine alte Session verwirft).
- **Integrationen**: `Service/Listmonk` (Newsletter-Sync, Cron-Endpoint `GET /cron/listmonk-sync?token=LISTMONK_SYNC_TOKEN`, deaktiviert wenn `LISTMONK_URL` leer), `Service/ListigApi` (Mailinglisten-Passwörter, `http://listig`), `Service/EmailService` (PHPMailer), `Service/ImageResizer` + `ProfilePictureController` (Profilbilder im Volume `profilbilder`), `Service/RateLimiter` (Tabelle `rate_limit`, Aufräumen bei jedem Request im Bootstrap).
- **Weitere Controller**: `UserController` (Profil anzeigen/bearbeiten, E-Mail-Änderung mit Token, Austritt/Löschen), `AufnahmeController` (Aufnahme neuer Mitglieder per Token, Legacy-Feldmapping `MAP`), `SearchController` (Mitgliedersuche mit `FilterOp`-Filtern; Felder mit `|s` nur bei gesetzter `sichtbarkeit_*`), `AgreementController`/`UserAgreementController` (Datenschutz-/Vereinbarungstexte und Zustimmungen), `StatisticsController`, `NewsletterexportController`, `DevController` (nur Nicht-Produktion).
- **Runtime/Deployment**: Image `trafex/php-nginx` (nginx + php-fpm, Port 8080, Docroot `app/html`, alle Requests ohne Datei → `index.php`; Config in `config/nginx`, `config/php-custom.ini`). Das Image installiert Composer-Deps `--no-dev`. Tracy-Debugger: `Debugger::$productionMode` steuert Dev-only-Routen.
- **Frontend**: kein Build; statische Dateien in `app/html/` (`css/style.css`, `js/MHN.js`, Tabler-Icons).

## Wo ändere ich was?

Kein Dateibaum (per `ls`/Glob schnell zu finden), sondern nur nicht offensichtliche Zuordnungen:

- **Neue Route/Seite**: Methode mit `#[Route]` + Zugriffsattribut im passenden Controller, Template unter `templates/<Controller>/`, Link ggf. in `templates/partials/navigation.latte`. Neuer Service: `getXyz()` in `Bootstrap` (Konstruktor-Injektion in Controller-Konstruktoren/Methodenparametern geschieht per Typ über den Container).
- **Neue Rolle/Berechtigung**: nur LDAP-Gruppe mit diesem Namen + `AllowIf(role: …)`; Sonderregeln in `User::hasRole`.
- **Profilfeld**: siehe „User-Modell" oben (SQL, `User::felder`, Formular `UserController/form.latte`, `bearbeiten.latte`/`profil.latte`, Suche).
- **LDAP-Zugriff** (Personen/Gruppen, DN-Bau, Passwort): ausschließlich `Service/Ldap`; fachliche Logik in `UserRepository`/`GroupRepository`.
- **Mails**: Versand `Service/EmailService`; Texte in `templates/mails/*.latte` (Rahmen: `html-mail-layout.latte`).
- **Wiederverwendbare Template-Bausteine**: `templates/partials/components.latte`; globales Layout `templates/layout.latte`; Fehlerseite `templates/errorpage.latte`.
- **Client-JS/CSS**: `html/js/MHN.js`, `html/css/style.css` (kein Build, `marked.min.js` für Markdown-Vorschau).
- **Umgebungsvariablen**: neue Variable in `env.sample`, in `environment:` von `docker-compose.yml` durchreichen und in `Bootstrap` per `getenv()` lesen.
- **Mailu-/Authelia-Anbindung**: `html/mailu-login.php` (Token-Verifikation) und `MailboxSelectorController` (Token-Erzeugung) müssen zusammenpassen.

## Konventionen

- `declare(strict_types=1)`, Namespace `App\` = `app/Classes/` (PSR-4), Klassen- und Template-Ordner heißen gleich (`FooController` ↔ `templates/FooController/`).
- Dateien tragen CC0-Header mit Autor; Kommentare/UI-Texte deutsch.
