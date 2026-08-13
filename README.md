# LDAP-Integration

Bindet owncloud.online an ein LDAP-Verzeichnis oder ein Active Directory an.
Benutzer und Gruppen kommen aus dem Verzeichnis, die Anmeldung prüft der
Verzeichnisdienst; owncloud.online speichert die Verzeichnis-Passwörter nicht.

## Was die App tut

* Meldet ein Benutzer- und ein Gruppen-Backend an owncloud.online an, sobald
  mindestens eine aktive Konfiguration vorhanden ist.
* Liest Benutzer und Gruppen anhand von Filtern aus dem Verzeichnis.
* Übernimmt Attribute wie Anzeigename, E-Mail-Adresse, Speicherkontingent,
  Home-Verzeichnis und – wenn Avatare systemweit aktiv sind – das Bild aus
  `jpegPhoto`, ersatzweise aus `thumbnailPhoto`.
* Vergibt für jeden gefundenen Eintrag einen internen Benutzer- bzw.
  Gruppennamen und hält die Zuordnung in den Tabellen `oc_ldap_user_mapping`
  und `oc_ldap_group_mapping` fest.
* Erlaubt mehrere Verzeichnisse nebeneinander. Jede Konfiguration hat eine
  eigene ID und wird getrennt gepflegt.

## Voraussetzungen

* owncloud.online 11.x
* PHP 8.4 mit der Erweiterung `ldap` – fehlt sie, zeigt die Einstellungsseite
  eine Warnung und das Backend arbeitet nicht
* erreichbarer LDAP- oder AD-Server; für StartTLS bzw. `ldaps://` muss das
  Zertifikat der Gegenstelle auf dem Server vertrauenswürdig sein
* ein Bind-Konto mit Leserecht auf den benötigten Teilbaum, oder anonymer
  Lesezugriff
* ein laufender Cron, wenn der Abgleich regelmäßig laufen soll
* die App `user_webdavauth` darf nicht gleichzeitig aktiv sein – beide
  zusammen führen zu unerwartetem Verhalten und schreiben eine Warnung ins Log

## Installation

Über den Market ist es der einfachere Weg. Von Hand:

```bash
cd /var/www/owncloud.online/apps
git clone https://github.com/BWTECH-github/user_ldap.git
cd user_ldap
composer install --no-dev
chown -R www-data:www-data .
sudo -u www-data php8.4 ../../occ app:enable user_ldap
```

## Einstellungen

Die Oberfläche liegt unter *Einstellungen → Administration →
Nutzer-Authentifizierung*. Der Assistent führt durch die Reiter *Server*,
*Benutzer*, *Loginattribute* und *Gruppen*; dazu kommen *Fortgeschritten*
und *Experte*. Alle Werte landen in der Tabelle `oc_appconfig` unter der
App-Kennung `user_ldap`, jeweils mit der Konfigurations-ID als Präfix.

### Verbindung

Im Reiter *Server* stehen *Host* und *Port*. Das Protokoll können Sie
weglassen; für LDAP über SSL beginnen Sie mit `ldaps://`. *Benutze StartTLS
support* schaltet StartTLS ein – das ist etwas anderes als
LDAPS, für das dieses Kästchen nicht gesetzt werden muss.

Unter *Fortgeschritten → Verbindungseinstellungen* liegen *Konfiguration
aktiv* (ohne diesen Haken wird die Konfiguration übersprungen), *Backup-Host
(Kopie)* nebst Port, *Hauptserver deaktivieren*, das Abschalten der
SSL-Zertifikatsprüfung, *Speichere Time-To-Live zwischen* (Cache, Vorgabe
600 Sekunden) und *Netzwerk-Zeitüberschreitung* (Vorgabe 15 Sekunden). Die
Zertifikatsprüfung sollten Sie höchstens zum Eingrenzen eines Fehlers
abschalten; besser importieren Sie das Zertifikat des LDAP-Servers.

### Bind-Konto

*Benutzer-DN* und *Passwort* im Reiter *Server* sind die Zugangsdaten des
Kontos, mit dem der Bind durchgeführt wird, zum Beispiel
`uid=agent,dc=example,dc=com`. Für anonymen Zugriff lassen Sie beide Felder
leer. Das Passwort wird base64-kodiert in `oc_appconfig` abgelegt – das ist
keine Verschlüsselung; geben Sie dem Konto nur Leserechte.

### Basis-DN

*Server → Einen Basis-DN pro Zeile* (`ldap_base`) nimmt einen oder mehrere
Bäume auf. Getrennte Bäume für Benutzer und Gruppen stellen Sie unter
*Fortgeschritten → Ordnereinstellungen* als *Basis-Benutzerbaum*
(`ldap_base_users`) und *Basis-Gruppenbaum* (`ldap_base_groups`) ein. Die
Schaltflächen *Base DN ermitteln* und *Base DN testen* prüfen die Eingabe
gegen den Server.

### Benutzer-, Anmelde- und Gruppenfilter

| Reiter | Wofür der Filter gilt | Schlüssel |
| --- | --- | --- |
| *Benutzer* | welche Einträge als Benutzer gelten (Auflistung, Suche) | `ldap_userlist_filter` |
| *Loginattribute* | welcher Eintrag zum eingegebenen Anmeldenamen gehört | `ldap_login_filter` |
| *Gruppen* | welche Einträge als Gruppen gelten | `ldap_group_filter` |

Im geführten Modus wählen Sie *Nur diese Objektklassen:* und *Nur aus diesen
Gruppen:*; über *LDAP-Abfrage bearbeiten* schreiben Sie den Filter selbst.
Im Anmeldefilter steht `%uid` für den eingegebenen Anmeldenamen, Beispiel
`uid=%uid`; ohne diesen Platzhalter meldet die App die Konfiguration als
fehlerhaft. Im Reiter *Loginattribute* legen Sie zusätzlich fest, ob die
Anmeldung über *LDAP-/AD-Benutzername:*, *LDAP-/AD-E-Mail-Adresse:* oder
*Andere Attribute:* erlaubt ist.

Bei großen Verzeichnissen empfiehlt sich *LDAP-Filter manuell eingeben*. Das
verhindert die automatischen Sammelabfragen des Assistenten.

### Attributzuordnung

Benutzer- und Gruppenattribute liegen unter *Fortgeschritten →
Ordnereinstellungen* bzw. *Spezielle Eigenschaften*.

| Feld | Schlüssel | Vorgabe |
| --- | --- | --- |
| Feld für den Anzeigenamen des Benutzers | `ldap_display_name` | `displayName` |
| 2. Benutzeranzeigename Feld | `ldap_user_display_name_2` | leer |
| Feld für den Anzeigenamen der Gruppe | `ldap_group_display_name` | `cn` |
| E-Mail-Feld | `ldap_email_attr` | leer |
| Kontingent Feld | `ldap_quota_attr` | leer |
| Standard Kontingent | `ldap_quota_def` | leer |
| Benennungsregel für das Home-Verzeichnis des Benutzers | `home_folder_naming_rule` | leer |
| Benutzersucheigenschaften | `ldap_attributes_for_user_search` | leer |
| Gruppensucheigenschaften | `ldap_attributes_for_group_search` | leer |

Leere Felder bedeuten das jeweilige Standardverhalten: Anzeigename aus
`displayName`, Kontingent aus den Vorgaben von owncloud.online, Home-
Verzeichnis nach dem internen Benutzernamen. Suchattribute werden zeilenweise
eingetragen; jeder Attributwert wird dabei auf 191 Zeichen gekürzt.

Die Gruppenzugehörigkeit steuern *Assoziation zwischen Gruppe und Benutzer*
(`ldap_group_member_assoc_attribute`, Vorgabe `uniqueMember`, daneben
`memberUid` und `member` – im Auswahlfeld als „member (AD)“ beschriftet) und
*Gruppen-Mitglied Algorithmus* (`ldap_group_member_algo`):

| Wert | Bedingung |
| --- | --- |
| `groupScan` (Vorgabe) | ohne Einschränkungen, unterstützt verschachtelte Gruppen |
| `memberOf` | setzt das Attribut `memberOf` voraus, keine verschachtelten Gruppen |
| `recursiveMemberOf` (im Auswahlfeld „recursiveMemberOf (AD)“) | setzt `memberOf` und den Operator `LDAP_MATCHING_RULE_IN_CHAIN` voraus; berücksichtigt nur explizit eingetragene Mitglieder, die AD-Primärgruppe also nicht |

Im Zweifel bleibt `groupScan` die sichere Wahl. *Eingebundene Gruppen*
(`ldap_nested_groups`) funktioniert nur, wenn das Mitgliedsattribut DNs
enthält. *Dynamische Gruppenmitglied URL*
(`ldap_dynamic_group_member_url`) schaltet dynamische Mitgliedschaften ein;
leer heißt aus.

### Der interne Benutzername

Der interne Benutzername identifiziert das Konto innerhalb von
owncloud.online. Er wird standardmäßig aus dem UUID-Attribut gebildet, darf
nur die Zeichen `a-zA-Z0-9+_.@-` enthalten und bekommt bei Namenskollisionen
eine Zahl angehängt. Er ist zugleich der Vorgabename des Home-Verzeichnisses
und Bestandteil der Remote-URLs, etwa für alle \*DAV-Dienste.

Genau deshalb ändert man ihn nicht nachträglich. Die Zuordnung interner Name
↔ Verzeichniseintrag steht in `oc_ldap_user_mapping`; Dateien, Freigaben,
Home-Verzeichnis und die von den Clients gespeicherten URLs hängen daran. Die
Felder *Attribut für internen Benutzernamen:* (`ldap_expert_username_attr`),
*Attribut für internen Gruppennamen:* (`ldap_expert_groupname_attr`) sowie
*UUID-Attribute für Benutzer:* / *UUID-Attribute für Gruppen:*
(`ldap_expert_uuid_user_attr`, `ldap_expert_uuid_group_attr`) im Reiter
*Experte* wirken sich ausschließlich auf neu zugeordnete Einträge aus –
bestehende Konten behalten ihren Namen.

Die Schaltflächen *LDAP-Benutzernamenzuordnung löschen* und
*LDAP-Gruppennamenzuordnung löschen* leeren die Zuordnungstabellen für **alle**
LDAP-Konfigurationen und hinterlassen überall Restdaten. Benutzen Sie sie nur
in Test- oder Experimentierumgebungen.

## Abgleich per Cron

Die App bringt selbst keinen Hintergrundauftrag mit. Den Abgleich stößt der
Kernbefehl `occ user:sync` an; er trägt die Konten des Backends in die
Kontentabelle ein. Die Backend-Klasse für LDAP ist
`OCA\User_LDAP\User_Proxy`, die Kurzform `ldap` tut es ebenso.

```bash
# alle Konten abgleichen
sudo -u www-data php8.4 occ user:sync 'OCA\User_LDAP\User_Proxy'

# Konten, die im Verzeichnis nicht mehr vorkommen, deaktivieren
sudo -u www-data php8.4 occ user:sync -m disable 'OCA\User_LDAP\User_Proxy'

# ein einzelnes Konto abgleichen
sudo -u www-data php8.4 occ user:sync -u jmeier 'OCA\User_LDAP\User_Proxy'
```

Für `--missing-account-action` (kurz `-m`) sind `disable` und `remove`
zulässig; `remove` löscht das Konto samt Daten und Dateien. Ohne diese Option
fragt der Befehl interaktiv nach – im Cron geben Sie sie deshalb immer mit an,
damit gesperrte oder entfernte Verzeichniseinträge auch in owncloud.online
nachgezogen werden.

## Kommandozeile

Die App bringt acht Befehle mit. Die Konfigurations-ID vergibt
`ldap:create-empty-config` selbst (`s01`, `s02`, …); bei sehr alten
Installationen kann die allererste Konfiguration eine leere ID haben.

```bash
# neue, leere Konfiguration anlegen
sudo -u www-data php8.4 occ ldap:create-empty-config

# Konfigurationen anzeigen – ohne ID alle
sudo -u www-data php8.4 occ ldap:show-config
sudo -u www-data php8.4 occ ldap:show-config s01 --show-password

# einzelnen Wert setzen
sudo -u www-data php8.4 occ ldap:set-config s01 ldap_host ldap.example.com

# Konfiguration prüfen (Verbindung und Bind)
sudo -u www-data php8.4 occ ldap:test-config s01

# Konfiguration löschen
sudo -u www-data php8.4 occ ldap:delete-config s01

# Benutzer oder Gruppen suchen
sudo -u www-data php8.4 occ ldap:search "meier"
sudo -u www-data php8.4 occ ldap:search "" --group --limit 50

# prüfen, ob ein Konto im Verzeichnis noch vorhanden ist
sudo -u www-data php8.4 occ ldap:check-user jmeier

# Cache der LDAP-Backends leeren
sudo -u www-data php8.4 occ ldap:invalidate-cache
```

`ldap:search` kennt `--group`, `--offset` und `--limit` (Vorgabe 15, `0` hebt
die Begrenzung auf; der Offset muss ein Vielfaches des Limits sein).
`ldap:check-user` kennt `--force`, um auch bei abgeschalteter Konfiguration zu
prüfen.

## Weitere Schalter

Diese Werte haben keine Oberfläche. Sie gelten für alle LDAP-Konfigurationen
gemeinsam.

Über `occ config:app:set user_ldap …`:

| Schlüssel | Vorgabe | Wirkung |
| --- | --- | --- |
| `reuse_accounts` | `no` | Bei `yes` darf eine Zuordnung auf ein bereits vorhandenes Konto gleichen Namens zeigen, sofern dieses aus demselben LDAP-Backend stammt. Konten aus anderen Backends werden immer abgelehnt und protokolliert. |
| `enforce_home_folder_naming_rule` | `true` | Fehlt einem Benutzer das Attribut aus der Benennungsregel für das Home-Verzeichnis, bricht die App mit einem Fehler ab. Auf `false` gesetzt, gilt stattdessen die Vorgabe. |
| `resolve_uid_by_legacy_dn` | `true` | Als letzter Versuch wird ein Benutzer auch über die alte DN-Schreibweise in der Zuordnungstabelle gesucht und der Eintrag danach auf den aktuellen DN gehoben. |

In `config.php`:

* `'user_ldap.enable_medial_search' => true`

  Standardmäßig trifft eine Suche nur den Anfang des Namens: Bei den
  Benutzern „erl“ und „peter“ findet die Eingabe „er“ nur „erl“. Mit dieser
  Option wird zusätzlich in der Mitte gesucht, „er“ findet dann beide.
  Beachten Sie dabei:

  * Die Option gilt für alle LDAP-Verbindungen, nicht je Konfiguration.
  * Sie kann große Verzeichnisse belasten, weil für solche Suchen meist kein
    Index greift. Ob Ihr Verzeichnis das unterstützt, klären Sie mit dem
    Betreiber; die Option arbeitet auch ohne passenden Index, und kleine
    Installationen kommen damit oft gut zurecht.
  * Sie wirkt nur, wenn die Benutzerauflistung im Freigabedialog erlaubt ist
    (`core`/`shareapi_allow_share_dialog_user_enumeration`).

* `'ldapIgnoreNamingRules' => true`

  Schaltet die Bereinigung des internen Benutzernamens ab – keine
  Transliteration, keine Einschränkung auf `a-zA-Z0-9+_.@-`. Wirkt nur auf
  neu zugeordnete Einträge und kann Namen erzeugen, mit denen andere Teile
  des Systems nicht rechnen.

## Fehlersuche

| Symptom | Ursache | Abhilfe |
| --- | --- | --- |
| Warnung auf der Einstellungsseite, Anmeldung schlägt fehl | PHP-Modul `ldap` fehlt | Erweiterung installieren, PHP-FPM neu starten |
| Bind schlägt fehl, Konfiguration wird als ungültig gemeldet | Host, Port, Bind-Konto oder Zertifikat stimmen nicht | `occ ldap:test-config <ID>`; Zertifikat importieren; zum Eingrenzen kurzzeitig die Zertifikatsprüfung abschalten |
| Konfiguration bleibt wirkungslos | *Konfiguration aktiv* ist nicht gesetzt | Haken unter *Fortgeschritten → Verbindungseinstellungen* setzen |
| Keine Benutzer sichtbar | Basis-DN oder Benutzerfilter passen nicht | `occ ldap:search ""` und die Filter im Assistenten prüfen |
| Anmeldung schlägt trotz korrektem Passwort fehl | Anmeldefilter trifft den Eintrag nicht oder enthält kein `%uid` | Filter im Reiter *Loginattribute* prüfen, *Loginnamen testen* benutzen |
| Gruppen bleiben leer | falsches Mitgliedsattribut oder ungeeigneter Algorithmus | *Assoziation zwischen Gruppe und Benutzer* prüfen, Algorithmus auf `groupScan` stellen |
| Geändertes Kontingent oder geänderte E-Mail kommen nicht an | Cache | `occ ldap:invalidate-cache`, notfalls *Speichere Time-To-Live zwischen* senken |
| Verzeichniseintrag gelöscht, Konto bleibt bestehen | kein Abgleich eingerichtet | `occ ldap:check-user <name>`, danach `occ user:sync -m disable …` |
| Suche findet nur Treffer am Wortanfang | Standardverhalten | `user_ldap.enable_medial_search` setzen |
| Timeouts oder abgeschnittene Ergebnislisten | Paging oder Zeitgrenze zu knapp | *Seitenstücke (Paging chunksize)* und *Netzwerk-Zeitüberschreitung* anpassen |
| Unerwartetes Verhalten, Warnung im Log | `user_webdavauth` ist ebenfalls aktiv | eine der beiden Apps abschalten |

## Tests

```bash
make test-php-unit      # PHPUnit
make test-php-style     # Code-Stil
make test-php-phan      # Phan
make test-php-phpstan   # PHPStan
```

## Herkunft

Fork der App `user_ldap` der ownCloud GmbH, gepflegt von der BW-Tech GmbH für
owncloud.online und PHP 8.4. Lizenz: AGPLv3.
Quelltext: <https://github.com/BWTECH-github/user_ldap>
