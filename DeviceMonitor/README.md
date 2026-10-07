# DeviceMonitor
Die Instanz prüft per Ping, ob ein Gerät oder eine Liste von Geräten im LAN erreichbar ist, und legt dazu Variablen für Status, zuletzt online und zuletzt offline an. Bei einem einzelnen Gerät kann es zusätzlich per Wake on Lan geweckt werden, bei einer Liste von Geräten ist Wake on Lan nicht möglich.

## Inhaltsverzeichnis
- [DeviceMonitor](#devicemonitor)
  - [Inhaltsverzeichnis](#inhaltsverzeichnis)
  - [1. Konfiguration](#1-konfiguration)
  - [2. Variablen](#2-variablen)
  - [3. Funktionen](#3-funktionen)
  - [4. Spenden](#4-spenden)
  - [5. Lizenz](#5-lizenz)

## 1. Konfiguration

Feld | Beschreibung
------------ | ----------------
Aktiv | Schaltet die Instanz aktiv bzw. inaktiv. Ist sie inaktiv, steht der Status auf offline.
Liste von Geräten | Prüft eine Liste von Geräten statt eines einzelnen Geräts. Die Felder IP-Adresse und Wake on Lan werden dann ausgeblendet.
IP-Adresse | IP-Adresse des Geräts, das überwacht werden soll. Pflichtfeld, wenn die Liste von Geräten nicht aktiv ist.
Geräte | Liste mit Name und IP-Adresse je Gerät. Wird nur angezeigt, wenn die Liste von Geräten aktiv ist. Sie darf nicht leer sein, jedes Gerät braucht eine IP-Adresse.
Ping-Timeout | Wartezeit in Millisekunden, muss größer als 0 sein. Standard: 1000.
Update Intervall | Zeit in Sekunden, wie oft die Geräte geprüft werden, muss größer als 0 sein. Standard: 20.
Fehlversuche aktiv | Ein Gerät gilt erst nach mehreren erfolglosen Pings als offline.
Versuche | Anzahl der Fehlversuche, bis der Status auf offline gesetzt wird. Standard: 20.
Wake on Lan | Aktiviert Wake on Lan für das einzelne Gerät.
Broadcast-Adresse | Broadcast-Adresse des Netzwerks, an die das Magic Packet gesendet wird.
MAC-Adresse | MAC-Adresse des Geräts im Format `AA:BB:CC:DD:EE:FF`.

Sind IP-Adresse bzw. Geräteliste, Intervall oder Ping-Timeout ungültig, meldet die Instanz "Konfiguration ist ungültig" und prüft nicht.

## 2. Variablen
Die Variablen für Zuletzt online und Zuletzt offline zeigen Datum und Uhrzeit. Sie bleiben bei Änderungen der Konfiguration erhalten. Bei der Liste von Geräten entstehen die Variablen je Gerät, der Ident enthält die IP-Adresse mit `_` statt `.`.

Variable | Ident | Typ | Beschreibung
------------ | ------------ | ------------ | ----------------
Status | `DeviceStatus` | Ja/Nein | Online oder offline. Bei der Liste von Geräten ist der Status nur online, wenn alle Geräte online sind.
Zuletzt online | `LastSeen` | Datum/Uhrzeit | Zeitpunkt, zu dem das Gerät zuletzt erreichbar war.
Zuletzt offline | `LastOffline` | Datum/Uhrzeit | Zeitpunkt, zu dem das Gerät zuletzt nicht erreichbar war.
Wake on Lan | `DeviceWOL` | Ganzzahl | Weckt das Gerät per Wake on Lan, steuerbar. Nur mit aktivem Wake on Lan und ohne Liste von Geräten.
Status *Name* | `lst_<IP>` | Ja/Nein | Status des einzelnen Geräts aus der Liste.
Zuletzt online *Name* | `lst_<IP>_LastSeen` | Datum/Uhrzeit | Zeitpunkt, zu dem das Gerät aus der Liste zuletzt erreichbar war.
Zuletzt offline *Name* | `lst_<IP>_LastOffline` | Datum/Uhrzeit | Zeitpunkt, zu dem das Gerät aus der Liste zuletzt nicht erreichbar war.

## 3. Funktionen

`void DM_UpdateStatus(integer $InstanzID);`
Prüft sofort den Status des Geräts bzw. der Geräte. Die Instanz ruft die Funktion im eingestellten Intervall selbst auf.

`void DM_WakeOnLan(integer $InstanzID);`
Sendet ein Magic Packet an die eingestellte Broadcast- und MAC-Adresse. Ungültige oder fehlende Adressen werden im Debug gemeldet.

## 4. Spenden
Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=EK4JRP87XLSHW" target="_blank"><img src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" border="0" /></a> <a href="https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share" target="_blank">Amazon Wunschzettel</a>

## 5. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
