# DeviceMonitor
Die Instanz prüft per Ping, ob ein Gerät oder eine Liste von Geräten im LAN erreichbar ist, und legt dazu Variablen für Status, zuletzt online und zuletzt offline an. Bei einem einzelnen Gerät kann es zusätzlich per Wake on Lan geweckt werden, bei einer Liste von Geräten ist Wake on Lan nicht möglich.

## Inhaltsverzeichnis
- [DeviceMonitor](#devicemonitor)
  - [Inhaltsverzeichnis](#inhaltsverzeichnis)
  - [1. Konfiguration](#1-konfiguration)
  - [2. Variablen](#2-variablen)
  - [3. Kachel](#3-kachel)
  - [4. Funktionen](#4-funktionen)
  - [5. Spenden](#5-spenden)
  - [6. Lizenz](#6-lizenz)

## 1. Konfiguration
Das Formular ist in Gruppen gegliedert: Gerät bzw. Geräte, Prüfung, Wake on Lan und Kachel. Die Anzahl der Versuche ist nur bedienbar, wenn Fehlversuche aktiv sind, und die Felder von Wake on Lan nur bei aktivem Wake on Lan.

Feld | Beschreibung
------------ | ----------------
Aktiv | Schaltet die Instanz aktiv bzw. inaktiv. Ist sie inaktiv, steht der Status auf offline.
Liste von Geräten | Prüft eine Liste von Geräten statt eines einzelnen Geräts. Das Feld IP-Adresse und das Einzelfeld MAC-Adresse werden dann ausgeblendet.
Hostname | Optionaler Name des einzelnen Geräts. Er erscheint in der Kachel. Bei der Liste von Geräten steht der Name in jeder Zeile der Liste.
IP-Adresse | IP-Adresse des Geräts, das überwacht werden soll. Pflichtfeld, wenn die Liste von Geräten nicht aktiv ist.
Geräte | Liste mit Name, IP-Adresse und optionaler MAC-Adresse je Gerät. Wird nur angezeigt, wenn die Liste von Geräten aktiv ist. Sie darf nicht leer sein, jedes Gerät braucht eine IP-Adresse. Die MAC-Adresse wird nur für Wake on Lan gebraucht.
Gesamtstatus der Liste | Nur bei der Liste von Geräten. "Alle Geräte müssen online sein" (Standard): Der Status ist nur online, wenn jedes Gerät online ist. "Mindestens ein Gerät online": Der Status ist online, sobald ein Gerät online ist.
Ping-Timeout | Wartezeit in Millisekunden, muss größer als 0 sein. Standard: 1000.
Update Intervall | Zeit in Sekunden, wie oft die Geräte geprüft werden, muss größer als 0 sein. Standard: 20.
Fehlversuche aktiv | Ein Gerät gilt erst nach mehreren erfolglosen Pings als offline.
Versuche | Anzahl der Fehlversuche, bis der Status auf offline gesetzt wird. Standard: 20.
Wake on Lan | Aktiviert Wake on Lan für das einzelne Gerät bzw. für die Geräte der Liste.
Broadcast-Adresse | Broadcast-Adresse des Netzwerks, an die das Magic Packet gesendet wird. Gilt auch für alle Geräte der Liste.
MAC-Adresse | MAC-Adresse des einzelnen Geräts im Format `AA:BB:CC:DD:EE:FF`. Bei der Liste von Geräten steht die MAC-Adresse in jeder Zeile der Liste.
Hostnamen anzeigen (Kachel) | Zeigt den Hostnamen in der Kachel für ein einzelnes Gerät an. Standard: an. Ohne Hostnamen oder ausgeschaltet entfällt die Zeile.
IP-Adresse anzeigen (Kachel) | Zeigt die IP-Adresse in der Kachel an, beim einzelnen Gerät unter dem Hostnamen, bei der Liste unter dem Namen jedes Geräts. Standard: aus.
Icon (Kachel) | Icon der Kachel für ein einzelnes Gerät (Font-Awesome-Name). Pflicht, Standard: `network-wired`.
Bild (Kachel) | Optional. Ein hochgeladenes Bild ersetzt bei einem einzelnen Gerät das Icon in der Kachel. Unterstützt werden PNG, JPG, GIF, BMP und SVG bis etwa 1,5 MB. Das Bild wird in der Konfiguration der Instanz gespeichert. Ist es zu groß, zeigt die Kachel das Icon und das Meldungsfenster nennt den Grund.
Icon (in der Liste) | Icon je Gerät in der Kachel der Liste. Pflicht, Standard: `network-wired`.

Sind IP-Adresse bzw. Geräteliste, Intervall oder Ping-Timeout ungültig, meldet die Instanz "Konfiguration ist ungültig" und prüft nicht.

## 2. Variablen
Die Variablen nutzen die nativen Darstellungen von IP-Symcon 9: Status als Online/Offline mit Farbe, Wake on Lan als Schaltfläche "Start". Bestehende Instanzen werden beim Speichern der Konfiguration automatisch umgestellt. Die Variablen für Zuletzt online und Zuletzt offline zeigen Datum und Uhrzeit. Sie bleiben bei Änderungen der Konfiguration erhalten. Bei der Liste von Geräten entstehen die Variablen je Gerät. Der Ident enthält eine feste ID des Eintrags (`<ID>`, eine fortlaufende Zahl ab 1), die beim ersten Speichern vergeben wird und auch nach dem Löschen eines Eintrags nicht erneut vergeben wird. Die IP-Adresse und der Name eines Eintrags können deshalb geändert werden, ohne dass die Variablen neu angelegt werden.

Variable | Ident | Typ | Beschreibung
------------ | ------------ | ------------ | ----------------
Status | `DeviceStatus` | Ja/Nein | Online oder offline. Bei der Liste von Geräten richtet er sich nach dem eingestellten Gesamtstatus der Liste: online, wenn alle Geräte online sind, oder wenn mindestens ein Gerät online ist.
Zuletzt online | `LastSeen` | Datum/Uhrzeit | Zeitpunkt, zu dem das Gerät zuletzt erreichbar war.
Zuletzt offline | `LastOffline` | Datum/Uhrzeit | Zeitpunkt, zu dem das Gerät zuletzt nicht erreichbar war.
Wake on Lan | `DeviceWOL` | Ganzzahl | Weckt das einzelne Gerät per Wake on Lan, steuerbar. Nur mit aktivem Wake on Lan und ohne Liste von Geräten.
Status *Name* | `lst_<ID>` | Ja/Nein | Status des einzelnen Geräts aus der Liste.
Zuletzt online *Name* | `lst_<ID>_LastSeen` | Datum/Uhrzeit | Zeitpunkt, zu dem das Gerät aus der Liste zuletzt erreichbar war.
Zuletzt offline *Name* | `lst_<ID>_LastOffline` | Datum/Uhrzeit | Zeitpunkt, zu dem das Gerät aus der Liste zuletzt nicht erreichbar war.
Wake on Lan *Name* | `lst_<ID>_WOL` | Ganzzahl | Weckt das Gerät aus der Liste per Wake on Lan, steuerbar. Nur mit aktivem Wake on Lan und gültiger MAC-Adresse in der Zeile.

## 3. Kachel
Die Instanz hat eine eigene Kachel für die Kachel-Visualisierung von IP-Symcon. Die Farben und Icons folgen dem Stil von IP-Symcon (Akzentfarbe, Font-Awesome-Icons).

* **Ein Gerät:** Icon oder Bild, optional der Hostname (abschaltbar) und die IP-Adresse (abschaltbar), Status (Online / Offline), Zeitpunkt zuletzt online und zuletzt offline. Der Name der Instanz wird nicht angezeigt. Mit aktivem Wake on Lan gibt es einen Knopf "Aufwecken".
* **Liste von Geräten:** Kopfzeile mit dem Gesamtstatus und der Anzahl ("1 von 3 online"), rechts ausgerichtet und ohne den Namen der Instanz. Darunter eine Zeile je Gerät mit Icon, Name, Zeitpunkt zuletzt online, bei gültiger MAC-Adresse einem Knopf zum Aufwecken und ganz rechts dem Statuspunkt.

Die Kachel aktualisiert sich bei jeder Prüfung ohne Neuladen.

## 4. Funktionen

`void DM_UpdateStatus(integer $InstanzID);`
Prüft sofort den Status des Geräts bzw. der Geräte. Die Instanz ruft die Funktion im eingestellten Intervall selbst auf.

`void DM_WakeOnLan(integer $InstanzID);`
Sendet ein Magic Packet an die eingestellte Broadcast- und MAC-Adresse des einzelnen Geräts. Die Geräte einer Liste werden über ihre Variable `lst_<ID>_WOL` geweckt. Ungültige oder fehlende Adressen werden im Debug gemeldet.

## 5. Spenden
Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

[![PayPal](https://img.shields.io/badge/PayPal-Spenden-00457C?logo=paypal&logoColor=white&style=for-the-badge)](https://www.paypal.com/donate?hosted_button_id=EK4JRP87XLSHW) [![Amazon Wunschzettel](https://img.shields.io/badge/Amazon-Wunschzettel-FF9900?logo=amazon&logoColor=white&style=for-the-badge)](https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share)

## 6. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
