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

namespace OCA\User_LDAP\Tests\Command;

use OCA\User_LDAP\Command\TestConfig;
use OCA\User_LDAP\Helper;
use OCA\User_LDAP\LDAP;
use OCP\IConfig;
use Symfony\Component\Console\Tester\CommandTester;
use Test\TestCase;

/**
 * occ ldap:test-config gegen einen Server, der die Verbindung annimmt
 */
class TestConfigTest extends TestCase {
	/** @var LDAP|\PHPUnit\Framework\MockObject\MockObject */
	private $ldap;
	/** @var CommandTester */
	private $commandTester;

	protected function setUp(): void {
		parent::setUp();

		$values = [
			't01ldap_host' => 'example.org',
			't01ldap_port' => '389',
			't01ldap_display_name' => 'displayName',
			't01ldap_group_display_name' => 'cn',
			't01ldap_login_filter' => '(uid=%uid)',
			't01ldap_configuration_active' => '1',
			't01ldap_dn' => 'cn=admin',
			't01ldap_base' => 'dc=example,dc=org',
			't01ldap_agent_password' => \base64_encode('secret'),
		];
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static fn ($app, $key, $default) => $values[$key] ?? $default
		);
		$helper = $this->createMock(Helper::class);
		$helper->method('getServerConfigurationPrefixes')->willReturn(['t01']);

		$this->ldap = $this->createMock(LDAP::class);
		$this->ldap->method('areLDAPFunctionsAvailable')->willReturn(true);
		$this->ldap->method('connect')->willReturn('ldapResource');
		$this->ldap->method('isResource')->willReturn(true);
		$this->ldap->method('setOption')->willReturn(true);

		$this->commandTester = new CommandTester(new TestConfig($config, $helper, $this->ldap));
	}

	public function testAcceptedBindIsReportedAsValid(): void {
		$this->ldap->method('bind')->willReturn(true);

		$this->commandTester->execute(['configID' => 't01']);

		$this->assertStringContainsString('the connection could be established', $this->commandTester->getDisplay());
	}

	public function testRejectedBindIsNamedInsteadOfAnException(): void {
		$this->ldap->method('bind')->willReturn(false);

		$this->commandTester->execute(['configID' => 't01']);

		$this->assertStringContainsString('the Bind failed', $this->commandTester->getDisplay());
	}

	public function testRejectedBindStillEndsWithAnErrorStatus(): void {
		// bisher über die unbehandelte Ausnahme; Skripte verlassen sich darauf
		$this->ldap->method('bind')->willReturn(false);

		$status = $this->commandTester->execute(['configID' => 't01']);

		$this->assertSame(1, $status);
	}
}
