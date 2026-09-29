<?php
/**
 * @copyright Copyright (c) 2026, BW-Tech GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\User_LDAP;

use Test\TestCase;

/**
 * Helper::nextPossibleConfigurationPrefix() gegen echte Einträge in
 * oc_appconfig: occ ldap:create-empty-config und die Schaltfläche „neue
 * Konfiguration" im Admin-Panel legen unter dem gelieferten Präfix an.
 *
 * @group DB
 */
class HelperConfigurationPrefixTest extends TestCase {
	private const REFERENCE_KEY = 'ldap_configuration_active';

	/** @var string[] Präfixe, die der Test angelegt hat */
	private $created = [];

	protected function setUp(): void {
		parent::setUp();
		// Ausgangslage: keine LDAP-Konfiguration
		foreach ((new Helper())->getServerConfigurationPrefixes() as $prefix) {
			$this->removeConfiguration($prefix);
		}
	}

	protected function tearDown(): void {
		foreach ($this->created as $prefix) {
			$this->removeConfiguration($prefix);
		}
		parent::tearDown();
	}

	private function addConfiguration(string $prefix): void {
		\OC::$server->getConfig()->setAppValue('user_ldap', $prefix . self::REFERENCE_KEY, '0');
		$this->created[] = $prefix;
	}

	private function removeConfiguration(string $prefix): void {
		\OC::$server->getConfig()->deleteAppValue('user_ldap', $prefix . self::REFERENCE_KEY);
	}

	/**
	 * Ruft nextPossibleConfigurationPrefix() auf und sammelt dabei alle
	 * PHP-Meldungen (Deprecations, Warnungen), statt sie ins Protokoll laufen
	 * zu lassen.
	 *
	 * @return array{0: string, 1: string[]} Präfix und Meldungen
	 */
	private function nextPrefix(): array {
		$notices = [];
		\set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline) use (&$notices) {
			$notices[] = "$errstr at $errfile#$errline";
			return true;
		});
		try {
			$prefix = (new Helper())->nextPossibleConfigurationPrefix();
		} finally {
			\restore_error_handler();
		}
		return [$prefix, $notices];
	}

	public function testFirstConfigurationGetsS01WithoutDeprecation(): void {
		[$prefix, $notices] = $this->nextPrefix();

		self::assertSame(['s01', []], [$prefix, $notices]);
	}

	public function testLegacyEmptyPrefixAloneLeadsToS01(): void {
		$this->addConfiguration('');

		[$prefix, $notices] = $this->nextPrefix();

		self::assertSame(['s01', []], [$prefix, $notices]);
	}

	public function testNextNumberFollowsHighestNumberedPrefix(): void {
		$this->addConfiguration('');
		$this->addConfiguration('s01');
		$this->addConfiguration('s03');

		[$prefix, $notices] = $this->nextPrefix();

		self::assertSame(['s04', []], [$prefix, $notices]);
	}

	/**
	 * Eine benannte Konfiguration (occ ldap:create-empty-config test), die im
	 * Alphabet hinter 's01' liegt, darf nicht zu 's01' zurückführen - sonst
	 * überschreibt das Admin-Panel die bestehende s01 mit Vorgabewerten.
	 */
	public function testNamedConfigurationDoesNotLeadBackToExistingPrefix(): void {
		$this->addConfiguration('s01');
		$this->addConfiguration('test');

		[$prefix, $notices] = $this->nextPrefix();

		self::assertSame(['s02', []], [$prefix, $notices]);
	}

	public function testOnlyNamedConfigurationLeadsToS01(): void {
		$this->addConfiguration('corp');

		[$prefix, $notices] = $this->nextPrefix();

		self::assertSame(['s01', []], [$prefix, $notices]);
	}

	/**
	 * Ab der hundertsten Konfiguration sortiert 's100' als Zeichenkette vor
	 * 's99'; gezählt wird nach der Zahl.
	 */
	public function testNumbersAreComparedNumerically(): void {
		$this->addConfiguration('s99');
		$this->addConfiguration('s100');

		[$prefix, $notices] = $this->nextPrefix();

		self::assertSame(['s101', []], [$prefix, $notices]);
	}

	/**
	 * Groß geschriebene Präfixe zählen mit: bei einer Sortierfolge ohne
	 * Groß-/Kleinschreibung wären 'S05…' und 's05…' derselbe Schlüssel.
	 */
	public function testUpperCasePrefixCountsLikeLowerCase(): void {
		$this->addConfiguration('S05');

		[$prefix, $notices] = $this->nextPrefix();

		self::assertSame(['s06', []], [$prefix, $notices]);
	}

	/**
	 * Nie ein bestehendes Präfix, auch wenn eines mit mehr als neun Stellen
	 * genau auf die nächste Zahl fällt.
	 */
	public function testNeverReturnsAnExistingPrefix(): void {
		$this->addConfiguration('s999999999');
		$this->addConfiguration('s1000000000');

		[$prefix, $notices] = $this->nextPrefix();

		self::assertSame(['s1000000001', []], [$prefix, $notices]);
	}
}
