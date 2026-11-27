<?php

declare(strict_types=1);

namespace OCA\Libresign\Tests\Unit;

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Libresign\Capabilities;
use OCA\Libresign\Service\Envelope\EnvelopeService;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\Confetti\ConfettiPolicy;
use OCA\Libresign\Service\Policy\Provider\DrawInkColor\DrawInkColorPolicy;
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
	private EnvelopeService&MockObject $envelopeService;
	private PolicyService&MockObject $policyService;

	#[\Override]
	public function setUp(): void {
		parent::setUp();
		$this->signerElementsService = $this->createMock(SignerElementsService::class);
		$this->signatureTextService = $this->createMock(SignatureTextService::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->envelopeService = $this->createMock(EnvelopeService::class);
		$this->policyService = $this->createMock(PolicyService::class);
	}

	private function getClass(): Capabilities {
		$this->capabilities = new Capabilities(
			$this->signerElementsService,
			$this->signatureTextService,
			$this->appManager,
			$this->envelopeService,
			$this->policyService,
		);
		return $this->capabilities;
	}

	private function mockResolvedPolicies(bool $showConfetti = true, string $drawInkColor = '#000000'): void {
		$values = [
			ConfettiPolicy::KEY => $showConfetti,
			DrawInkColorPolicy::KEY => $drawInkColor,
		];
		$this->policyService->method('resolve')
			->willReturnCallback(fn (string $key): ResolvedPolicy => (new ResolvedPolicy())
				->setPolicyKey($key)
				->setEffectiveValue($values[$key]));
	}

	#[DataProvider('providerSignElementsIsAvailable')]
	public function testSignElementsIsAvailable($isEnabled, $expected): void {
		$this->mockResolvedPolicies();
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

	#[DataProvider('providerShowConfetti')]
	public function testShowConfetti(bool $configValue, bool $expected): void {
		$this->mockResolvedPolicies(showConfetti: $configValue);
		$capabilities = $this->getClass()->getCapabilities();
		$this->assertEquals($expected, $capabilities['libresign']['config']['show-confetti']);
	}

	public static function providerShowConfetti(): array {
		return [
			[true, true],
			[false, false],
		];
	}

	public function testDrawInkColor(): void {
		$this->mockResolvedPolicies(drawInkColor: '#1f2a5a');
		$capabilities = $this->getClass()->getCapabilities();
		$this->assertSame('#1f2a5a', $capabilities['libresign']['config']['sign-elements']['draw-ink-color']);
	}
}
