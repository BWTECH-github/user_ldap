/**
 * LDAP-Anbindung (user_ldap) im Redesign, Ende zu Ende.
 *
 * Voraussetzung: ldap-testdaten.sh einrichten (Baum ou=ldapprobe mit
 * ldapprobe1/-2 und ldapprobe-gruppe im lokalen slapd) und KEINE andere
 * LDAP-Konfiguration auf der Instanz. Die Probe richtet die Anbindung über
 * den Assistenten ein; ldap-testdaten.sh entfernen nimmt Konfiguration, Konten
 * und Zuordnungen wieder weg.
 *
 * Geprüft wird (1440 px):
 *   - Karte so breit wie die übrigen Karten der Seite
 *   - Reiter ohne Unterstreichung, aktiver Reiter mit Akzentlinie, einzeilig
 *   - Kästchen 24 × 24 px, links neben ihrer Beschriftung
 *   - Beschriftungen über den Auswahlknöpfen, Auswahlknöpfe einzeilig
 *   - Umschalter „LDAP-Abfrage bearbeiten“ als Verweis, nicht als Pille
 *   - Assistent: Server, Base DN testen, Benutzer zählen (2), Anmeldename
 *     prüfen, Gruppen wählen und zählen (1), Konfiguration aktiv
 *   - Anmeldung als ldapprobe1 klappt; Konto trägt Anzeigename und Gruppe
 * und (400 px): Karte ohne waagerechtes Rollen, keine Konsolenfehler.
 *
 * Aufruf: OC_PASSWORD=... node tests/visual/pruefe-user-ldap.js
 *   OC_URL (Standard http://127.0.0.1:18130), LDAP_PASS, LDAP_BASIS
 *
 * @copyright Copyright (c) 2026, BW-Tech GmbH
 * @license AGPL-3.0
 */
'use strict';

let chromium;
try {
	({ chromium } = require('playwright'));
} catch (e) {
	({ chromium } = require('C:/git/owncloud.online-redesign/node_modules/playwright'));
}

const BASIS = process.env.OC_URL || 'http://127.0.0.1:18130';
const PASSWORT = process.env.OC_PASSWORD;
const LDAP_PASS = process.env.LDAP_PASS || 'Ldap-Probe-2026!x';
const LDAP_BASIS = 'ou=ldapprobe,' + (process.env.LDAP_BASIS || 'dc=ho,dc=bw-tech,dc=de');
if (!PASSWORT) {
	console.error('OC_PASSWORD fehlt.');
	process.exit(2);
}

const ergebnisse = [];
function pruefe(name, ok, zusatz) {
	ergebnisse.push({ name, ok: ok === true, zusatz: zusatz === undefined ? '' : String(zusatz) });
}

function seitenfehler(e) {
	const stelle = ((e.stack || '').split('\n')[1] || '').trim().replace(BASIS, '').slice(0, 140);
	return 'Seitenfehler: ' + e.message.slice(0, 160) + (stelle ? ' ' + stelle : '');
}

async function anmelden(browser, benutzer, passwort, breite, hoehe) {
	const kontext = await browser.newContext({ locale: 'de-DE', viewport: { width: breite, height: hoehe } });
	const seite = await kontext.newPage();
	const fehler = [];
	seite.on('console', (m) => {
		if (m.type() === 'error') {
			fehler.push(m.text().slice(0, 160) + ' @ ' + (m.location().url || '').replace(BASIS, '').slice(0, 100));
		}
	});
	seite.on('pageerror', (e) => fehler.push(seitenfehler(e)));
	await seite.goto(BASIS + '/index.php/login', { waitUntil: 'domcontentloaded' });
	await seite.fill('#user', benutzer);
	await seite.fill('#password', passwort);
	await Promise.all([seite.waitForNavigation({ timeout: 30000 }).catch(() => {}), seite.click('#submit, button[type=submit]')]);
	return { kontext, seite, fehler };
}

async function einstellungen(seite) {
	await seite.goto(BASIS + '/index.php/settings/admin?sectionid=authentication', { waitUntil: 'load' });
	await seite.waitForSelector('#ldapSettings.ui-tabs', { timeout: 30000 });
	await seite.waitForTimeout(2000);
}

async function reiter(seite, ziel) {
	await seite.locator('#ldapSettings .ui-tabs-nav a[href="' + ziel + '"]').first().click();
	await seite.waitForTimeout(1500);
}

async function feld(seite, wahl, wert) {
	await seite.fill(wahl, wert);
	await seite.locator(wahl).blur();
	await seite.waitForTimeout(1500);
}

// Warten, bis die Anzeige (Zähler, Ergebnis) einen Wert trägt
function textAbwarten(seite, wahl, muster) {
	return seite.waitForFunction(({ w, m }) => {
		const e = document.querySelector(w);
		return e && new RegExp(m).test(e.textContent) ? e.textContent.trim() : false;
	}, { w: wahl, m: muster.source }, { timeout: 20000 }).then((h) => h.jsonValue()).catch(async () => (await seite.locator(wahl).textContent().catch(() => '')) || '');
}

// Eintrag in einer ui-multiselect-Auswahl anhaken
async function mehrfachWaehlen(seite, selectId, wert) {
	await seite.locator('select#' + selectId + ' + button.ui-multiselect').click();
	await seite.waitForTimeout(600);
	const box = seite.locator('.ui-multiselect-menu:visible input[type="checkbox"][value="' + wert + '"]').first();
	if (await box.count()) {
		await box.check({ force: true });
	}
	await seite.keyboard.press('Escape');
	await seite.locator('select#' + selectId + ' + button.ui-multiselect').click();
	await seite.waitForTimeout(300);
	await seite.mouse.click(5, 5);
	await seite.waitForTimeout(1500);
}

(async () => {
	const browser = await chromium.launch();
	const a = await anmelden(browser, 'admin', PASSWORT, 1440, 1000);
	const s = a.seite;
	await einstellungen(s);

	// --- Gestalt ---------------------------------------------------------------
	const karten = await s.evaluate(() => Array.from(document.querySelectorAll('#app-content .section')).map((k) => ({ id: k.id, r: Math.round(k.getBoundingClientRect().right), w: Math.round(k.getBoundingClientRect().width) })));
	const ldapKarte = karten.find((k) => k.id === 'ldap');
	const andere = karten.filter((k) => k.id !== 'ldap' && k.w > 0);
	pruefe('Karte so breit wie die übrigen Karten', !!ldapKarte && andere.length > 0 && andere.every((k) => Math.abs(k.r - ldapKarte.r) <= 1), JSON.stringify(karten));
	const reiterLeiste = await s.evaluate(() => {
		// Nur die eigentlichen Reiter; Zustandsanzeige und Hilfe dürfen umbrechen.
		const tabs = Array.from(document.querySelectorAll('#ldapSettings .ui-tabs-nav > li')).filter((l) => l.getClientRects().length && l.querySelector('a[href^="#ldap"]'));
		const aktiv = document.querySelector('#ldapSettings .ui-tabs-nav > li.ui-state-active');
		const zeilen = new Set(tabs.map((l) => Math.round(l.getBoundingClientRect().top)));
		return {
			unterstrichen: tabs.filter((l) => getComputedStyle(l.querySelector('a')).textDecorationLine.indexOf('underline') !== -1).map((l) => l.textContent.trim()),
			akzent: aktiv ? getComputedStyle(aktiv).borderBottomColor : '',
			zeilen: zeilen.size,
		};
	});
	pruefe('Reiter ohne Unterstreichung', reiterLeiste.unterstrichen.length === 0, reiterLeiste.unterstrichen.join(','));
	pruefe('aktiver Reiter mit Akzentlinie', reiterLeiste.akzent === 'rgb(0, 128, 107)', reiterLeiste.akzent);
	pruefe('Reiter in einer Zeile (1440 px)', reiterLeiste.zeilen === 1, reiterLeiste.zeilen + ' Zeilen');
	const kopieren = await s.evaluate(() => getComputedStyle(document.getElementById('ldap_action_copy_configuration')).backgroundSize);
	pruefe('Symbol „Konfiguration kopieren“ in Knopfgröße', kopieren === '16px 16px', kopieren);

	// --- Assistent: Server -------------------------------------------------------
	await feld(s, '#ldap_host', '127.0.0.1');
	await feld(s, '#ldap_port', '389');
	await feld(s, '#ldap_base', LDAP_BASIS);
	const kaestchen = await s.evaluate(() => Array.from(document.querySelectorAll('#ldapWizard1 input[type="checkbox"]')).filter((c) => c.getClientRects().length).map((c) => {
		const r = c.getBoundingClientRect();
		const l = document.querySelector('label[for="' + c.id + '"]');
		const lr = l ? l.getBoundingClientRect() : null;
		return { id: c.id, w: Math.round(r.width), h: Math.round(r.height), links: !!lr && r.right <= lr.left + 1 && Math.abs((r.top + r.height / 2) - (lr.top + lr.height / 2)) < 12 };
	}));
	pruefe('Kästchen 24 × 24 px, links neben der Beschriftung', kaestchen.length > 0 && kaestchen.every((k) => k.w === 24 && k.h === 24 && k.links), JSON.stringify(kaestchen));
	await s.locator('#ldapWizard1 button.ldapDetectBase, #ldapWizard1 .ldapDetectBase').first().isVisible().catch(() => false);
	const testKnopf = s.locator('#ldapWizard1 button:has-text("Base DN testen"), #ldapWizard1 button.ldapTestBase').first();
	await testKnopf.click();
	const basisErgebnis = await textAbwarten(s, '#ldapTestBaseResult', /\d/);
	pruefe('Base DN testen findet die Probeeinträge', /\d+/.test(basisErgebnis) && !/No object|Kein Objekt|error|Fehler/i.test(basisErgebnis), basisErgebnis);

	// --- Benutzer ----------------------------------------------------------------------
	await reiter(s, '#ldapWizard2');
	await s.waitForFunction(() => /inetOrgPerson/.test((document.querySelector('select#ldap_userfilter_objectclass + button') || {}).textContent || ''), null, { timeout: 15000 }).catch(() => {});
	const ueber = await s.evaluate(() => {
		const l = document.querySelector('label[for="ldap_userfilter_objectclass"]');
		const k = document.querySelector('select#ldap_userfilter_objectclass + button');
		return l && k ? { labelUnten: Math.round(l.getBoundingClientRect().bottom), knopfOben: Math.round(k.getBoundingClientRect().top), knopfHoehe: Math.round(k.getBoundingClientRect().height) } : null;
	});
	pruefe('Benutzer: Beschriftung über dem Auswahlknopf, Knopf einzeilig', !!ueber && ueber.labelUnten <= ueber.knopfOben + 1 && ueber.knopfHoehe <= 40, JSON.stringify(ueber));
	const umschalter = await s.evaluate(() => {
		const b = document.getElementById('toggleRawUserFilter');
		const c = getComputedStyle(b);
		return { rahmen: c.borderTopWidth, grund: c.backgroundColor, linie: c.textDecorationLine, hoehe: Math.round(b.getBoundingClientRect().height) };
	});
	pruefe('Umschalter „LDAP-Abfrage bearbeiten“ als Verweis', umschalter.rahmen === '0px' && /rgba\(0, 0, 0, 0\)|transparent/.test(umschalter.grund) && umschalter.linie.indexOf('underline') !== -1 && umschalter.hoehe < 30, JSON.stringify(umschalter));
	// Erst zählen, wenn der Filter aus der erkannten Objektklasse steht
	await textAbwarten(s, '#ldapReadOnlyUserFilterContainer', /objectclass=inetOrgPerson/i);
	await s.waitForTimeout(1500);
	await s.locator('#ldapWizard2 button.ldapGetUserCount').click();
	const benutzerZahl = await textAbwarten(s, '#ldap_user_count', /\d/);
	pruefe('Benutzer zählen: 2', /\b2\b/.test(benutzerZahl), benutzerZahl);

	// --- Anmeldeattribute -----------------------------------------------------------------
	await reiter(s, '#ldapWizard3');
	await s.fill('#ldap_test_loginname', 'ldapprobe1');
	await s.locator('#ldap_test_loginname').dispatchEvent('keyup');
	await s.waitForTimeout(500);
	await s.locator('#ldapWizard3 button.ldapVerifyLoginName').click();
	const anmeldeMeldung = await textAbwarten(s, '#notification', /gefunden|found|verified|überprüft/i);
	pruefe('Anmeldename prüfen: Konto gefunden', /gefunden|found|verified|überprüft/i.test(anmeldeMeldung), anmeldeMeldung);

	// --- Gruppen -------------------------------------------------------------------------
	await reiter(s, '#ldapWizard4');
	await s.waitForTimeout(1500);
	const gruppenLabel = await s.evaluate(() => {
		const l = document.querySelector('label[for="ldap_groupfilter_objectclass"]');
		const k = document.querySelector('select#ldap_groupfilter_objectclass + button');
		return l && k ? { labelUnten: Math.round(l.getBoundingClientRect().bottom), knopfOben: Math.round(k.getBoundingClientRect().top), knopfHoehe: Math.round(k.getBoundingClientRect().height) } : null;
	});
	pruefe('Gruppen: Beschriftung über dem Auswahlknopf, Knopf einzeilig', !!gruppenLabel && gruppenLabel.labelUnten <= gruppenLabel.knopfOben + 1 && gruppenLabel.knopfHoehe <= 40, JSON.stringify(gruppenLabel));
	await mehrfachWaehlen(s, 'ldap_groupfilter_objectclass', 'groupOfNames');
	await s.locator('#ldapWizard4 button.ldapGetGroupCount').click();
	const gruppenZahl = await textAbwarten(s, '#ldap_group_count', /\d/);
	pruefe('Gruppen zählen: 1', /\b1\b/.test(gruppenZahl), gruppenZahl);

	// --- Fortgeschritten: aktiv --------------------------------------------------------------
	await reiter(s, '#ldapSettings-1');
	const aktiv = s.locator('#ldap_configuration_active');
	if (!(await aktiv.isChecked())) {
		await s.locator('label[for="ldap_configuration_active"]').click();
		await s.waitForTimeout(1500);
	}
	pruefe('Konfiguration aktiv', await aktiv.isChecked());
	// Die Probegruppe ist groupOfNames, ihre Mitglieder stehen in „member“;
	// der Kern-Standard uniqueMember fände niemanden.
	await s.selectOption('#ldap_group_member_assoc_attribute', 'member');
	await s.locator('#ldap_group_member_assoc_attribute').blur();
	await s.waitForTimeout(2000);
	pruefe('Gruppen-Mitgliedschaft auf „member“ gespeichert', await s.inputValue('#ldap_group_member_assoc_attribute') === 'member');
	const status = await s.evaluate(() => (document.querySelector('#ldapSettings .ui-tabs-nav .ldap_config_state_indicator') || {}).textContent || '');
	pruefe('Assistent meldet „Konfiguration OK“', /OK/.test(status), status);
	pruefe('keine Konsolenfehler (Einstellungen, 1440)', a.fehler.length === 0, a.fehler.join(' | '));

	// --- 400 px ---------------------------------------------------------------------------
	await s.setViewportSize({ width: 400, height: 800 });
	await einstellungen(s);
	const breite = await s.evaluate(() => {
		const k = document.getElementById('ldap');
		return { dok: document.documentElement.scrollWidth, karteRechts: Math.round(k.getBoundingClientRect().right), rollbreite: k.scrollWidth, sichtbar: k.clientWidth };
	});
	pruefe('400 px: kein waagerechtes Rollen, Karte im Fenster', breite.dok <= 400 && breite.karteRechts <= 400 && breite.rollbreite <= breite.sichtbar + 1, JSON.stringify(breite));
	await a.kontext.close();

	// --- Anmeldung als LDAP-Konto ---------------------------------------------------------
	const l = await anmelden(browser, 'ldapprobe1', LDAP_PASS, 1440, 900);
	const url = l.seite.url();
	pruefe('Anmeldung als ldapprobe1 klappt', !/\/login/.test(url) && /apps\/(dashboard|files)/.test(url), url.replace(BASIS, ''));
	const konto = await l.seite.evaluate(() => ({ uid: OC.getCurrentUser().uid, name: OC.getCurrentUser().displayName }));
	pruefe('LDAP-Konto trägt den Anzeigenamen aus dem Verzeichnis', konto.name === 'Probe Eins', JSON.stringify(konto));
	// Gruppen tragen wie Konten ihre entryUUID als internen Namen. Verglichen
	// wird deshalb mit dem, was die Gruppensuche für „ldapprobe-gruppe“ liefert.
	const adminSicht = await (async () => {
		const k = await anmelden(browser, 'admin', PASSWORT, 1440, 900);
		const g = await k.seite.evaluate(async (uid) => {
			const kopf = { headers: { 'OCS-APIRequest': 'true', requesttoken: OC.requestToken } };
			const mitglied = await (await fetch(OC.linkToOCS('cloud/users', 2) + encodeURIComponent(uid) + '/groups?format=json', kopf)).json();
			const suche = await (await fetch(OC.linkToOCS('cloud', 2) + 'groups?format=json&search=ldapprobe-gruppe', kopf)).json();
			return {
				mitglied: (mitglied.ocs && mitglied.ocs.data && mitglied.ocs.data.groups) || [],
				gesucht: (suche.ocs && suche.ocs.data && suche.ocs.data.groups) || [],
			};
		}, konto.uid);
		await k.kontext.close();
		return g;
	})();
	// Die Gruppensuche liefert zusätzlich Gruppen anderer Anbieter, die auf
	// jede Suche antworten (guests: guest_app) – entscheidend ist die Schnittmenge.
	pruefe('LDAP-Konto ist Mitglied von ldapprobe-gruppe', adminSicht.mitglied.length === 1 && adminSicht.gesucht.indexOf(adminSicht.mitglied[0]) !== -1, JSON.stringify(adminSicht));
	pruefe('keine Konsolenfehler (LDAP-Konto)', l.fehler.length === 0, l.fehler.join(' | '));
	await l.kontext.close();

	await browser.close();
	let fehler = 0;
	for (const e of ergebnisse) {
		console.log((e.ok ? 'OK    ' : 'FEHL  ') + e.name + (e.zusatz ? '  (' + e.zusatz + ')' : ''));
		if (!e.ok) {
			fehler++;
		}
	}
	console.log('\n' + (ergebnisse.length - fehler) + '/' + ergebnisse.length + ' bestanden');
	process.exit(fehler === 0 ? 0 : 1);
})().catch((e) => {
	console.error(e);
	process.exit(2);
});
