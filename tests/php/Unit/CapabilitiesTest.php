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

	#[DataProvider('providerSignElementsIsAvailable')]
	public function testSignElementsIsAvailable($isEnabled, $expected): void {
		$resolved = (new ResolvedPolicy())
			->setPolicyKey(ConfettiPolicy::KEY)
			->setEffectiveValue(true);
		$this->policyService->method('resolve')
			->with(ConfettiPolicy::KEY)
			->willReturn($resolved);
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
		$resolved = (new ResolvedPolicy())
			->setPolicyKey(ConfettiPolicy::KEY)
			->setEffectiveValue($configValue);
		$this->policyService->method('resolve')
			->with(ConfettiPolicy::KEY)
			->willReturn($resolved);
		$capabilities = $this->getClass()->getCapabilities();
		$this->assertEquals($expected, $capabilities['libresign']['config']['show-confetti']);
	}

	public static function providerShowConfetti(): array {
		return [
			[true, true],
			[false, false],
		];
	}
}
