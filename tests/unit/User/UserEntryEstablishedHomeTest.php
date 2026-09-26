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

namespace OCA\User_LDAP\Tests\User;

use OCA\User_LDAP\Connection;
use OCA\User_LDAP\User\UserEntry;
use OCA\User_LDAP\User_Proxy;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\ILogger;

/**
 * Bestandsschutz für Heimatverzeichnisse nach einem Umzug: Ein Konto, das aus
 * einer ownCloud-10-Datenbank mitkommt, hat sein Heimatverzeichnis schon in
 * oc_accounts. Liegt es außerhalb des Datenverzeichnisses, darf die seit 0.20.4
 * geltende Eingrenzung seine Anmeldung nicht abbrechen - neue oder andere Pfade
 * aus dem Verzeichnis bleiben aber abgewiesen.
 *
 * @group DB
 */
class UserEntryEstablishedHomeTest extends \Test\TestCase {
	private const DATA_DIR = '/var/owncloud-online-data';

	/** @var IConfig|\PHPUnit\Framework\MockObject\MockObject */
	private $config;
	/** @var ILogger|\PHPUnit\Framework\MockObject\MockObject */
	private $logger;
	/** @var Connection|\PHPUnit\Framework\MockObject\MockObject */
	private $connection;
	/** @var IDBConnection */
	private $db;
	/** @var string[] */
	private $createdUids = [];

	protected function setUp(): void {
		parent::setUp();
		$this->db = \OC::$server->getDatabaseConnection();
		$this->config = $this->createMock(IConfig::class);
		$this->config->method('getSystemValue')
			->willReturnCallback(function ($key, $default = '') {
				if ($key === 'datadirectory') {
					return self::DATA_DIR;
				}
				if ($key === 'user_ldap.home_base_dirs' || $key === 'apps_paths') {
					return [];
				}
				return $default;
			});
		$this->logger = $this->createMock(ILogger::class);
		$this->connection = $this->createMock(Connection::class);
		$this->connection->method('__get')
			->willReturnCallback(function ($key) {
				return $key === 'homeFolderNamingRule' ? 'attr:home' : 'mail';
			});
	}

	protected function tearDown(): void {
		foreach ($this->createdUids as $uid) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('accounts')
				->where($qb->expr()->eq('user_id', $qb->createNamedParameter($uid)))
				->execute();
		}
		parent::tearDown();
	}

	private function insertAccount(string $uid, string $home, string $backend = User_Proxy::class): void {
		$qb = $this->db->getQueryBuilder();
		$qb->insert('accounts')
			->values([
				'user_id' => $qb->createNamedParameter($uid),
				'lower_user_id' => $qb->createNamedParameter(\strtolower($uid)),
				'backend' => $qb->createNamedParameter($backend),
				'home' => $qb->createNamedParameter($home),
				'state' => $qb->createNamedParameter(1),
			])
			->execute();
		$this->createdUids[] = $uid;
	}

	private function accountRow(string $uid): array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('*')
			->from('accounts')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($uid)))
			->execute();
		$row = $result->fetchAssociative();
		$result->free();
		return $row;
	}

	private function entry(string $uid, string $ldapHome, ?IDBConnection $db): UserEntry {
		$entry = new UserEntry(
			$this->config,
			$this->logger,
			$this->connection,
			[
				'dn' => [0 => "uid=$uid,ou=people,dc=example,dc=org"],
				'mail' => [0 => "$uid@example.org"],
				'home' => [0 => $ldapHome],
			],
			$db
		);
		$entry->setOwnCloudUID($uid);
		return $entry;
	}

	private function uid(): string {
		return 'ldap-bestand-' . \uniqid();
	}

	public function testEstablishedHomeOutsideDataDirIsKept(): void {
		$uid = $this->uid();
		$this->insertAccount($uid, '/srv/homes/alice');
		$before = $this->accountRow($uid);

		// der Kern ruft getHome() je Sync bis zu dreimal auf - eine Zeile genügt
		$this->logger->expects($this->once())->method('info');
		$this->logger->expects($this->never())->method('error');

		$entry = $this->entry($uid, '/srv/homes/alice', $this->db);
		self::assertSame('/srv/homes/alice', $entry->getHome());
		// weitere Aufrufe: gleiches Ergebnis, nichts wird geschrieben
		self::assertSame('/srv/homes/alice', $entry->getHome());
		self::assertSame('/srv/homes/alice', $entry->getHome());
		self::assertSame($before, $this->accountRow($uid));
	}

	/**
	 * Ein eingetragenes Heimatverzeichnis in einem Systemverzeichnis kann nur aus
	 * einem manipulierten Verzeichnisdienst stammen und bleibt gesperrt.
	 *
	 * @dataProvider providesSystemDirectoryHomes
	 */
	public function testEstablishedHomeInSystemDirectoryIsRefused(string $home): void {
		$uid = $this->uid();
		$this->insertAccount($uid, $home);

		$this->expectException(\OutOfBoundsException::class);
		$this->entry($uid, $home, $this->db)->getHome();
	}

	public function providesSystemDirectoryHomes(): array {
		return [
			'etc' => ['/etc'],
			'unter etc' => ['/etc/alice'],
			'root' => ['/root'],
			'var log' => ['/var/log/alice'],
			'proc' => ['/proc/self'],
			'wurzel' => ['/'],
		];
	}

	public function testStoredHomeIsComparedAfterNormalization(): void {
		$uid = $this->uid();
		$this->insertAccount($uid, '/srv/homes//alice/');

		$entry = $this->entry($uid, '/srv/homes/./alice', $this->db);
		self::assertSame('/srv/homes/alice', $entry->getHome());
	}

	public function testDifferentPathFromDirectoryIsStillRefused(): void {
		$uid = $this->uid();
		$this->insertAccount($uid, '/srv/homes/alice');

		$this->expectException(\OutOfBoundsException::class);
		$this->entry($uid, '/srv/homes/mallory', $this->db)->getHome();
	}

	public function testNewAccountWithoutStoredHomeIsRefused(): void {
		$this->expectException(\OutOfBoundsException::class);
		$this->entry($this->uid(), '/srv/homes/alice', $this->db)->getHome();
	}

	public function testAccountOfAnotherBackendIsNotAdopted(): void {
		$uid = $this->uid();
		$this->insertAccount($uid, '/srv/homes/alice', 'OC\User\Database');

		$this->expectException(\OutOfBoundsException::class);
		$this->entry($uid, '/srv/homes/alice', $this->db)->getHome();
	}

	/**
	 * Auch ein bereits eingetragenes Heimatverzeichnis im Code-Baum bleibt
	 * gesperrt: genau dafür gibt es die Eingrenzung.
	 *
	 * @dataProvider providesApplicationTreePaths
	 */
	public function testEstablishedHomeInApplicationTreeIsRefused(string $suffix): void {
		$home = \rtrim(\OC::$SERVERROOT, '/') . $suffix;
		$uid = $this->uid();
		$this->insertAccount($uid, $home);

		$this->expectException(\OutOfBoundsException::class);
		$this->entry($uid, $home, $this->db)->getHome();
	}

	public function providesApplicationTreePaths(): array {
		return [
			'code root' => [''],
			'apps directory' => ['/apps'],
			'config directory' => ['/config'],
		];
	}

	public function testEstablishedHomeAboveApplicationTreeIsRefused(): void {
		$home = \dirname(\rtrim(\OC::$SERVERROOT, '/'));
		$uid = $this->uid();
		$this->insertAccount($uid, $home);

		$this->expectException(\OutOfBoundsException::class);
		$this->entry($uid, $home, $this->db)->getHome();
	}

	public function testWithoutDatabaseConnectionNothingIsAdopted(): void {
		$uid = $this->uid();
		$this->insertAccount($uid, '/srv/homes/alice');

		$this->expectException(\OutOfBoundsException::class);
		$this->entry($uid, '/srv/homes/alice', null)->getHome();
	}

	public function testHomeInsideDataDirIsUnaffected(): void {
		$uid = $this->uid();
		$this->insertAccount($uid, '/srv/homes/alice');

		// neuer Bestand im Datenverzeichnis: die Eingrenzung lässt ihn ohnehin
		// durch, der gespeicherte Wert spielt keine Rolle
		$entry = $this->entry($uid, self::DATA_DIR . '/alice', $this->db);
		self::assertSame(self::DATA_DIR . '/alice', $entry->getHome());
	}
}
