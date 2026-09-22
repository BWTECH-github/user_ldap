#!/bin/bash
# Testdaten für pruefe-user-ldap.js: ein kleiner Verzeichnisbaum im lokalen
# slapd der Testumgebung (Standard-Basis dc=ho,dc=bw-tech,dc=de).
#
# Aufruf (als root, slapd lokal, Zugriff über ldapi:/// mit SASL EXTERNAL):
#   bash ldap-testdaten.sh einrichten   ou=ldapprobe mit zwei Konten
#                                       (ldapprobe1/-2, Passwort $LDAP_PASS) und
#                                       der Gruppe ldapprobe-gruppe; dafür
#                                       bekommt root über ldapi vorübergehend
#                                       Schreibrecht auf die Datenbank
#   bash ldap-testdaten.sh entfernen    Baum, Schreibrecht und LDAP-
#                                       Konfigurationen der Testinstanz, die auf
#                                       die Probe zeigen, wieder weg
#
# Anonymes Lesen erlaubt der slapd der Testumgebung ohnehin (to * by * read);
# die Anmeldung als Probekonto prüft das Passwort per Bind.
#
# @copyright Copyright (c) 2026, BW-Tech GmbH
# @license AGPL-3.0
set -euo pipefail
BASIS_DN=${LDAP_BASIS:-dc=ho,dc=bw-tech,dc=de}
LDAP_PASS=${LDAP_PASS:-Ldap-Probe-2026!x}
ZIEL=${OC_ZIEL:-/opt/oco-schnell}
OCC="sudo -u www-data php8.4 $ZIEL/occ"
PROBE="ou=ldapprobe,$BASIS_DN"
ROOTREGEL='to * by dn.exact=gidNumber=0+uidNumber=0,cn=peercred,cn=external,cn=auth manage by * break'
LDAPI="-Q -Y EXTERNAL -H ldapi:///"

VORHER=/root/.ldap-probe-konfigurationen

datenbank() {
	ldapsearch $LDAPI -LLL -b cn=config "(olcSuffix=$BASIS_DN)" dn | sed -n 's/^dn: //p' | head -1
}

# Kennungen der LDAP-Konfigurationen der Instanz (s01, s02 …; die erste über
# den Assistenten angelegte trägt die leere Kennung, hier „LEER“). Aus der
# Datenbank, weil ldap:show-config bei nur einer Konfiguration deren Werte
# ohne Kennung ausgibt.
konfigurationen() {
	(cd "$ZIEL" && sudo -u www-data php8.4 -r 'require "lib/base.php"; $r = \OC::$server->getDatabaseConnection()->executeQuery("SELECT configkey FROM *PREFIX*appconfig WHERE appid = ? AND configkey LIKE ?", ["user_ldap", "%ldap_configuration_active"]); while (($z = $r->fetch()) !== false) { $p = substr($z["configkey"], 0, -strlen("ldap_configuration_active")); echo $p === "" ? "LEER" : $p, "\n"; }')
}

case "${1:-}" in
	einrichten)
		[ -f "$VORHER" ] || konfigurationen > "$VORHER"
		DB=$(datenbank)
		if ! ldapsearch $LDAPI -LLL -o ldif-wrap=no -b "$DB" olcAccess | grep -q 'peercred,cn=external,cn=auth manage'; then
			ldapmodify $LDAPI > /dev/null <<LDIF
dn: $DB
changetype: modify
add: olcAccess
olcAccess: {0}$ROOTREGEL
LDIF
		fi
		HASH=$(slappasswd -s "$LDAP_PASS")
		ldapdelete $LDAPI -r "$PROBE" > /dev/null 2>&1 || true
		ldapadd $LDAPI > /dev/null <<LDIF
dn: $PROBE
objectClass: organizationalUnit
ou: ldapprobe

dn: ou=people,$PROBE
objectClass: organizationalUnit
ou: people

dn: ou=groups,$PROBE
objectClass: organizationalUnit
ou: groups

dn: uid=ldapprobe1,ou=people,$PROBE
objectClass: inetOrgPerson
uid: ldapprobe1
cn: Probe Eins
sn: Eins
givenName: Probe
displayName: Probe Eins
mail: ldapprobe1@example.org
userPassword: $HASH

dn: uid=ldapprobe2,ou=people,$PROBE
objectClass: inetOrgPerson
uid: ldapprobe2
cn: Probe Zwei
sn: Zwei
givenName: Probe
displayName: Probe Zwei
mail: ldapprobe2@example.org
userPassword: $HASH

dn: cn=ldapprobe-gruppe,ou=groups,$PROBE
objectClass: groupOfNames
cn: ldapprobe-gruppe
member: uid=ldapprobe1,ou=people,$PROBE
LDIF
		# viele Anmeldungen der Probe sollen nicht in die Drosselung laufen
		(cd "$ZIEL" && sudo -u www-data php8.4 -r 'require "lib/base.php"; \OC::$server->getDatabaseConnection()->executeStatement("DELETE FROM *PREFIX*bruteforce_attempts");')
		echo "eingerichtet: $PROBE (ldapprobe1, ldapprobe2, ldapprobe-gruppe)"
		;;
	entfernen)
		# Reihenfolge zählt: erst der Baum weg, dann erkennt user_ldap die
		# Konten als gelöscht und gibt sie zum Löschen frei; erst danach die
		# Konfiguration, ohne die ldap:check-user nichts mehr prüfen kann.
		ldapdelete $LDAPI -r "$PROBE" > /dev/null 2>&1 || true
		# Der interne Name eines LDAP-Kontos ist standardmäßig seine entryUUID,
		# nicht der Anmeldename – er steht in der Zuordnungstabelle.
		KONTEN=$(cd "$ZIEL" && sudo -u www-data php8.4 -r 'require "lib/base.php"; $r = \OC::$server->getDatabaseConnection()->executeQuery("SELECT owncloud_name FROM *PREFIX*ldap_user_mapping WHERE ldap_dn LIKE ?", ["%ou=ldapprobe,%"]); while (($z = $r->fetch()) !== false) { echo $z["owncloud_name"], "\n"; }')
		for u in $KONTEN; do
			$OCC ldap:check-user --force "$u" > /dev/null 2>&1 || true
			$OCC user:delete "$u" > /dev/null 2>&1 || true
		done
		# Nur Konfigurationen, die seit „einrichten“ dazugekommen sind (die
		# Probe legt über den Assistenten eine an); ältere bleiben unberührt.
		for id in $(konfigurationen); do
			if ! grep -qx "$id" "$VORHER" 2>/dev/null; then
				[ "$id" = LEER ] && id=""
				$OCC ldap:delete-config "$id" > /dev/null 2>&1 || true
			fi
		done
		rm -f "$VORHER"
		# Zuordnungen der Probe (DN → interner Name) mit wegräumen
		(cd "$ZIEL" && sudo -u www-data php8.4 -r 'require "lib/base.php"; $db = \OC::$server->getDatabaseConnection(); foreach (["ldap_user_mapping", "ldap_group_mapping"] as $t) { $db->executeStatement("DELETE FROM *PREFIX*$t WHERE ldap_dn LIKE ?", ["%ou=ldapprobe,%"]); }')
		DB=$(datenbank)
		if ldapsearch $LDAPI -LLL -o ldif-wrap=no -b "$DB" olcAccess | grep -q 'peercred,cn=external,cn=auth manage'; then
			ldapmodify $LDAPI > /dev/null <<LDIF
dn: $DB
changetype: modify
delete: olcAccess
olcAccess: {0}$ROOTREGEL
LDIF
		fi
		echo "entfernt"
		;;
	*)
		echo "Aufruf: $0 einrichten|entfernen" >&2
		exit 1
		;;
esac
