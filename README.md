# Mitglieder-Verwaltung

Die MHN-Mitgliederverwaltung (http://mitglieder.mind-hochschul-netzwerk.de)

## Container lokal bauen und starten

### Target "dev" (Entwicklung)

    $ composer install -d app
    $ make rebuild
    $ make dev

Der Login ist dann im Browser unter [https://mitglieder.docker.localhost/](https://mitglieder.docker.localhost/) erreichbar. Die Sicherheitswarnung wegen des Zertifikates kann weggeklickt werden.

* Benutzername: Webteam
* Passwort: webteam1

### Target "prod" (Production)

    $ make prod
