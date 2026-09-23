<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Crl;

use OCA\Libresign\Enum\CrlValidationStatus;
use OCA\Libresign\Service\Crl\CrlRevocationChecker;
use OCA\Libresign\Service\Crl\Ldap\LdapCrlDownloader;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\CrlValidation\CrlValidationPolicy;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\ITempManager;
use OCP\IURLGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class CrlLegacyDistributionPointTest extends TestCase {
	#[DataProvider('legacyLocalUrls')]
	public function testLegacyLocalDistributionPointReturnsActionableStatus(string $url): void {
		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValue')
			->with('trusted_domains', [])
			->willReturn(['cloud.example.com']);

		$policyService = $this->createMock(PolicyService::class);
		$policyService->method('resolve')
			->with(CrlValidationPolicy::KEY)
			->willReturn((new ResolvedPolicy())
				->setPolicyKey(CrlValidationPolicy::KEY)
				->setEffectiveValue(true));

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$tempManager = $this->createMock(ITempManager::class);
		$logger = $this->createMock(LoggerInterface::class);
		$cache = $this->createMock(ICache::class);
		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($cache);
		$ldapDownloader = $this->createMock(LdapCrlDownloader::class);

		$checker = new CrlRevocationChecker(
			$config,
			$policyService,
			$urlGenerator,
			$tempManager,
			$logger,
			$cacheFactory,
			$ldapDownloader,
		);

		$result = $checker->validate([$url], '');

		self::assertSame(CrlValidationStatus::LEGACY_DISTRIBUTION_POINT, $result['status']);
	}

	public static function legacyLocalUrls(): array {
		return [
			'front controller disabled' => ['https://cloud.example.com/apps/libresign/crl'],
			'front controller enabled' => ['https://cloud.example.com/index.php/apps/libresign/crl'],
			'trailing slash' => ['https://cloud.example.com/apps/libresign/crl/'],
		];
	}
}
