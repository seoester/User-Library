<?php
/**
* In dieser Datei werden alle Einstellungen der User Library gespeichert.
* @filesource
* @package userlib
*/

namespace settings;

/**
* Server, auf dem die Datenbank liegt.
*/
const DB_server = "localhost";
/**
* Die Datenbank, die die User Library zum Speichern von Daten nutzen soll.
*/
const DB_database = "localhost";
/**
* Der Nutzer, mit dem sich die User Library auf der Datenbank einloggt.
*/
const DB_user = "root";
/**
* Das Passwort, das die User Library zum Einloggen auf der Datenbank benutzen soll.
*/
const DB_password = "root";
/**
* Das Präfix, das vor die Tabellen der User Library gestellt wird.
*/
const DB_prefix = "site_";

/**
* Soll ein Login möglich sein?
*/
const login_enabled = true;
/**
* Soll ein Registrieren mit {@link User::register()} möglich sein?
* Ein Registrieren mit {@link User::create()} ist weiterhin möglich.
*/
const register_enabled = true;
/**
* Benötigen neue Benutzer eine Bestätigung durch die Funktion {@link User::approve()}?
*/
const need_approval = false;
/**
* Wie lang soll der Salt sein, der für die Verschlüsselung von Passwörtern verwendet wird?
*/
const length_salt = 20;
/**
* Wie lang soll der Aktivierungscode sein, der in der Email durch {@link User::register()} verschickt wird?
*/
const length_activationcode = 20;
/**
* Von welcher Email Adresse sollen Emails verschickt werden?
*/
const send_mailaddress = "noreply@localhost";

#After how many seconds will a user be kicked?
/**
* Nach wie vielen Sekunden wird ein Benutzer automatisch ausgeloggt?
*/
const autologouttime = 2000;
/**
* Wie viele fehlerhafte Loginversuche darf ein Benutzer machen, ohne das sein Account gesperrt wird?
*/
const maxloginattempts = 5;
/**
* Für wie lange wird ein Account gesperrt, nachdem zu viele fehlerhafte Loginversuche vorgenommen wurden.
*/
const loginblocktime = 60;
/**
* Sollen Securesessions eingeschaltet sein?
* Bei einer Securesession darf die IP-Adresse eines Benutzers während einer Sitzung nicht wechseln.
* Securesessions werden für die meisten Webseiten nicht empfohlen, weil zum Beispiel Handys mit mobilen Internet häufig die IP-Adresse wechseln.
* Bei lange dauernden Sitzungen kann es durch die Knappheit von IP-Adressen auch sein, das über Kabel/WLAN verbundene Computer/Handys ihre öffentliche IP-Adresse wechseln.
*/
const securesessions = false;
?>