<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Libresign\Capabilities;
use OCA\Libresign\Service\SignatureTextService;
use OCA\Libresign\Service\SignerElementsService;
use OCP\App\IAppManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class CapabilitiesTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private Capabilities $capabilities;
	private SignerElementsService&MockObject $signerElementsService;
	private SignatureTextService&MockObject $signatureTextService;
	private IAppManager&MockObject $appManager;

	public function setUp(): void {
		$this->signerElementsService = $this->createMock(SignerElementsService::class);
		$this->signatureTextService = $this->createMock(SignatureTextService::class);
		$this->appManager = $this->createMock(IAppManager::class);
	}


	private function getClass(): Capabilities {
		$this->capabilities = new Capabilities(
			$this->signerElementsService,
			$this->signatureTextService,
			$this->appManager,
		);
		return $this->capabilities;
	}

	#[DataProvider('providerSignElementsIsAvailable')]
	public function testSignElementsIsAvailable($isEnabled, $expected): void {
		$this->signerElementsService->method('isSignElementsAvailable')->willReturn($isEnabled);
		$capabilities = $this->getClass()->getCapabilities();
		$this->assertEquals($expected, $capabilities['libresign']['config']['sign-elements']['is-available']);
	}

	public static function providerSignElementsIsAvailable(): array {
		return [
			[true, true],
			[false, false],
		];
	}
}
