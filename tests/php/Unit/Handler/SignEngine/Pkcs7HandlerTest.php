<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Libresign\Handler\SignEngine\Pkcs7Handler;
use PHPUnit\Framework\MockObject\MockObject;

final class Pkcs7HandlerTest extends \OCA\Libresign\Tests\Unit\TestCase {
	/**
	 * @return MockObject|Pkcs7Handler
	 */
	protected function getInstance(array $methods = []): Pkcs7Handler|MockObject {
		if (empty($methods)) {
			return new Pkcs7Handler();
		}
		return $this->getMockBuilder(Pkcs7Handler::class)
			->setConstructorArgs([])
			->onlyMethods($methods)
			->getMock();
	}

	public function testSignWithSuccess():void {
		$handler = $this->getInstance(['getP7sFile']);

		$p7sFile = $this->createMock(\OCP\Files\File::class);
		$p7sTempNam = tempnam(sys_get_temp_dir(), 'p7s');
		$p7sFile
			->method('getInternalPath')
			->willReturn($p7sTempNam);
		$handler->expects($this->once())
			->method('getP7sFile')
			->willReturn($p7sFile);

		$fileToSign = $this->createMock(\OCP\Files\File::class);
		$fileToSIgnTempNam = tempnam(sys_get_temp_dir(), 'txt');
		$content = 'A simple test';
		file_put_contents($fileToSIgnTempNam, $content);
		$fileToSign
			->method('getInternalPath')
			->willReturn($fileToSIgnTempNam);
		$handler->setInputFile($fileToSign);

		$certKeys = json_decode(file_get_contents(__DIR__ . '/../../../fixtures/cfssl/newcert-with-success.json'), true);
		$certKeys = $certKeys['result'];
		openssl_pkcs12_export($certKeys['certificate'], $certContent, $certKeys['private_key'], 'password');
		$handler->setCertificate($certContent);

		$handler->setPassword('password');

		$actual = $handler->sign();
		$signedFile = $actual->getInternalPath();
		$this->assertStringContainsString($content, file_get_contents($signedFile));
		$this->assertGreaterThan($content, file_get_contents($signedFile));
	}
}
