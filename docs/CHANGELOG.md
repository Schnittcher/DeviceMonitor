# Changelog

## 07.10.2026 - Version 2.0

### Neu
- Das Modul nutzt den strengen Modulstandard von IP-Symcon und benötigt IP-Symcon 9.0.
- Status, Zuletzt online, Zuletzt offline und Wake on Lan nutzen die neuen Darstellungen von IP-Symcon 9. Achtung: Bestehende Instanzen werden beim nächsten Speichern der Konfiguration automatisch umgestellt, Variablen, Werte und Archivdaten bleiben erhalten. Die Profile "DM.Status" und "DM.WOL" werden nicht mehr angelegt, vorhandene Profile bleiben bestehen.
- Jedes Gerät in der Geräteliste hat eine feste, unsichtbare ID. Die IP-Adresse und der Name eines Geräts können geändert werden, ohne dass dessen Variablen neu angelegt werden und Archivdaten verloren gehen. Achtung: Die Idents der Variablen heißen jetzt `lst_<ID>` statt `lst_<IP>`. Bestehende Variablen werden beim nächsten Speichern der Konfiguration umbenannt, Objekt-IDs und Archivdaten bleiben erhalten. Skripte, die diese Variablen über den Ident suchen, müssen angepasst werden.
- Das Konfigurationsformular ist übersichtlicher in Gruppen gegliedert (Gerät bzw. Geräte, Prüfung, Wake on Lan, Kachel). Die Anzahl der Versuche und die Felder für Wake on Lan sind nur bedienbar, wenn die jeweilige Funktion aktiviert ist.
- Bei einer Liste von Geräten kann der Gesamtstatus eingestellt werden: online, wenn alle Geräte online sind (wie bisher, Standard), oder wenn mindestens ein Gerät online ist.
- Neue Kachel für die Kachel-Visualisierung im Stil von IP-Symcon: Für ein einzelnes Gerät mit Icon (Pflicht) oder optional einem direkt in der Konfiguration hochgeladenen Bild, Status, Zeitpunkten und Knopf zum Aufwecken. Für die Liste von Geräten mit Gesamtstatus, Anzahl und einer Zeile je Gerät (mit Icon je Gerät). Hostname und IP-Adresse lassen sich in der Kachel ein- und ausblenden. Die Kachel aktualisiert sich bei jeder Prüfung.
- Wake on Lan funktioniert jetzt auch bei einer Liste von Geräten. Jedes Gerät in der Liste hat ein Feld für die MAC-Adresse und bekommt, wenn Wake on Lan aktiv und die MAC-Adresse gültig ist, eine eigene Variable zum Wecken. Die Broadcast-Adresse gilt für alle Geräte der Liste.

### Fixes
- Zuletzt-online- und Zuletzt-offline-Variablen der Geräteliste bleiben bei jeder Änderung der Konfiguration erhalten und werden nicht mehr gelöscht und neu angelegt.
- Zuletzt-online- und Zuletzt-offline-Variablen der Geräteliste werden wieder als Datum und Uhrzeit angezeigt. Achtung: Bei bestehenden Instanzen wird das Profil beim nächsten Speichern der Konfiguration korrigiert.
- Die Instanz meldet bei fehlender IP-Adresse, leerer Geräteliste oder ungültigem Intervall bzw. Timeout den Status "Konfiguration ist ungültig", statt Fehler zu erzeugen.
- Die Beschriftungen "Ping Timeout" und "Update Intervall" werden im Formular wieder angezeigt.
- Wake on Lan prüft die MAC-Adresse und meldet Fehler beim Senden, statt abzubrechen.
- Der Ordner des Moduls heißt jetzt DeviceMonitor statt IPS-DeviceMonitor, die Repository-Adresse wurde angepasst.
