# Telefonkette
Das Telefonkette-Modul ermöglicht es eine Liste von Telefonnummern nacheinander anzurufen.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

* Eine Liste von Telefonnummern kann absteigend angerufen werden
* Weitere Anrufe werden beendet, wenn ein Angerufener durch ein DTMF-Zeichen bestätigt
* DTMF-Zeichen frei wählbar
* Dauer bis ein Anruf beendet wird frei wählbar
* Bool Variable als Auslöser

### 2. Vorraussetzungen

- IP-Symcon ab Version 5.5

### 3. Software-Installation

* Über den Module Store das 'Telefonkette'-Modul installieren.
* Alternativ über das Module Control folgende URL hinzufügen

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'Telefonkette'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name                         | Beschreibung
---------------------------- | ------------------
Auslöser                     | Bool Variable die als Auslöser dient
VoIP Instanz                 | VoIP Instanz, welche für die Anrufe genutzt werden soll
Telefonnummern               | Liste und Reihenfolge der Nummern, elche angerufen werden sollen
Anzahl gleichzeitiger Anrufe | Anzahl der Anrufe, welche parallel gemacht werden
Anrufdauer                   | Zeit in Sekunden, welche auf ein Annehmen des Anrufes gewartet wird
DTMF Bestätigungstaste       | Taste, mit der ein Anruf bestätigt wird
       

### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

Es werden keine neuen Statusvariablen oder Profile angelegt.

### 6. WebFront

Die Funktionalität, die das Modul im WebFront bietet.

### 7. PHP-Befehlsreferenz

Es werden keine zusätzlichen Funktionen zur Verfügung gestellt.