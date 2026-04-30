<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Crl;

use OCA\Libresign\Enum\CrlValidationStatus;
use OCA\Libresign\Service\Crl\CrlRevocationChecker;
use OCA\Libresign\Service\Crl\CrlService;
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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Minimal subclass that promotes the two pure-algorithm methods to public so
 * tests can call them directly without reflection.
 */
class CrlRevocationCheckerTestable extends CrlRevocationChecker {
	private ?CrlService $crlService = null;
	private string|false|null $remoteCrlContent = null;

	public function publicIsSerialNumberInCrl(string $crlText, string $serialNumber): bool {
		return $this->isSerialNumberInCrl($crlText, $serialNumber);
	}

	public function publicExtractRevocationDateFromCrlText(string $crlText, array $serialNumbers): ?string {
		return $this->extractRevocationDateFromCrlText($crlText, $serialNumbers);
	}

	public function publicGetLocalCrlPattern(): string {
		return $this->getLocalCrlPattern();
	}

	public function publicGenerateLocalCrl(string $crlUrl): ?string {
		return $this->generateLocalCrl($crlUrl);
	}

	public function publicDownloadCrlContent(string $url): ?string {
		return $this->downloadCrlContent($url);
	}

	public function setCrlService(CrlService $crlService): void {
		$this->crlService = $crlService;
	}

	public function setRemoteCrlContent(string|false|null $remoteCrlContent): void {
		$this->remoteCrlContent = $remoteCrlContent;
	}

	#[\Override]
	protected function getCrlService(): CrlService {
		if ($this->crlService !== null) {
			return $this->crlService;
		}

		return parent::getCrlService();
	}

	#[\Override]
	protected function fetchRemoteCrlContent(string $url, $context): string|false {
		if ($this->remoteCrlContent !== null) {
			return $this->remoteCrlContent;
		}

		return parent::fetchRemoteCrlContent($url, $context);
	}
}

class CrlRevocationCheckerTest extends TestCase {
	private IConfig&MockObject $config;
	private PolicyService&MockObject $policyService;
	private IURLGenerator&MockObject $urlGenerator;
	private ITempManager&MockObject $tempManager;
	private LoggerInterface&MockObject $logger;
	private ICacheFactory&MockObject $cacheFactory;
	private ICache&MockObject $crlCache;
	private LdapCrlDownloader&MockObject $ldapDownloader;
	private CrlRevocationCheckerTestable $checker;

	protected function setUp(): void {
		$this->config = $this->createMock(IConfig::class);
		$this->policyService = $this->createMock(PolicyService::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->tempManager = $this->createMock(ITempManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->crlCache = $this->createMock(ICache::class);
		$this->cacheFactory = $this->createMock(ICacheFactory::class);
		$this->cacheFactory->method('createDistributed')->willReturn($this->crlCache);
		$this->ldapDownloader = $this->createMock(LdapCrlDownloader::class);

		$this->checker = new CrlRevocationCheckerTestable(
			$this->config,
			$this->policyService,
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
		$this->mockExternalValidationEnabled(false);

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
				CrlValidationStatus::DISABLED,
				'Empty URL list should return disabled when external validation is off',
			],
		];
	}

	public function testValidateReturnsNoUrlsForEmptyListWhenSettingOn(): void {
		$this->mockExternalValidationEnabled(true);

		$result = $this->checker->validate([], '');

		$this->assertSame(CrlValidationStatus::NO_URLS, $result['status'], 'Empty URL list should return no_urls when external validation is on');
	}

	public function testValidateDoesNotReturnDisabledWhenSettingOn(): void {
		$this->mockExternalValidationEnabled(true);

		$this->config
			->method('getSystemValue')
			->with('trusted_domains', [])
			->willReturn([]);

		// With the setting on, an inaccessible external URL should fail, not be skipped.
		$result = $this->checker->validate(['http://crl.unreachable.invalid/crl.crl'], '');

		$this->assertNotSame(CrlValidationStatus::DISABLED, $result['status'], 'Status should not be disabled when external validation is enabled');
	}

	public function testValidateChecksLocalUrlsEvenWhenExternalValidationDisabled(): void {
		$this->mockExternalValidationEnabled(false);

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

	/**
	 * In a given request the CRL route resolves to one specific host. The tests
	 * below assert that the recognition pattern does not depend on that host.
	 */
	private function mockCrlRouteUrlGenerator(): void {
		$this->urlGenerator
			->method('linkToRouteAbsolute')
			->with('libresign.crl.getRevocationList', [
				'instanceId' => 'INSTANCEID',
				'generation' => 999999,
				'engineType' => 'ENGINETYPE',
			])
			->willReturn('https://cloud.example.com/apps/libresign/crl/libresign_INSTANCEID_999999_ENGINETYPE.crl');
	}

	#[DataProvider('dataProviderLocalCrlUrlsWithDifferentHosts')]
	public function testGetLocalCrlPatternMatchesAnyHost(
		string $url,
		string $expectedInstanceId,
		string $expectedGeneration,
		string $expectedEngineType,
	): void {
		// A certificate's embedded CRL DP host is fixed at issuance and may differ
		// from the current request host (e.g. signing through the API from another
		// origin). The pattern must still recognise the local CRL and capture its
		// instanceId / generation / engineType regardless of the host.
		$this->mockCrlRouteUrlGenerator();

		$pattern = $this->checker->publicGetLocalCrlPattern();

		$this->assertSame(1, preg_match($pattern, $url, $matches), 'Local CRL URL should match');
		$this->assertSame($expectedInstanceId, $matches[1], 'instanceId should be captured');
		$this->assertSame($expectedGeneration, $matches[2], 'generation should be captured');
		$this->assertSame($expectedEngineType, $matches[3], 'engineType should be captured');
	}

	public static function dataProviderLocalCrlUrlsWithDifferentHosts(): array {
		return [
			'same host' => ['https://cloud.example.com/apps/libresign/crl/libresign_abc123_4_o.crl', 'abc123', '4', 'o'],
			'different host and port' => ['http://localhost:9000/apps/libresign/crl/libresign_abc123_4_o.crl', 'abc123', '4', 'o'],
			'different host with index.php' => ['http://host.docker.internal:9000/index.php/apps/libresign/crl/libresign_abc123_4_o.crl', 'abc123', '4', 'o'],
		];
	}

	public function testGetLocalCrlPatternRejectsNonCrlUrls(): void {
		$this->mockCrlRouteUrlGenerator();

		$pattern = $this->checker->publicGetLocalCrlPattern();

		$this->assertSame(
			0,
			preg_match($pattern, 'https://cloud.example.com/apps/libresign/crl/not-a-crl.txt'),
			'A non-CRL path must not match the local CRL pattern',
		);
	}

	public function testGenerateLocalCrlMemoizesPerRequest(): void {
		$this->mockCrlRouteUrlGenerator();

		$crlService = $this->createMock(CrlService::class);
		$crlService->expects($this->once())
			->method('generateCrlDer')
			->with('abc123', 4, 'o')
			->willReturn('memoized-der');

		$this->checker->setCrlService($crlService);

		$url = 'https://cloud.example.com/apps/libresign/crl/libresign_abc123_4_o.crl';

		$this->assertSame('memoized-der', $this->checker->publicGenerateLocalCrl($url));
		$this->assertSame('memoized-der', $this->checker->publicGenerateLocalCrl($url));
	}

	public function testDownloadCrlContentCachesEncodedBinaryValue(): void {
		$binaryContent = "\x30\x82\x01\x00";

		$this->crlCache->expects($this->once())
			->method('get')
			->with(sha1('https://crl.example.com/current.crl'))
			->willReturn(null);

		$this->crlCache->expects($this->once())
			->method('set')
			->with(
				sha1('https://crl.example.com/current.crl'),
				'base64:' . base64_encode($binaryContent),
				86400
			);

		$this->checker->setRemoteCrlContent($binaryContent);

		$this->assertSame($binaryContent, $this->checker->publicDownloadCrlContent('https://crl.example.com/current.crl'));
	}

	public function testDownloadCrlContentDecodesEncodedCachedBinaryValue(): void {
		$binaryContent = "\x30\x82\x01\x00";

		$this->crlCache->expects($this->once())
			->method('get')
			->with(sha1('https://crl.example.com/current.crl'))
			->willReturn('base64:' . base64_encode($binaryContent));

		$this->crlCache->expects($this->never())
			->method('set');

		$this->assertSame($binaryContent, $this->checker->publicDownloadCrlContent('https://crl.example.com/current.crl'));
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

	private function mockExternalValidationEnabled(bool $enabled): void {
		$resolved = (new ResolvedPolicy())
			->setPolicyKey(CrlValidationPolicy::KEY)
			->setEffectiveValue($enabled);
		$this->policyService->method('resolve')
			->with(CrlValidationPolicy::KEY)
			->willReturn($resolved);
	}

}
