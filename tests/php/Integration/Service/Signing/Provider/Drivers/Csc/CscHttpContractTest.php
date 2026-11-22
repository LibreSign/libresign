<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service\Signing\Provider\Drivers\Csc;

use donatj\MockWebServer\MockWebServer;
use donatj\MockWebServer\Response;
use OCA\Libresign\Service\Signing\Provider\Drivers\Csc\CscIntegration;
use PHPUnit\Framework\TestCase;
use SignerPHP\PdfSigner\Domain\Exception\SignerException;
use SignerPHP\PdfSigner\Infrastructure\Native\Service\CurlHttpClient;

/** A disposable, localhost-only mock HTTP server without Nextcloud persistence. */
final class CscHttpContractTest extends TestCase {
	private static MockWebServer $server;

	public static function setUpBeforeClass(): void {
		self::$server = new MockWebServer();
		self::$server->start();
	}

	public static function tearDownAfterClass(): void {
		self::$server->stop();
	}

	public function testRealHttpDiscoveryUsesCscPostPaths(): void {
		self::$server->setResponseOfPath('/csc/v1/info', new Response('{"specs":"1.0.4.0"}'));
		self::$server->setResponseOfPath('/csc/v1/credentials/list', new Response('{"credentialIDs":[]}'));
		$adapter = CscIntegration::forSyntheticLab(new CurlHttpClient(), self::$server->getServerRoot());
		$this->assertSame([], $adapter->certificates());
		$last = self::$server->getLastRequest();
		$this->assertNotNull($last);
		$this->assertSame('POST', $last->getRequestMethod());
		$this->assertSame('/csc/v1/credentials/list', $last->getRequestUri());
	}

	public function testUnsupportedCscVersionFails(): void {
		self::$server->setResponseOfPath('/csc/v1/info', new Response('{"specs":"2.2.0.0"}'));
		$this->expectException(SignerException::class);
		CscIntegration::forSyntheticLab(new CurlHttpClient(), self::$server->getServerRoot())->certificates();
	}

	public function testMalformedJsonFails(): void {
		self::$server->setResponseOfPath('/csc/v1/info', new Response('not-json'));
		$this->expectException(SignerException::class);
		CscIntegration::forSyntheticLab(new CurlHttpClient(), self::$server->getServerRoot())->certificates();
	}

	public function testHttp302IsNotFollowed(): void {
		self::$server->setResponseOfPath('/csc/v1/info', new Response('', ['Location' => 'http://127.0.0.1:1/private'], 302));
		$this->expectException(SignerException::class);
		$this->expectExceptionMessage('HTTP 302');
		CscIntegration::forSyntheticLab(new CurlHttpClient(), self::$server->getServerRoot())->certificates();
	}
}
