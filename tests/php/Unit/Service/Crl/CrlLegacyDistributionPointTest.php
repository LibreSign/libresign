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
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\CrlValidation\CrlValidationPolicy;
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
	public function testLegacyLocalDistributionPointReturnsActionableStatus(string $templateUrl, string $url): void {
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
		$urlGenerator->method('linkToRouteAbsolute')->willReturn($templateUrl);
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

	public function testSimilarLocalPathIsNotTreatedAsLegacyDistributionPoint(): void {
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
		$urlGenerator->method('linkToRouteAbsolute')->willReturn('https://cloud.example.com/nextcloud/apps/libresign/crl/libresign_INSTANCEID_999999_ENGINETYPE.crl');
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

		$result = $checker->validate(['https://cloud.example.com/prefix/index.php/apps/libresign/crl'], '');

		self::assertSame(CrlValidationStatus::URLS_INACCESSIBLE, $result['status']);
	}

	public static function legacyLocalUrls(): array {
		$rootTemplate = 'https://cloud.example.com/apps/libresign/crl/libresign_INSTANCEID_999999_ENGINETYPE.crl';
		$subdirTemplate = 'https://cloud.example.com/nextcloud/apps/libresign/crl/libresign_INSTANCEID_999999_ENGINETYPE.crl';
		$appsInWebrootTemplate = 'https://cloud.example.com/apps/nextcloud/apps/libresign/crl/libresign_INSTANCEID_999999_ENGINETYPE.crl';

		return [
			'root webroot without front controller' => [$rootTemplate, 'https://cloud.example.com/apps/libresign/crl'],
			'root webroot with front controller' => [$rootTemplate, 'https://cloud.example.com/index.php/apps/libresign/crl'],
			'root webroot trailing slash' => [$rootTemplate, 'https://cloud.example.com/apps/libresign/crl/'],
			'subdirectory without front controller' => [$subdirTemplate, 'https://cloud.example.com/nextcloud/apps/libresign/crl'],
			'subdirectory with front controller' => [$subdirTemplate, 'https://cloud.example.com/nextcloud/index.php/apps/libresign/crl'],
			'subdirectory trailing slash' => [$subdirTemplate, 'https://cloud.example.com/nextcloud/apps/libresign/crl/'],
			'webroot containing apps segment' => [$appsInWebrootTemplate, 'https://cloud.example.com/apps/nextcloud/apps/libresign/crl'],
		];
	}
}
