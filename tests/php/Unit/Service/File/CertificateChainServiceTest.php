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
use OCA\Libresign\Tests\Unit\TestCase;
use Psr\Log\LoggerInterface;

final class CertificateChainServiceTest extends TestCase {
	public function testGetCertificateChainReturnsAndSetsFlag(): void {
		$content = 'abc content for hash';

		$stream = fopen('php://memory', 'r+');
		fwrite($stream, $content);
		rewind($stream);

		$fileNode = new class($stream) {
			private $s;
			public function __construct($s) {
				$this->s = $s;
			}
			public function fopen($mode) {
				return $this->s;
			}
		};

		$libreSignFile = new File();
		$libreSignFile->setSignedNodeId(1);
		$libreSignFile->setSignedHash(hash('sha256', $content));

		$pkcs12 = $this->createMock(Pkcs12Handler::class);
		$pkcs12->expects($this->once())->method('setIsLibreSignFile');
		$pkcs12->method('getCertificateChain')->willReturn(['chain' => []]);

		$logger = $this->createMock(LoggerInterface::class);

		$service = new CertificateChainService($pkcs12, $logger);

		$options = new FileResponseOptions();
		$options->validateFile(true);

		$result = $service->getCertificateChain($fileNode, $libreSignFile, $options);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('chain', $result);
	}
}
