<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Crl;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Enum\CrlValidationStatus;
use OCA\Libresign\Service\Crl\CrlRevocationChecker;
use OCA\Libresign\Service\Crl\Ldap\LdapCrlDownloader;
use OCP\IAppConfig;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\ITempManager;
use OCP\IURLGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Minimal subclass that promotes the two pure-algorithm methods to public so
 * tests can call them directly without reflection.
 */
class CrlRevocationCheckerTestable extends CrlRevocationChecker {
	public function publicIsSerialNumberInCrl(string $crlText, string $serialNumber): bool {
		return $this->isSerialNumberInCrl($crlText, $serialNumber);
	}

	public function publicExtractRevocationDateFromCrlText(string $crlText, array $serialNumbers): ?string {
		return $this->extractRevocationDateFromCrlText($crlText, $serialNumbers);
	}
}

class CrlRevocationCheckerTest extends TestCase {
	private IConfig&MockObject $config;
	private IAppConfig&MockObject $appConfig;
	private IURLGenerator&MockObject $urlGenerator;
	private ITempManager&MockObject $tempManager;
	private LoggerInterface&MockObject $logger;
	private ICacheFactory&MockObject $cacheFactory;
	private ICache&MockObject $crlCache;
	private LdapCrlDownloader&MockObject $ldapDownloader;
	private CrlRevocationCheckerTestable $checker;

	protected function setUp(): void {
		$this->config = $this->createMock(IConfig::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->tempManager = $this->createMock(ITempManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->crlCache = $this->createMock(ICache::class);
		$this->cacheFactory = $this->createMock(ICacheFactory::class);
		$this->cacheFactory->method('createDistributed')->willReturn($this->crlCache);
		$this->ldapDownloader = $this->createMock(LdapCrlDownloader::class);

		$this->checker = new CrlRevocationCheckerTestable(
			$this->config,
			$this->appConfig,
			$this->urlGenerator,
			$this->tempManager,
			$this->logger,
			$this->cacheFactory,
			$this->ldapDownloader,
		);
	}

	#[DataProvider('dataProviderCrlRevocationDateExtraction')]
	public function testExtractRevocationDateFromCrlText(
		string $crlText,
		array $serialNumbers,
		?string $expectedDate,
		string $description,
	): void {
		$result = $this->checker->publicExtractRevocationDateFromCrlText($crlText, $serialNumbers);

		$this->assertSame($expectedDate, $result, $description);
	}

	public static function dataProviderCrlRevocationDateExtraction(): array {
		$crlText = implode("\n", [
			'Revoked Certificates:',
			'    Serial Number: 0A',
			'        Revocation Date: Jan 28 12:34:56 2026 GMT',
			'    Serial Number: 0B',
			'        Revocation Date: Jan 29 01:02:03 2026 GMT',
		]);

		return [
			'Extract first revocation date' => [
				$crlText,
				['0A'],
				'2026-01-28T12:34:56+00:00',
				'Expected revocation date for serial 0A',
			],
			'Extract second revocation date with hex' => [
				$crlText,
				['0B', '0C'],
				'2026-01-29T01:02:03+00:00',
				'Expected revocation date for serial 0B',
			],
			'Revocation date not found' => [
				$crlText,
				['0D'],
				null,
				'No revocation date should be returned when serial not present',
			],
		];
	}

	#[DataProvider('dataProviderCrlExternalValidationDisabled')]
	public function testValidateReturnsDisabledWhenSettingOff(
		array $crlUrls,
		CrlValidationStatus $expectedStatus,
		string $description,
	): void {
		$this->appConfig
			->method('getValueBool')
			->with(Application::APP_ID, 'crl_external_validation_enabled', true)
			->willReturn(false);

		$this->config
			->method('getSystemValue')
			->with('trusted_domains', [])
			->willReturn([]);

		$result = $this->checker->validate($crlUrls, '');

		$this->assertSame($expectedStatus, $result['status'], $description);
	}

	public static function dataProviderCrlExternalValidationDisabled(): array {
		return [
			'all external HTTP URLs skipped' => [
				['http://crl.external.example.com/crl.crl'],
				CrlValidationStatus::DISABLED,
				'External HTTP CRL URL should return disabled when setting is off',
			],
			'all external LDAP URLs skipped' => [
				['ldap://ldap.external.example.com/cn=CRL,o=Example'],
				CrlValidationStatus::DISABLED,
				'External LDAP CRL URL should return disabled when setting is off',
			],
			'mix of external URLs all skipped' => [
				[
					'http://crl.external.example.com/crl.crl',
					'ldap://ldap.external.example.com/cn=CRL,o=Example',
				],
				CrlValidationStatus::DISABLED,
				'All external CRL URLs should return disabled when setting is off',
			],
			'empty URL list' => [
				[],
				CrlValidationStatus::NO_URLS,
				'Empty URL list should always return no_urls regardless of setting',
			],
		];
	}

	public function testValidateDoesNotReturnDisabledWhenSettingOn(): void {
		$this->appConfig
			->method('getValueBool')
			->with(Application::APP_ID, 'crl_external_validation_enabled', true)
			->willReturn(true);

		$this->config
			->method('getSystemValue')
			->with('trusted_domains', [])
			->willReturn([]);

		// With the setting on, an inaccessible external URL should fail, not be skipped.
		$result = $this->checker->validate(['http://crl.unreachable.invalid/crl.crl'], '');

		$this->assertNotSame(CrlValidationStatus::DISABLED, $result['status'], 'Status should not be disabled when external validation is enabled');
	}

	public function testValidateChecksLocalUrlsEvenWhenExternalValidationDisabled(): void {
		$this->appConfig
			->method('getValueBool')
			->with(Application::APP_ID, 'crl_external_validation_enabled', true)
			->willReturn(false);

		// Make the domain trusted so isLocalCrlUrl returns true for this host.
		$this->config
			->method('getSystemValue')
			->with('trusted_domains', [])
			->willReturn(['cloud.example.com']);

		// A URL on the trusted (local) host must be attempted even when external
		// validation is disabled. It will fail here because there is no real CRL,
		// but it must NOT be counted as a disabled/skipped URL.
		$result = $this->checker->validate(
			['http://cloud.example.com/apps/libresign/crl/instance/1/openssl'],
			''
		);

		$this->assertNotSame(CrlValidationStatus::DISABLED, $result['status'], 'Local CRL URLs must not be skipped when external validation is disabled');
	}

	#[DataProvider('dataProviderIsSerialNumberInCrl')]
	public function testIsSerialNumberInCrlNormalizesSerialNumber(
		string $crlText,
		string $serialNumber,
		bool $expected,
		string $description,
	): void {
		$result = $this->checker->publicIsSerialNumberInCrl($crlText, $serialNumber);

		$this->assertSame($expected, $result, $description);
	}

	public static function dataProviderIsSerialNumberInCrl(): array {
		return [
			'exact uppercase match' => [
				"    Serial Number: AB\n",
				'AB',
				true,
				'Exact serial in CRL text should match',
			],
			'lowercase input normalised to uppercase' => [
				"    Serial Number: AB\n",
				'ab',
				true,
				'Lowercase serial input should be uppercased before comparison',
			],
			'leading zeros in input stripped before comparison' => [
				"    Serial Number: AB\n",
				'00AB',
				true,
				'Leading zeros in the input serial should be stripped',
			],
			'leading zeros in CRL covered by regex wildcard' => [
				"    Serial Number: 00AB\n",
				'AB',
				true,
				'Leading zeros in the CRL text are matched by the 0* regex wildcard',
			],
			'zero serial number preserved' => [
				"    Serial Number: 0\n",
				'0',
				true,
				'Serial number zero should match Serial Number: 0',
			],
			'all-zero input normalised to single zero' => [
				"    Serial Number: 0\n",
				'000',
				true,
				'All-zero input (000) should be normalised to 0',
			],
			'serial not present returns false' => [
				"    Serial Number: AB\n",
				'CD',
				false,
				'Serial absent from CRL text should not match',
			],
		];
	}

}
