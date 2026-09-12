# Clientherkunft ab 1.1.6

Einordnung: Server 1. WorkTimePunch benötigt die vorhandene WorkTime-App und
Nextcloud. Keine Kommunikation mit Server 2; Request-, Result-, Callback- und
Datenschutzgrenze von HvSystem bleiben unberührt.

Die bestehenden Clients liefern bereits unterscheidbare HTTP-Merkmale:
Nextcloud sendet Requesttoken und XMLHttpRequest-Kennung, die WEB-GUI
`WorkTimePunchWeb/1.0`, Android verwendet HttpURLConnection mit Dalvik-Kennung
und OCS-Header. Die Erkennung ist eine Herkunftsangabe, kein Gerätenachweis.

Der Beginn eines Arbeitsabschnitts erhält `segment_client`. Pause und Gehen
übernehmen Beginn und Abschluss in die WorkTime-Beschreibung. Pausenende
speichert die Herkunft des neuen Abschnitts. Unbekannte und historische
Herkünfte bleiben ausdrücklich nicht ermittelbar.

## Prüfung am 12.09.2026

- `php tests/client-source.php`: erfolgreich. Abgedeckt sind die drei Clients,
  Android-Browser, unbekannte Anfragen, Altstände, Clientwechsel, Kommen,
  Pause, Pausenende, Gehen sowie Erhalt der Sitzung bei Schreibfehlern.
- PHP-Syntax aller App-PHP-Dateien, `node --check js/topbar.js` und
  `git diff --check`: erfolgreich.
- HVML-Nextcloud: Version 1.1.6 installiert, WorkTime und WorkTimePunch aktiv;
  Nextcloud meldet keinen Wartungsmodus und kein ausstehendes DB-Upgrade.
- `occ integrity:check-app worktimepunch`: ohne Beanstandung.
- SHA-256 von PunchService.php stimmt lokal und auf HVML überein:
  `5972d8fd8e53e261be2c850c56708667fe3546b749305550289fd8123ee90bae`.

Die Tests erzeugen keine echten Arbeitszeitbuchungen. Ein manueller Durchlauf
mit den tatsächlichen drei Clients ist damit nicht ersetzt.

Version 1.1.6 ist zur HVML-Erprobung bestimmt. Eine Veröffentlichung im
Nextcloud-App-Store erfordert weiterhin die ausdrückliche manuelle Freigabe.

## Grenzen der Abschlussprüfung

Die zusätzliche eigenständige PHP-Laufzeitprüfung lieferte zunächst keine
Erfolgsausgabe. Ihre überarbeitete Fassung konnte wegen wiederholter
SSH-Verbindungsabbrüche nicht mehr ausgeführt werden. Eine ausdrückliche
SQL-Abfrage der neuen Spalte und die zusätzliche gezielte Logauswertung sind
somit noch nicht als erfolgreich belegt. Die oben genannten occ-Prüfungen
waren zuvor erfolgreich.

Der Entwicklungsbranch `feature/client-source-1.1.6` wurde gepusht. Eine
GitHub-CI-/Verified-Abfrage war wegen fehlender gh-Anmeldung nicht möglich.
Es wurden kein Release-Tag und keine Store-Veröffentlichung erzeugt.
