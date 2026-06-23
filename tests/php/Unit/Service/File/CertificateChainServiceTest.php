<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\File;

use OCA\Libresign\Db\File;
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Service\File\CertificateChainService;
use OCA\Libresign\Service\File\FileResponseOptions;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class CertificateChainServiceTest extends TestCase {
	public function testGetCertificateChainReturnsAndSetsFlag(): void {
		$content = 'abc content for hash';

		$stream = fopen('php://memory', 'r+');
		fwrite($stream, $content);
		rewind($stream);

		$fileNode = new class($stream) {
			public function __construct(
				private $s,
			) {
			}
			public function fopen($mode) {
				return $this->s;
			}
		};

		$libreSignFile = new File();
		$libreSignFile->setSignedNodeId(1);
		$libreSignFile->setSignedHash(hash('sha256', $content));
		$libreSignFile->setUserId('requester');

		$pkcs12 = new PolicyAwarePkcs12HandlerDouble();

		$logger = $this->createMock(LoggerInterface::class);

		$service = new CertificateChainService($pkcs12, $logger);

		$options = new FileResponseOptions();
		$options->validateFile(true);

		$result = $service->getCertificateChain($fileNode, $libreSignFile, $options);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('chain', $result);
		$this->assertTrue($pkcs12->libreSignFlagSet);
		$this->assertSame('requester', $pkcs12->policyUserIdForValidation);
	}

	public function testGetCertificateChainHandlesInvalidResourceGracefully(): void {
		$fileNode = new class() {
			public function fopen($mode) {
				return false;
			}
		};

		$libreSignFile = new File();
		$libreSignFile->setSignedNodeId(1);

		$pkcs12 = $this->createMock(Pkcs12Handler::class);
		$pkcs12->expects($this->never())->method('getCertificateChain');

		$logger = $this->createMock(LoggerInterface::class);
		$logger
			->expects($this->once())
			->method('warning')
			->with($this->stringContains('unable to open signed file stream'));

		$service = new CertificateChainService($pkcs12, $logger);

		$options = new FileResponseOptions();
		$options->validateFile(true);

		$result = $service->getCertificateChain($fileNode, $libreSignFile, $options);

		$this->assertSame([], $result);
	}
}
