# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)

## [1.1.0] - 2026-10-08

Stand der main-Linie bis 0.21.0 (29.09.2026) übernommen.

Die zweite Stelle der Versionsnummer steigt, damit die neue Migration
`Version20260926130000` beim Update läuft. Der Kern führt Migrationen und
Repair-Schritte einer App nur aus, wenn sich mindestens die zweite Stelle
ihrer Versionsnummer ändert (`OC_App::shouldUpgrade`); mit 1.0.3 wären die
verwaisten Hintergrundjobs stehen geblieben. Zwischen Ordnertausch und
`occ upgrade` zeigt die Instanz deshalb die Update-Seite.

### Fixed

- **Die erste LDAP-Konfiguration schrieb eine Deprecation ins Protokoll.**
  `occ ldap:create-empty-config` ohne bestehende Konfiguration führte zu
  `ltrim(): Passing null to parameter #1` (Stufe 3, `lib/Helper.php#219`):
  `nextPossibleConfigurationPrefix()` nahm das alphabetisch größte Präfix, und
  ohne Konfiguration war das null.
- **„Neue Konfiguration" im Admin-Panel konnte eine bestehende Konfiguration
  überschreiben.** Aus demselben Grund führte eine benannte Konfiguration, die
  im Alphabet hinter `s` liegt (`occ ldap:create-empty-config test`), zurück
  auf `s01`. Das Admin-Panel legte die „neue" Konfiguration dann unter `s01`
  an und setzte dabei alle Werte der bestehenden `s01` auf die Vorgaben
  zurück (Host, Bind-DN, Kennwort, Filter). `occ ldap:create-empty-config`
  meldete eine neue Konfiguration `s01`, legte aber keine an. Ab der
  hundertsten Konfiguration sortierte zudem `s100` vor `s99`. Das nächste
  Präfix ergibt sich jetzt aus der höchsten Zahl aller Präfixe der Form
  `s<Zahl>` (Groß-/Kleinschreibung egal) und ist nie ein bestehendes.
- **`occ group:list` ohne Suchmuster schrieb zwei Deprecations ins
  Protokoll.** Der Kern reicht den fehlenden Suchbegriff als null an
  `getGroups()` durch; `escapeFilterPart()` gab ihn an `strlen()` und
  `preg_replace_callback()` weiter (`lib/Access.php#1469` und `#1480`). null
  gilt jetzt als leere Suche.
- **Nach einem Umzug konnten sich LDAP-Konten mit einem Heimatverzeichnis
  außerhalb des Datenverzeichnisses nicht mehr anmelden.** Seit 0.20.4 weist
  `getHome()` jeden Pfad aus der `homeFolderNamingRule` ab, der weder im
  Datenverzeichnis noch unter `user_ldap.home_base_dirs` liegt. Der Kern ruft
  `getHome()` aber bei jeder Anmeldung und jedem `user:sync` auf, auch für
  Konten, deren Heimatverzeichnis längst in `oc_accounts` steht – übernehmen
  tut er den Wert nur, solange dort nichts steht, eine Abweichung
  protokolliert er bloß. Betroffen waren Konten aus Fassungen ohne
  Eingrenzung (ownCloud 10 mit user_ldap bis 0.19.x, upstream 0.20.0 bis
  0.20.2, owncloud.online bis 0.20.3), etwa `attr:homeDirectory` mit
  `/home/<uid>` oder einem NFS-Pfad, und Konten, bei denen gespeichertes
  Heimatverzeichnis und Attribut auseinanderlaufen (Namensregel nachträglich
  gesetzt, `occ user:move-home`, geändertes Attribut).

  Für ein Konto des LDAP-Backends (`backend = OCA\User_LDAP\User_Proxy`) mit
  absolutem Heimatverzeichnis in `oc_accounts` entscheidet jetzt dieses
  gespeicherte Heimatverzeichnis, denn nur damit arbeitet der Kern:
  - Ist es zulässig, gilt wie unter ownCloud 10 der Wert aus dem Verzeichnis.
    Liegt dieser außerhalb der erlaubten Verzeichnisse, steht einmal je
    Anfrage eine Zeile auf Stufe info im Protokoll.
  - Ist es unzulässig, bleibt das Konto gesperrt – auch wenn das Verzeichnis
    inzwischen einen harmlosen Wert liefert. Unzulässig ist ein
    Heimatverzeichnis, das die Wurzel ist oder das Datenverzeichnis (die
    Dateien aller Konten), den Code-, Konfigurations- oder App-Baum oder ein
    Systemverzeichnis (`/etc`, `/root`, `/usr`, `/proc`, `/var/log` …) enthält
    oder gleich einem davon ist, und eines, das außerhalb der erlaubten
    Verzeichnisse im Code-Baum oder in einem Systemverzeichnis liegt. Lag das
    Datenverzeichnis der Altinstanz im Code-Baum (`/var/www/owncloud/data`)
    und liegt es jetzt woanders, gibt `user_ldap.home_base_dirs` das alte
    Verzeichnis frei.

  Neue Konten und Konten mit leerem Heimatverzeichnis unterliegen weiter der
  Eingrenzung. Geschrieben wird nichts; für Konfigurationen mit Namensregel
  kommt je Konto und Anfrage eine Abfrage auf `oc_accounts` hinzu.
- **Hintergrundjobs früherer Fassungen blieben für immer liegen.** 0.9.0 bis
  0.13.x trugen `OCA\User_LDAP\Jobs\UpdateGroups` ein, 0.9.0 (ownCloud
  10.0.0) zusätzlich `OCA\User_LDAP\Jobs\CleanUp`. Beide Klassen gibt es nicht
  mehr, der Kern entfernt Jobs beim App-Update nicht, und seine Liste alter
  Jobs führt `CleanUp` nur mit führendem Backslash. Die Einträge ließen sich
  nicht bauen und wurden bei jedem Versuch protokolliert. Die neue Migration
  `Version20260926130000` entfernt genau diese Einträge, solange es die Klasse
  nicht gibt.

## [1.0.2] - 2026-10-08

### Fixed

- Schmale Fenster (320–390 px): der Assistent ragte 36 px über seine Karte und
  die Seite rollte waagerecht (fieldset mit Mindestbreite seines Inhalts).
- „Fortsetzen“ endete in „Fortse…“: die Knöpfe des Assistenten rutschen jetzt
  in die nächste Zeile statt zu schrumpfen; ein zu langer Knopftext bricht um,
  statt mit Auslassungspunkten zu enden (auch die Mehrfachauswahl für
  Objektklassen und Gruppen).
- Der Hinweiskasten des Assistenten war 16 px breiter als die Karte und hatte
  eine feste Höhe, aus der eine umbrochene Meldung herauslief.

## [1.0.1] - 2026-09-23

### Fixed

- Aus main übernommen (e4794ae9): fix(ldap): Cache-Fehlschlag schreibt keine Protokollzeile mehr

## [1.0.0] - 2026-09-22

Redesign-Linie (owncloud.online 11.1). Für 11.0 gilt weiter der Zweig `main`.
Enthält 0.20.7.

### Fixed

- Die LDAP-Karte endete 40 px vor den übrigen Karten der Seite
  (`calc(100% - 40px)` aus dem alten Layout, mit border-box doppelt abgezogen).
- Kästchen des Assistenten waren verzerrte Kapseln (15 × 24 px, Haken
  abgeschnitten); sie haben wieder 24 × 24 px und stehen links neben ihrer
  Beschriftung.
- Reiter wurden wie Fließtextverweise unterstrichen und grau hinterlegt; jetzt
  Reiterleiste im Stil des Redesigns mit Akzentlinie am aktiven Reiter, das
  Warnsymbol überdeckt den Text nicht mehr.
- Im Reiter „Gruppen“ (und bei „Nur diese Objektklassen“) standen die
  Beschriftungen neben statt über den Auswahlknöpfen; die Auswahlknöpfe brachen
  zweizeilig um und schnitten ihren Text ab.
- Der Umschalter „LDAP-Abfrage bearbeiten“ wurde zur umrandeten Pille; er ist
  wieder ein Verweis.
- Trennlinien der Abschnittsköpfe liefen bis an den Kartenrand; das Symbol
  „Konfiguration kopieren“ stand in voller Größe über dem Rand seines Knopfes.

### Changed

- Voraussetzung owncloud.online 11.1. Echte Umlaute und Fremdverweise als Text
  im Changelog.

### Added

- `tests/visual/pruefe-user-ldap.js` (22 Prüfungen: Gestalt in 1440/400 px,
  Assistent Ende zu Ende gegen einen echten slapd, Anmeldung als LDAP-Konto
  samt Anzeigename und Gruppe) mit `tests/visual/ldap-testdaten.sh`.

## [0.20.7] - 2026-09-22

### Fixed

- **Jeder Cache-Fehlschlag schrieb eine Protokollzeile.** `getFromCache()` gab
  das Ergebnis von `$this->cache->get()` direkt an `base64_decode()` weiter. Bei
  einem Fehlschlag ist das null, und die implizite Umwandlung nach string ist
  unter PHP 8 eine Deprecation — auf einem belebten Verzeichnisdienst sind das
  Tausende Zeilen am Tag, für einen völlig normalen Vorgang. Der Fehlschlag
  kehrt jetzt früh zurück. Gemeldet von Scott Barbour (EFAdrive); der Befund
  gilt unverändert auch für upstream.

## [0.20.6] - 2026-09-21

Entspricht 0.20.4 auf `main`. Die Nummerierung dieses Zweiges liegt seit dem
Zusammenführen in 0.20.5 höher, deshalb hier 0.20.6.

### Security

- **Das Heimatverzeichnis aus dem Verzeichnisdienst wurde ungeprüft
  übernommen.** `getHome()` hat den Wert der `homeFolderNamingRule` ohne
  Normalisierung zurückgegeben. Der Wert kommt aus dem LDAP-Verzeichnis, das
  nicht zwangsläufig unter der Kontrolle der Serververwaltung steht: Zeigte
  er auf das Code-Verzeichnis, wurde die Dateiansicht des Nutzers zu Lese- und
  Schreibzugriff auf die PHP-Dateien der Anwendung. Der Pfad wird jetzt
  normalisiert (Symlinks auf dem existierenden Anfangsstück aufgelöst) und
  muss im Datenverzeichnis liegen oder in einem Verzeichnis, das die Verwaltung
  über die neue Einstellung `user_ldap.home_base_dirs` ausdrücklich erlaubt
  hat. Andernfalls wird er abgewiesen und protokolliert. Übernommen von
  upstream (#849).

- **`escapeFilterPart()` ließ Platzhalter am Leben.** Die Ersetzungen liefen
  nacheinander, wodurch die Funktion ihre eigenen Backslashes nachescapte: Aus
  `*` wurde erst `\*` und dann `\\*` - ein escapter Backslash gefolgt von
  einem **rohen** Sternchen. Ein Anmeldename konnte so einen Platzhalter in den
  Suchfilter einschleusen, vor jeder Anmeldung erreichbar. Escapt wird jetzt in
  einem Durchgang in der Hex-Form nach RFC 4515 Abschnitt 3; Steuerzeichen sind
  damit gleich mit abgedeckt. Übernommen von upstream (#846).

- **Bind-DN und die drei freien Suchfilter wurden nicht von Steuerzeichen
  befreit.** Nur `ldapAgentPassword` lief durch `FILTER_FLAG_STRIP_LOW`.
  `ldapAgentName`, `ldapUserFilter`, `ldapLoginFilter` und `ldapGroupFilter`
  gehen wörtlich in die Bind-Anfrage bzw. in den Suchfilter und landen damit
  auf der Leitung zu dem Dienst, der auf dem eingestellten Host und Port
  lauscht. Übernommen von upstream (#844).

### Added

- Neue Systemeinstellung `user_ldap.home_base_dirs` (Standard: leer): Liste
  zusätzlicher absoluter Verzeichnisse, in denen ein aus LDAP gelesenes
  Heimatverzeichnis liegen darf. Das Datenverzeichnis gilt immer.

### Changed

- Die Testsammlung für `Access`, `Configuration` und `UserEntry` entspricht
  jetzt der von upstream: 278 statt 223 Fälle. Gegen den alten Code fallen
  davon 43.

## [0.20.3] - 2026-08-13

### Changed

- README als Betriebsdokumentation neu geschrieben: Installation, Einstellungen,
  Kommandozeile und Fehlersuche; tote und fremde Verweise entfernt.

## [0.20.2] - 2026-08-13

### Changed

- Produktname, Beschreibung und übersetzte Zeichenketten nennen owncloud.online;
  Verweise auf Fehlerbereich, Repository und Dokumentation zeigen auf das eigene
  Repository. Screenshots aus fremden Repositories entfernt.

## [Unreleased] - XXXX-XX-XX


## [0.19.1] - 2024-10-23

### Fixed

- Upstream #823 - Don't hit the LDAP server if no exposed attribute is configured


## [0.19.0] - 2024-01-18

### Added

- Upstream #801 - Exposed attributes


## [0.18.0] - 2023-07-27

### Changed

- Upstream #796 - Use alphabetical order instead of natural order
- Upstream #787 - Always return an int from Symfony Command execute method
- Upstream #783 - Add condition in `doConnect` for checking network timeout is set
- Minimum core version 10.11, mimimum php version 7.4
- Dependencies updated.

### Fixed

- Upstream #800 - guessBaseDN: try to parse domain part from a user given in email syntax


## [0.17.0] - 2022-02.24

### Changed

- Upstream #762 - Include a notice for the recursive group membership algorithm
- Upstream #748 - [full-ci] Expose group's displayname
- Upstream #734 - Rise default network timeout to 15 secs
- Upstream #675 - [full-ci] Do not check for the username if if has been processed already

### Fixed

- Upstream #771 - fix: fix config file for new transifex client
- Upstream #770 - Don't trim binary attribute values as this might corrupt the value if it starts with a non-printable ASCII byte.


## [0.16.1] - 2022-11-07

### Changed

- Upstream #678 - Change color of warning sign for expert settings
- Upstream #711 - Decode binary GUID where we normally expected string (eDirectory)
- Upstream #760 - Binary converter

## [0.16.0] - 2021-11-25

### Changed
- Fix user group selection layout Upstream #672
- Add a command to invalidate the LDAP cache Upstream #670
- Adjust command description Upstream #671
- Drop PHP 7.2 in sonar-project.properties Upstream #680
- When checking memberof, apply the group filter after getting all groups Upstream #683
- Include "memberOf"-based algorithms to find users within groups Upstream #697

## [0.15.4] - 2021-07-13

### Fixed
- user_ldap 0.15.3 double quote in passwords does not work Upstream #662
- Fix display errors in combination with other apps Upstream #660
- [QA] Frontend breaks with other auser auth apps Upstream #659
- [QA] tab break into new line hides content Upstream #656
- [QA] Login Attributes: LDAP Filter misagligned output Upstream #653

## [0.15.3] - 2021-06-14

### Fixed
- Fix display errors in combination with other apps Upstream #660
- Fixed read LDAP attribute value 0 returned as null Upstream #599
- Security: filter special characters from password field Upstream #636
- LDAP multiple base dns break pagination Upstream #307

### Changed
- Add warning for disabling email login regarding strict login check Upstream #581 (Requires 10.5.0)
- Facelift Upstream #597
- Bump libraries



## [0.15.2] - 2020-06-16

### Fixed
- Reissue search in case of missing cookie in continued paged search - Upstream #551

### Changed
- Bump libraries

## [0.15.1] - 2020-03-09

### Fixed

- Allow plus in LDAP usernames - Upstream #490
- Easier tls - Upstream #512

## [0.15.0] - 2019-12-20

### Fixed

- Don't delete / disable Users if they change their DN - Upstream #470

### Changed

- Drop PHP 7.0 - Upstream #474
- Requires ownCloud min-version 10.4

## [0.14.0] - 2019-11-11

### Added

- Add network timeout setting - Upstream #324
- Log bind errors Upstream #436
- Reuse existing LDAP accounts if available Upstream #165

### Changed

- Allow avatars to be changed by users if not provided by LDAP - Upstream #188
- Remove PHP 5.6 support - Upstream #388
- Clean up Application initialization code - Upstream #396
- Remove unused use statements - Upstream #399 Upstream #400
- Simplify connection: Get rid of init method Upstream #437
- Replace magic numbers with constants Upstream #435

### Fixed

- Only return users valid for ownCloud when getting LDAP group members - Upstream #12
- Fix paging when limit is used - Upstream #315
- Extract housekeeping part from new LDAP wizard - Upstream #396
- loginName2UserName is already called for an object, not a class - Upstream #398
- Remove unused use statements - Upstream #400 - Upstream #399
- Include port only if there is port Upstream #425
- Remove no longer existing job from appinfo Upstream #430


## [0.13.0] - 2018-12-11

### Changed

- Set max version to 10 because core is switching to Semver - Upstream #319
- Update Screenshot - Upstream #306

### Fixed

- Remove legacy table and resolve dn encoding issues - Upstream #248
- Suppress "invalid quota" message if quota isn't set for the user - Upstream #237


## [0.12.0] - 2018-11-05

### Added

- Store "samaccountname" in user preferences table - Upstream #254
- PHP 7.2 support - Upstream #280

### Fixed

- Display name and email will not be editable from the profile page - Upstream #218
- Do not throw exception when user not found on LDAP during login - Upstream #269
- Users with no avatar in LDAP are now able to add avatar again, like in ownCloud 9.1 - Upstream #256
- Replaced deprecated config API calls - Upstream #258

### Removed

- Removed obsolete comment reference to ldapUserCleanupInterval - Upstream #213

## [0.11.0] - 2018-04-19

### Added

- Ability to output ldap configurations (`ldap:show-config`) as json Upstream #185

### Changed

- Frontend routes converted to proper Controllers Upstream #199
- Fully leverage core account synchronisation Upstream #156
- Improved error log messages Upstream #194

### Fixed

- Error with encrypted storage when a unsynchronized user logs in for the first time Upstream #178
- Properly use filters when synchronizing mapped users by dn Upstream #168
- Fallback to ownClouds default quota, if the provided quota by ldap can not be parsed correctly Upstream #153

## [0.10.0] - 2017-12-20

### Fixed

- Rework LDAP app to match account table logic Upstream #125
- Use custom uuid attribute if configured - Upstream #158
- Sync displayname on login - Upstream #157
- Fix working with LDAP replica server - Upstream #138
- Allow specifying the prefix for occ ldap:create-empty-config - Upstream #7
- Remove fix for ldap installation - Upstream #132
- Make the time between needsRefresh configurable - Upstream #120
- Keep the current quota if no suitable quota is found - Upstream #123
- Only use IndexIgnore if mod_autoindex.c is enabled/loaded - Upstream #112
- Remove unneeded account updates during sync - Upstream #109
- Fix possible race condition - Upstream #8
- Remove automatic enable of a configuration - Upstream #10
- Add missing spaces to log message - Upstream #110
- Add hint for max search term length - Upstream #105
- Allow proxy to check next server - Upstream #101


