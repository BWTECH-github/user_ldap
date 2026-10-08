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

namespace OCA\User_LDAP\Migrations;

use OCP\Migration\IOutput;
use OCP\Migration\ISimpleMigration;

/**
 * Frühere Fassungen haben Hintergrundjobs über info.xml eingetragen, deren
 * Klassen es nicht mehr gibt: UpdateGroups (bis 0.13.x) und CleanUp (0.9.0,
 * ownCloud 10.0.0). Der Kern entfernt Jobs einer App beim Update nicht
 * (OC_App::setupBackgroundJobs), und seine Liste in DropOldJobs führt CleanUp
 * nur mit führendem Backslash - die Einträge ohne ihn blieben stehen, ließen
 * sich nicht bauen und wurden bei jedem Versuch protokolliert.
 *
 * Entfernt werden nur Einträge genau dieser Klassen, und nur solange es die
 * Klasse nicht gibt.
 */
class Version20260926130000 implements ISimpleMigration {
	public const ORPHANED_JOBS = [
		'OCA\User_LDAP\Jobs\UpdateGroups',
		'\OCA\User_LDAP\Jobs\UpdateGroups',
		'OCA\User_LDAP\Jobs\CleanUp',
		'\OCA\User_LDAP\Jobs\CleanUp',
	];

	/**
	 * @param IOutput $out
	 */
	public function run(IOutput $out) {
		$jobList = \OC::$server->getJobList();
		foreach (self::ORPHANED_JOBS as $class) {
			if (\class_exists(\ltrim($class, '\\')) || !$jobList->has($class, null)) {
				continue;
			}
			$jobList->remove($class, null);
			$out->info("Removed the background job $class of an earlier user_ldap version; the class no longer exists.");
		}
	}
}
