<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Crl\Ldap;

use OCA\Libresign\Service\Crl\Ldap\ILdapConnection;
use OCA\Libresign\Service\Crl\Ldap\LdapCrlDownloader;
use OCP\ICache;
use OCP\ICacheFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LdapCrlDownloaderTest extends TestCase {
	private LoggerInterface&MockObject $logger;
	private ICacheFactory&MockObject $cacheFactory;
	private ICache&MockObject $cache;
	private ILdapConnection&MockObject $ldap;
	private LdapCrlDownloader $downloader;

	/** Reusable fake LDAP connection handle (any object will do). */
	private object $fakeConn;

	protected function setUp(): void {
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->cache = $this->createMock(ICache::class);
		$this->cacheFactory = $this->createMock(ICacheFactory::class);
		$this->cacheFactory->method('createDistributed')->willReturn($this->cache);
		$this->ldap = $this->createMock(ILdapConnection::class);
		$this->fakeConn = new \stdClass();

		$this->downloader = new LdapCrlDownloader(
			$this->logger,
			$this->cacheFactory,
			$this->ldap,
		);
	}

	// --------------------------------------------------------------------- //
	// isLdapUrl                                                               //
	// --------------------------------------------------------------------- //

	#[DataProvider('dataProviderIsLdapUrl')]
	public function testIsLdapUrlRecognizesSchemes(string $url, bool $expected): void {
		$this->assertSame($expected, $this->downloader->isLdapUrl($url));
	}

	public static function dataProviderIsLdapUrl(): array {
		return [
			'ldap lowercase' => ['ldap://ldap.example.com/cn=CRL,o=Org', true],
			'ldaps lowercase' => ['ldaps://ldap.example.com/cn=CRL,o=Org', true],
			'LDAP uppercase' => ['LDAP://ldap.example.com/cn=CRL,o=Org', true],
			'http not ldap' => ['http://crl.example.com/crl.crl', false],
			'https not ldap' => ['https://crl.example.com/crl.crl', false],
			'empty string' => ['', false],
		];
	}

	// --------------------------------------------------------------------- //
	// download — cache                                                        //
	// --------------------------------------------------------------------- //

	public function testDownloadReturnsCachedValueWithoutCallingLdap(): void {
		$url = 'ldap://ldap.example.com/cn=CRL,o=Org';
		$cachedData = 'binary-crl-data';

		$this->cache->method('get')->willReturn($cachedData);
		$this->ldap->expects($this->never())->method('connect');

		$result = $this->downloader->download($url);

		$this->assertSame($cachedData, $result);
	}

	public function testDownloadCachesSuccessfulFetchResult(): void {
		$url = 'ldap://ldap.example.com/cn=CRL,o=Org?certificateRevocationList;binary?base';
		$crlData = 'binary-crl-content';

		$this->cache->method('get')->willReturn(null);
		$this->ldap->method('connect')->willReturn($this->fakeConn);
		$this->ldap->method('read')->willReturn(new \stdClass());
		$this->ldap->method('getEntries')->willReturn([
			'count' => 1,
			0 => ['certificaterevocationlist' => [0 => $crlData, 'count' => 1]],
		]);

		$this->cache
			->expects($this->once())
			->method('set')
			->with(sha1($url), $crlData, 86400);

		$result = $this->downloader->download($url);

		$this->assertSame($crlData, $result);
	}

	public function testDownloadDoesNotCacheNullResult(): void {
		$url = 'ldap://ldap.example.com/cn=CRL,o=Org';

		$this->cache->method('get')->willReturn(null);
		$this->ldap->method('connect')->willThrowException(new \RuntimeException('no server'));

		$this->cache->expects($this->never())->method('set');

		$result = $this->downloader->download($url);

		$this->assertNull($result);
	}

	// --------------------------------------------------------------------- //
	// download — URL parsing                                                  //
	// --------------------------------------------------------------------- //

	public function testDownloadReturnsNullForUnparsableUrl(): void {
		// Triple-slash LDAP URLs are considered malformed by parse_url
		$url = 'ldap:///cn=CRL,o=Org';

		$this->cache->method('get')->willReturn(null);
		$this->ldap->expects($this->never())->method('connect');

		$this->assertNull($this->downloader->download($url));
	}

	public function testDownloadReturnsNullAndLogsWarningWhenDnMissing(): void {
		$url = 'ldap://ldap.example.com/';

		$this->cache->method('get')->willReturn(null);
		$this->logger->expects($this->once())->method('warning')
			->with($this->stringContains('missing host or DN'));
		$this->ldap->expects($this->never())->method('connect');

		$this->assertNull($this->downloader->download($url));
	}

	// --------------------------------------------------------------------- //
	// download — connection failure                                           //
	// --------------------------------------------------------------------- //

	public function testDownloadReturnsNullWhenConnectThrows(): void {
		$url = 'ldap://ldap.example.com/cn=CRL,o=Org';

		$this->cache->method('get')->willReturn(null);
		$this->ldap->method('connect')
			->willThrowException(new \RuntimeException('ldap_connect failed'));

		$this->logger->expects($this->once())->method('warning')
			->with($this->stringContains('Failed to connect'));

		$this->assertNull($this->downloader->download($url));
	}

	// --------------------------------------------------------------------- //
	// download — LDAP query results                                           //
	// --------------------------------------------------------------------- //

	public function testDownloadReturnsNullWhenReadReturnsNoResult(): void {
		$url = 'ldap://ldap.example.com/cn=CRL,o=Org';

		$this->cache->method('get')->willReturn(null);
		$this->ldap->method('connect')->willReturn($this->fakeConn);
		$this->ldap->method('read')->willReturn(false);

		$this->logger->expects($this->once())->method('warning')
			->with($this->stringContains('LDAP search returned no result'));

		$this->assertNull($this->downloader->download($url));
	}

	public function testDownloadReturnsNullWhenEntriesCountIsZero(): void {
		$url = 'ldap://ldap.example.com/cn=CRL,o=Org';

		$this->cache->method('get')->willReturn(null);
		$this->ldap->method('connect')->willReturn($this->fakeConn);
		$this->ldap->method('read')->willReturn(new \stdClass());
		$this->ldap->method('getEntries')->willReturn(['count' => 0]);

		$this->assertNull($this->downloader->download($url));
	}

	public function testDownloadReturnsNullWhenAttributeNotFound(): void {
		$url = 'ldap://ldap.example.com/cn=CRL,o=Org';

		$this->cache->method('get')->willReturn(null);
		$this->ldap->method('connect')->willReturn($this->fakeConn);
		$this->ldap->method('read')->willReturn(new \stdClass());
		// Entry exists but the attribute is absent
		$this->ldap->method('getEntries')->willReturn([
			'count' => 1,
			0 => ['dn' => 'cn=CRL,o=Org'],
		]);

		$this->assertNull($this->downloader->download($url));
	}

	// --------------------------------------------------------------------- //
	// download — scope routing                                                //
	// --------------------------------------------------------------------- //

	#[DataProvider('dataProviderScope')]
	public function testDownloadUsesCorrectMethodForScope(
		string $scope,
		string $expectedMethod,
	): void {
		$urlBase = 'ldap://ldap.example.com/cn=CRL,o=Org?certificateRevocationList;binary?';
		$url = $urlBase . $scope;

		$this->cache->method('get')->willReturn(null);
		$this->ldap->method('connect')->willReturn($this->fakeConn);
		$this->ldap->method('getEntries')->willReturn(['count' => 0]);

		$this->ldap->expects($this->once())->method($expectedMethod)->willReturn(false);
		// The other two scope methods must never be called
		foreach (['read', 'listEntries', 'search'] as $m) {
			if ($m !== $expectedMethod) {
				$this->ldap->expects($this->never())->method($m);
			}
		}

		$this->downloader->download($url);
	}

	public static function dataProviderScope(): array {
		return [
			'base scope (default)' => ['base', 'read'],
			'one scope' => ['one', 'listEntries'],
			'onelevel scope' => ['onelevel', 'listEntries'],
			'sub scope' => ['sub', 'search'],
			'subtree scope' => ['subtree', 'search'],
			'empty = default' => ['', 'read'],
		];
	}

	// --------------------------------------------------------------------- //
	// download — resource cleanup (finally block)                            //
	// --------------------------------------------------------------------- //

	public function testDownloadCallsUnbindEvenWhenGetEntriesThrows(): void {
		$url = 'ldap://ldap.example.com/cn=CRL,o=Org';

		$this->cache->method('get')->willReturn(null);
		$this->ldap->method('connect')->willReturn($this->fakeConn);
		$this->ldap->method('read')->willReturn(new \stdClass());
		$this->ldap->method('getEntries')
			->willThrowException(new \Exception('unexpected ldap error'));

		// unbind MUST be called regardless of the exception
		$this->ldap->expects($this->once())
			->method('unbind')
			->with($this->fakeConn);

		$this->assertNull($this->downloader->download($url));
	}

	public function testDownloadDoesNotCallUnbindWhenConnectFails(): void {
		$url = 'ldap://ldap.example.com/cn=CRL,o=Org';

		$this->cache->method('get')->willReturn(null);
		$this->ldap->method('connect')
			->willThrowException(new \RuntimeException('no server'));

		// $ldapConn was never assigned, so unbind must not be called
		$this->ldap->expects($this->never())->method('unbind');

		$this->assertNull($this->downloader->download($url));
	}

	// --------------------------------------------------------------------- //
	// download — attribute with ;binary suffix is stripped                   //
	// --------------------------------------------------------------------- //

	public function testDownloadStripsAttributeOptionSuffix(): void {
		// The attribute in the URL is "certificateRevocationList;binary"
		// but the LDAP entries array uses the bare name.
		$url = 'ldap://ldap.example.com/cn=CRL,o=Org?certificateRevocationList;binary?base';
		$crlData = 'binary-crl-data';

		$this->cache->method('get')->willReturn(null);
		$this->ldap->method('connect')->willReturn($this->fakeConn);

		// Verify that read() is called with the stripped attribute name
		$this->ldap->expects($this->once())->method('read')
			->with(
				$this->fakeConn,
				$this->anything(),
				$this->anything(),
				['certificateRevocationList'],
			)
			->willReturn(new \stdClass());

		$this->ldap->method('getEntries')->willReturn([
			'count' => 1,
			0 => ['certificaterevocationlist' => [0 => $crlData, 'count' => 1]],
		]);

		$this->assertSame($crlData, $this->downloader->download($url));
	}
}
