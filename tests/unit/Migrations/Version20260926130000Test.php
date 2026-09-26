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

namespace OCA\User_LDAP\Tests\Migrations;

use OCA\User_LDAP\Migrations\Version20260926130000;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use Test\TestCase;

/**
 * Verwaiste Hintergrundjobs früherer user_ldap-Fassungen verschwinden, alles
 * andere in oc_jobs bleibt.
 *
 * @group DB
 */
class Version20260926130000Test extends TestCase {
	/** ein Job einer Klasse, die es gibt - bleibt stehen */
	private const OTHER_JOB = 'OC\Command\CommandJob';
	private const OTHER_ARGUMENT = ['user_ldap-migrationstest'];

	/** @var IDBConnection */
	private $db;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once __DIR__ . '/../../../appinfo/Migrations/Version20260926130000.php';
	}

	protected function setUp(): void {
		parent::setUp();
		$this->db = \OC::$server->getDatabaseConnection();
		$this->removeTestJobs();
	}

	protected function tearDown(): void {
		$this->removeTestJobs();
		parent::tearDown();
	}

	private function removeTestJobs(): void {
		$jobList = \OC::$server->getJobList();
		foreach (Version20260926130000::ORPHANED_JOBS as $class) {
			$jobList->remove($class);
		}
		$jobList->remove(self::OTHER_JOB, self::OTHER_ARGUMENT);
	}

	/**
	 * @return array[] alle Zeilen aus oc_jobs (id, class, argument)
	 */
	private function jobs(): array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('id', 'class', 'argument')
			->from('jobs')
			->orderBy('id')
			->execute();
		$rows = $result->fetchAllAssociative();
		$result->free();
		return $rows;
	}

	public function testOrphanedJobsAreRemovedAndNothingElse(): void {
		$jobList = \OC::$server->getJobList();
		$before = $this->jobs();
		// so haben 0.9.0 bis 0.13.x sie über info.xml eingetragen
		$jobList->add('OCA\User_LDAP\Jobs\UpdateGroups');
		$jobList->add('OCA\User_LDAP\Jobs\CleanUp');
		$jobList->add(self::OTHER_JOB, self::OTHER_ARGUMENT);
		$other = $this->jobs();

		$out = $this->createMock(IOutput::class);
		$out->expects($this->exactly(2))->method('info');
		(new Version20260926130000())->run($out);

		self::assertFalse($jobList->has('OCA\User_LDAP\Jobs\UpdateGroups', null));
		self::assertFalse($jobList->has('OCA\User_LDAP\Jobs\CleanUp', null));
		self::assertTrue($jobList->has(self::OTHER_JOB, self::OTHER_ARGUMENT));
		self::assertCount(\count($before) + 1, $this->jobs());
		// die übrigen Zeilen sind genau die von vorher plus der fremde Job
		$expected = \array_values(\array_filter($other, static function (array $row) {
			return \strpos($row['class'], 'User_LDAP\Jobs') === false;
		}));
		self::assertEquals($expected, $this->jobs());
	}

	public function testSecondRunIsANoop(): void {
		\OC::$server->getJobList()->add('OCA\User_LDAP\Jobs\UpdateGroups');
		(new Version20260926130000())->run($this->createMock(IOutput::class));
		$afterFirst = $this->jobs();

		$out = $this->createMock(IOutput::class);
		$out->expects($this->never())->method('info');
		(new Version20260926130000())->run($out);

		self::assertEquals($afterFirst, $this->jobs());
	}
}
