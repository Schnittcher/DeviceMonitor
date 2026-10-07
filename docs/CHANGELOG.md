# 07.10.2026 - Version 2.0
## Neu
- Das Modul nutzt den strengen Modulstandard von IP-Symcon und benötigt IP-Symcon 9.0.

## Fixes
- Der Ordner des Moduls heißt jetzt DeviceMonitor statt IPS-DeviceMonitor, die Repository-Adresse wurde angepasst.

# 07.10.2026 - Version 1.7
## Fixes
- Zuletzt-online- und Zuletzt-offline-Variablen der Geräteliste bleiben bei jeder Änderung der Konfiguration erhalten und werden nicht mehr gelöscht und neu angelegt.
- Zuletzt-online- und Zuletzt-offline-Variablen der Geräteliste werden wieder als Datum und Uhrzeit angezeigt. Achtung: Bei bestehenden Instanzen wird das Profil beim nächsten Speichern der Konfiguration korrigiert.
- Die Instanz meldet bei fehlender IP-Adresse, leerer Geräteliste oder ungültigem Intervall bzw. Timeout den Status "Konfiguration ist ungültig", statt Fehler zu erzeugen.
- Die Beschriftungen "Ping Timeout" und "Update Intervall" werden im Formular wieder angezeigt.
- Wake on Lan prüft die MAC-Adresse und meldet Fehler beim Senden, statt abzubrechen.
