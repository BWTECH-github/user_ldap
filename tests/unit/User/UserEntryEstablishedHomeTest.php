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
 * Heimatverzeichnisse bereits angelegter Konten, etwa nach einem Umzug von
 * ownCloud 10: Der Kern verwendet für ein Konto mit eingetragenem
 * Heimatverzeichnis den Wert aus dem Verzeichnis nicht mehr
 * (SyncService::syncHome). Maßgeblich ist deshalb das gespeicherte
 * Heimatverzeichnis - ist es zulässig, darf die Eingrenzung des Werts aus dem
 * Verzeichnis die Anmeldung nicht abbrechen; ist es unzulässig, bleibt das
 * Konto gesperrt, egal was das Verzeichnis liefert. Neue Konten bleiben der
 * Eingrenzung unterworfen.
 *
 * @group DB
 */
class UserEntryEstablishedHomeTest extends \Test\TestCase {
	private const DATA_DIR = '/srv/oco-online/data';

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
	/** @var string[] Wert von user_ldap.home_base_dirs */
	private $baseDirs = [];

	protected function setUp(): void {
		parent::setUp();
		$this->db = \OC::$server->getDatabaseConnection();
		$this->config = $this->createMock(IConfig::class);
		$this->config->method('getSystemValue')
			->willReturnCallback(function ($key, $default = '') {
				if ($key === 'datadirectory') {
					return self::DATA_DIR;
				}
				if ($key === 'user_ldap.home_base_dirs') {
					return $this->baseDirs;
				}
				if ($key === 'apps_paths') {
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

	private static function serverRoot(): string {
		return \rtrim(\OC::$SERVERROOT, '/');
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
	 * Die Namensregel attr:homeDirectory wurde erst nach dem Anlegen gesetzt:
	 * gespeichert ist datadir/<uid>, das Verzeichnis liefert /home/<uid>. Unter
	 * ownCloud 10 lief die Anmeldung, der Kern hat die Abweichung nur
	 * protokolliert - so bleibt es.
	 */
	public function testStoredHomeInDataDirWithDifferentPathFromDirectoryIsKept(): void {
		$uid = $this->uid();
		$this->insertAccount($uid, self::DATA_DIR . "/$uid");
		$before = $this->accountRow($uid);

		$this->logger->expects($this->once())->method('info');
		$this->logger->expects($this->never())->method('error');

		$entry = $this->entry($uid, "/home/$uid", $this->db);
		self::assertSame("/home/$uid", $entry->getHome());
		self::assertSame("/home/$uid", $entry->getHome());
		self::assertSame($before, $this->accountRow($uid));
	}

	/**
	 * occ user:move-home auf der Altinstanz oder ein geändertes Attribut: das
	 * gespeicherte und das gelieferte Heimatverzeichnis laufen auseinander.
	 */
	public function testDifferentPathFromDirectoryIsAcceptedForAnEstablishedAccount(): void {
		$uid = $this->uid();
		$this->insertAccount($uid, '/srv/homes/alice');
		$before = $this->accountRow($uid);

		$this->logger->expects($this->never())->method('error');

		self::assertSame('/srv/homes/mallory', $this->entry($uid, '/srv/homes/mallory', $this->db)->getHome());
		self::assertSame($before, $this->accountRow($uid));
	}

	public function testPathFromDirectoryIsReturnedNormalized(): void {
		$uid = $this->uid();
		$this->insertAccount($uid, '/srv/homes//alice/');

		$entry = $this->entry($uid, '/srv/homes/./alice', $this->db);
		self::assertSame('/srv/homes/alice', $entry->getHome());
	}

	/**
	 * Ein unzulässiges Heimatverzeichnis in oc_accounts sperrt das Konto auch
	 * dann, wenn das Verzeichnis inzwischen einen harmlosen Wert liefert: Der
	 * Kern würde mit dem gespeicherten weiterarbeiten.
	 *
	 * @dataProvider providesForbiddenStoredHomes
	 */
	public function testForbiddenStoredHomeIsRefusedWhateverTheDirectorySays(string $storedHome): void {
		$uid = $this->uid();
		$this->insertAccount($uid, $storedHome);

		$this->expectException(\OutOfBoundsException::class);
		// relativer Wert = im Datenverzeichnis, für ein neues Konto zulässig
		$this->entry($uid, 'x', $this->db)->getHome();
	}

	public function providesForbiddenStoredHomes(): array {
		return [
			'code root' => [self::serverRoot()],
			'apps directory' => [self::serverRoot() . '/apps'],
			'config directory' => [self::serverRoot() . '/config'],
			'above the code root' => [\dirname(self::serverRoot())],
			'system directory' => ['/etc/alice'],
		];
	}

	/**
	 * @dataProvider providesForbiddenStoredHomes
	 * @dataProvider providesSystemDirectoryHomes
	 */
	public function testForbiddenStoredHomeIsRefusedWithTheSamePathFromDirectory(string $home): void {
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
			'usr local' => ['/usr/local/alice'],
			'wurzel' => ['/'],
			// enthält /var/log, /var/run und /var/spool
			'var' => ['/var'],
		];
	}

	/**
	 * Ein Heimatverzeichnis gleich dem Datenverzeichnis oder oberhalb davon
	 * sieht die Dateien aller Konten.
	 *
	 * @dataProvider providesHomesAtOrAboveDataDir
	 */
	public function testStoredHomeAtOrAboveDataDirIsRefused(string $home): void {
		$uid = $this->uid();
		$this->insertAccount($uid, $home);

		$this->expectException(\OutOfBoundsException::class);
		$this->entry($uid, '/srv/homes/alice', $this->db)->getHome();
	}

	public function providesHomesAtOrAboveDataDir(): array {
		return [
			'data directory' => [self::DATA_DIR],
			'above the data directory' => [\dirname(self::DATA_DIR)],
		];
	}

	public function testForbiddenStoredHomeIsLoggedOnce(): void {
		$uid = $this->uid();
		$this->insertAccount($uid, '/etc/alice');

		$this->logger->expects($this->once())->method('error')
			->with($this->stringContains('/etc/alice'));

		$entry = $this->entry($uid, '/etc/alice', $this->db);
		for ($i = 0; $i < 3; $i++) {
			try {
				$entry->getHome();
				self::fail('OutOfBoundsException erwartet');
			} catch (\OutOfBoundsException $e) {
				// erwartet
			}
		}
	}

	/**
	 * Standardlayout einer Altinstanz: Datenverzeichnis im Code-Baum. Liegt es
	 * nach dem Umzug woanders, zeigen die gespeicherten Heimatverzeichnisse in
	 * den Code-Baum - freischalten lässt sich das über home_base_dirs.
	 */
	public function testStoredHomeInCodeTreeIsAcceptedWhenListedAsBaseDir(): void {
		$oldData = self::serverRoot() . '/data-alt';
		$uid = $this->uid();
		$this->insertAccount($uid, "$oldData/$uid");

		$this->baseDirs = [$oldData];
		self::assertSame("$oldData/$uid", $this->entry($uid, "$oldData/$uid", $this->db)->getHome());

		$this->baseDirs = [];
		$this->expectException(\OutOfBoundsException::class);
		$this->entry($uid, "$oldData/$uid", $this->db)->getHome();
	}

	/**
	 * Auch in einem erlaubten Verzeichnis bleibt verboten, was den Code-Baum
	 * oder ein Systemverzeichnis enthält.
	 */
	public function testBaseDirDoesNotPermitAHomeContainingTheCodeTree(): void {
		$this->baseDirs = ['/'];
		$uid = $this->uid();
		$this->insertAccount($uid, \dirname(self::serverRoot()));

		$this->expectException(\OutOfBoundsException::class);
		$this->entry($uid, '/srv/homes/alice', $this->db)->getHome();
	}

	public function testNewAccountWithoutStoredHomeIsRefused(): void {
		$this->expectException(\OutOfBoundsException::class);
		$this->entry($this->uid(), '/srv/homes/alice', $this->db)->getHome();
	}

	public function testNewAccountInsideDataDirIsAccepted(): void {
		$uid = $this->uid();
		self::assertSame(self::DATA_DIR . "/$uid", $this->entry($uid, $uid, $this->db)->getHome());
	}

	/**
	 * Solange oc_accounts.home leer ist, übernimmt der Kern den Wert aus dem
	 * Verzeichnis - dann muss die Eingrenzung greifen.
	 */
	public function testAccountWithEmptyHomeIsTreatedAsNew(): void {
		$uid = $this->uid();
		$this->insertAccount($uid, '');

		$this->expectException(\OutOfBoundsException::class);
		$this->entry($uid, '/srv/homes/alice', $this->db)->getHome();
	}

	public function testAccountOfAnotherBackendIsNotAdopted(): void {
		$uid = $this->uid();
		$this->insertAccount($uid, '/srv/homes/alice', 'OC\User\Database');

		$this->expectException(\OutOfBoundsException::class);
		$this->entry($uid, '/srv/homes/alice', $this->db)->getHome();
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

		$this->logger->expects($this->never())->method('info');
		$this->logger->expects($this->never())->method('error');

		$entry = $this->entry($uid, self::DATA_DIR . '/alice', $this->db);
		self::assertSame(self::DATA_DIR . '/alice', $entry->getHome());
	}
}
