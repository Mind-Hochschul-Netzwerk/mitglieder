# Mitglieder-Verwaltung

Die MHN-Mitgliederverwaltung (http://mitglieder.mind-hochschul-netzwerk.de)

## Container lokal bauen und starten

    $ make image
    $ make dev

Der Login ist dann im Browser unter [https://mitglieder.docker.localhost/](https://mitglieder.docker.localhost/) erreichbar. Die Sicherheitswarnung wegen des Zertifikates kann weggeklickt werden.

* Benutzername: Webteam
* Passwort: webteam1

## Schnittstellen zu anderen Diensten

### Schnittstelle zum Aufnahmetool

Siehe unter [mhn/aufnahme](https://gitlab.mind-hochschul-netzwerk.de/mhn/aufnahme)

### Schnittstelle zum Mailcontainer

Zuerst muss [mhn/docker-mailserver](https://gitlab.mind-hochschul-netzwerk.de/mhn/docker-mailserver) gebaut werden.

Die Konfiguration für den Mailserver ist in `docker-compose.yml` enthalten, aber auskommentiert.

## Automatische Updates

Falls Änderungen ein Update an der Datenbank erforderlich machen, kann ein Update-Skript in `update.d` abgelegt werden, das die nötigen Änderungen vornimmt und dann beim Start des Containers geladen wird. Möglich sind PHP-Skripte (Endung .php) und SQL-Dateien (Endung .sql). Schlägt ein SQL-Query fehl, werden die nachfolgenden Queries in der Datei nicht mehr ausgeführt. Nachfolgende Update-Skripte werden aber trotzdem geladen.
