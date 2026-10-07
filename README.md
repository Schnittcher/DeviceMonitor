[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Version](https://img.shields.io/badge/Symcon%20Version-9.0%20%3E-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Schnittcher/DeviceMonitor/workflows/Check%20Style/badge.svg)](https://github.com/Schnittcher/DeviceMonitor/actions)

# DeviceMonitor
Mit dieser Bibliothek wird der Online- / Offline-Status von Geräten im LAN überwacht.

## Inhaltsverzeichnis
- [DeviceMonitor](#devicemonitor)
  - [Inhaltsverzeichnis](#inhaltsverzeichnis)
  - [1. Voraussetzungen](#1-voraussetzungen)
  - [2. Funktionsumfang](#2-funktionsumfang)
  - [3. Enthaltene Module](#3-enthaltene-module)
  - [4. Installation](#4-installation)
  - [5. Konfiguration in IP-Symcon](#5-konfiguration-in-ip-symcon)
  - [6. Spenden](#6-spenden)
  - [7. Lizenz](#7-lizenz)

## 1. Voraussetzungen

* mindestens IP-Symcon Version 9.0
* Die zu überwachenden Geräte müssen per Ping erreichbar sein.
* Für Wake on Lan muss das Gerät Magic Packets unterstützen und im selben Netzwerk erreichbar sein.

## 2. Funktionsumfang
* Überwachung eines einzelnen Geräts oder einer Liste von Geräten per Ping
* Variablen für Status, zuletzt online und zuletzt offline
* Fehlversuche, bis ein Gerät als offline gilt
* Geräte per Wake on Lan wecken

Die einzelnen Funktionen stehen bei den Instanzen unter "Enthaltene Module" und in der README des jeweiligen Moduls.

## 3. Enthaltene Module

* [DeviceMonitor](DeviceMonitor/README.md)
  * Prüft per Ping, ob ein Gerät oder eine Liste von Geräten online ist.
  * Legt Variablen für Status, zuletzt online und zuletzt offline an und kann Geräte per Wake on Lan wecken.

## 4. Installation
Installation über die Modulverwaltung von IP-Symcon mit der Adresse:
```
https://github.com/Schnittcher/DeviceMonitor.git
```

## 5. Konfiguration in IP-Symcon
Eine Instanz "DeviceMonitor" anlegen und die Konfiguration nach der [Modul-README](DeviceMonitor/README.md) ausfüllen. Die Instanz prüft danach im eingestellten Intervall, ob die Geräte erreichbar sind.

## 6. Spenden

Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=EK4JRP87XLSHW" target="_blank"><img src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" border="0" /></a> <a href="https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share" target="_blank">Amazon Wunschzettel</a>

## 7. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
