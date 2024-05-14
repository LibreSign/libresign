<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Settings\AdminSettings;
use OCP\IL10N;
use OCP\IURLGenerator;

/**
 * @internal
 */
final class AdminSettingsTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private AdminSettings $adminSettings;
	public function setUp(): void {
		$l10n = $this->createMock(IL10N::class);
		$l10n
			->method('t')
			->will($this->returnArgument(0));
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$this->adminSettings = new AdminSettings(
			$l10n,
			$urlGenerator
		);
	}

	public function testGetId():void {
		$actual = $this->adminSettings->getID();
		$this->assertEquals(Application::APP_ID, $actual);
	}

	public function testGetName():void {
		$actual = $this->adminSettings->getName();
		$this->assertEquals('LibreSign', $actual);
	}

	public function testGetPriority():void {
		$actual = $this->adminSettings->getPriority();
		$this->assertEquals(60, $actual);
	}

	public function testGetIcon():void {
		$actual = $this->adminSettings->getIcon();
		$this->assertIsString($actual);
	}
}
