<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\SignatureText\SignatureTextPolicy;
use OCA\Libresign\Service\SignatureBackgroundService;
use OCA\Libresign\Service\SignatureTextService;
use OCP\Files\IAppData;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\ITempManager;
use PHPUnit\Framework\MockObject\MockObject;

final class SignatureBackgroundServiceConsolidatedTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private SignatureBackgroundService $service;
	private IAppConfig $appConfig;
	private IAppData&MockObject $appData;
	private IConfig&MockObject $config;
	private ITempManager&MockObject $tempManager;
	private SignatureTextService&MockObject $signatureTextService;
	private PolicyService&MockObject $policyService;

	public function setUp(): void {
		$this->appData = $this->createMock(IAppData::class);
		$this->appConfig = $this->getMockAppConfigWithReset();
		$this->config = $this->createMock(IConfig::class);
		$this->tempManager = $this->createMock(ITempManager::class);
		$this->signatureTextService = $this->createMock(SignatureTextService::class);
		$this->policyService = $this->createMock(PolicyService::class);
	}

	private function getClass(): SignatureBackgroundService {
		$this->service = new SignatureBackgroundService(
			$this->appData,
			$this->appConfig,
			$this->config,
			$this->tempManager,
			$this->signatureTextService,
			$this->policyService,
		);
		return $this->service;
	}

	private function createResolvedPolicy(string $effectiveValue): ResolvedPolicy {
		$policy = new ResolvedPolicy();
		$policy->setEffectiveValue($effectiveValue);
		return $policy;
	}

	public function testGetSignatureBackgroundTypeReadsFromConsolidatedStampPolicy(): void {
		$consolidatedValue = json_encode([
			'template' => 'Dear @{firstname}',
			'template_font_size' => 9.0,
			'signature_font_size' => 9.0,
			'signature_width' => 90.0,
			'signature_height' => 60.0,
			'background_type' => 'custom',
			'render_mode' => 'default',
		], JSON_THROW_ON_ERROR);

		$this->policyService->method('resolve')
			->with(SignatureTextPolicy::KEY)
			->willReturn($this->createResolvedPolicy($consolidatedValue));

		$result = $this->getClass()->getSignatureBackgroundType();

		$this->assertSame('custom', $result);
	}

	public function testGetSignatureBackgroundTypeDefaultsToDefaultWhenNotSet(): void {
		$this->policyService->method('resolve')
			->with(SignatureTextPolicy::KEY)
			->willReturn($this->createResolvedPolicy('{}'));

		$result = $this->getClass()->getSignatureBackgroundType();

		$this->assertSame('default', $result);
	}

	public function testGetSignatureBackgroundTypeReturnsDefaultForInvalidValue(): void {
		$consolidatedValue = json_encode([
			'background_type' => 'INVALID_VALUE',
		], JSON_THROW_ON_ERROR);

		$this->policyService->method('resolve')
			->with(SignatureTextPolicy::KEY)
			->willReturn($this->createResolvedPolicy($consolidatedValue));

		$result = $this->getClass()->getSignatureBackgroundType();

		// Invalid values normalize to 'default'
		$this->assertSame('default', $result);
	}

	public function testIsEnabledReturnsFalseWhenBackgroundTypeIsDeleted(): void {
		$consolidatedValue = json_encode([
			'background_type' => 'deleted',
		], JSON_THROW_ON_ERROR);

		$this->policyService->method('resolve')
			->with(SignatureTextPolicy::KEY)
			->willReturn($this->createResolvedPolicy($consolidatedValue));

		$result = $this->getClass()->isEnabled();

		$this->assertFalse($result);
	}

	public function testIsEnabledReturnsTrueWhenBackgroundTypeIsNotDeleted(): void {
		$consolidatedValue = json_encode([
			'background_type' => 'custom',
		], JSON_THROW_ON_ERROR);

		$this->policyService->method('resolve')
			->with(SignatureTextPolicy::KEY)
			->willReturn($this->createResolvedPolicy($consolidatedValue));

		$result = $this->getClass()->isEnabled();

		$this->assertTrue($result);
	}
}
