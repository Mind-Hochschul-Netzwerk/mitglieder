# Mitglieder-Verwaltung

Die MHN-Mitgliederverwaltung (http://mitglieder.mind-hochschul-netzwerk.de)

## Container lokal bauen und starten

Zuerst muss [mhn/docker-php-base](https://gitlab.mind-hochschul-netzwerk.de/mhn/docker-php-base) gebaut werden. Anschließend kann der Mitglieder-Container mit 

    $ docker-compose up
    
gestartet werden. Der Login ist dann im Browser unter http://localhost/login.php erreichbar.

* Benutzername: Webteam
* Passwort: webteam1

 Alternativ kannst du dich mit Keycloak einloggen (s.u.).

## Schnittstellen zu anderen Diensten

### Schnittstelle zum Aufnahmetool

Siehe unter [mhn/aufnahme](https://gitlab.mind-hochschul-netzwerk.de/mhn/aufnahme)

### Schnittstelle zum Mailcontainer

Zuerst muss [mhn/docker-mailserver](https://gitlab.mind-hochschul-netzwerk.de/mhn/docker-mailserver) gebaut werden.

Die Konfiguration für den Mailserver ist in `docker-compose.yml` enthalten, aber auskommentiert.

### Login mit Keycloak

Passe die ID des Webteams in `docker/sq/setWebteamUserId.sql` an, sodass sie deiner echten MHN-ID entspricht. Damit die Änderung wirksam wird, musst du einmal die Datenbank zurücksetzen:

```
$ docker-compose down
$ docker-compose up
```

Anschließend kannst du dich unter http://localhost mit deinen echten MHN-Zugangsdaten anmelden, loggst dich dadurch aber in den lokalen Webteam-Account ein.

## Automatische Updates

Falls Änderungen ein Update an der Datenbank erforderlich machen, kann ein Update-Skript in `lib/autoupdate.d` abgelegt werden, das die nötigen Änderungen vornimmt und dann beim ersten Aufruf geladen wird. Möglich sind PHP-Skripte (Endung .php) und SQL-Dateien (Endung .sql). Schlägt ein SQL-Query fehl, werden die nachfolgenden Queries in der Datei nicht mehr ausgeführt. Nachfolgende Update-Skripte werden aber trotzdem geladen. Auto-Updates sollen nur ein einziges Mal nach dem Deployen ausgeführt werden. Um beim Entwickeln ein Update-Skript zu testen, muss daher vor jedem Test die Datei `/app/autoupdate.lock` gelöscht werden.
