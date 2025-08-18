<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\Certificate\RulesService;
use OCA\Libresign\Service\Certificate\ValidateService;
use OCP\IL10N;
use OCP\L10N\IFactory as IL10NFactory;
use PHPUnit\Framework\Attributes\DataProvider;

class ValidateServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {

	private IL10N $l10n;

	public function setUp(): void {
		$this->l10n = \OCP\Server::get(IL10NFactory::class)->get(Application::APP_ID);
	}

	public function getService(): RulesService {
		return new RulesService(
			$this->l10n,
		);
	}

	public function testValidateWithValidInput(): void {
		$service = new ValidateService($this->getService(), $this->l10n);
		$service->validate('CN', 'John Doe');
	}

	public function testValidateWithInvalidInput(): void {
		$service = new ValidateService($this->getService(), $this->l10n);
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage("Parameter 'CN' should be betweeen 1 and 64.");
		$service->validate('CN', str_repeat('a', 65));
	}
}
