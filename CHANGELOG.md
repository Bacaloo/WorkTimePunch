# Changelog

## 1.1.5

* Gleicht offene WorkTimePunch-Sitzungen regelmäßig mit späteren Änderungen
  an den zugehörigen WorkTime-Zeiteinträgen ab.
* Berücksichtigt dabei auch neu angelegte und gelöschte WorkTime-Einträge.
* Verhindert einen 409-Konflikt, wenn eine gerade ausgelöste Punch-Aktion mit
  der automatischen Zustandskorrektur zusammenfällt.
* Aktualisiert den Status in der Nextcloud-Kopfleiste alle 30 Sekunden.

## 1.1.4

* Übernimmt das Logo aus WorkTimePunchAndroid als Store- und App-Symbol.

## 1.1.3

* Verwendet den vom Nextcloud App Store erwarteten SPDX-Lizenzbezeichner.
* Ordnet die Abhängigkeiten in `info.xml` entsprechend dem aktuellen Store-Schema.
* Bereitet das erste signierte App-Store-Paket vor.

## 1.1.2

* Zeigt die Uhrzeit der letzten passenden Buchung direkt in der Nextcloud
  Kopfleiste an.
* Verwendet klarere Beschriftungen fuer Pausenaktionen.
* Ergaenzt die Release-Dokumentation um die App-Icons im Nutzungsabschnitt.
* Fuegt CI-Pruefungen fuer PHP-Syntax und JavaScript-Syntax hinzu.

## 1.0.0

* Erste stabile Veroeffentlichung von WorkTimePunch.
* Stellt Top-Bar-Schaltflaechen fuer Kommen, Pause starten, Pause beenden und
  Gehen bereit.
* Erfasst tagesbezogenen Anwesenheits- und Pausenstatus je WorkTime
  Mitarbeiter.
* Erstellt WorkTime-Zeiteintraege fuer abgeschlossene Arbeitsabschnitte.
* Deaktiviert sich automatisch, wenn die WorkTime App nicht aktiv ist.
