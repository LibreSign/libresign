<?php

use OCA\Libresign\Handler\Pkcs7Handler;
use PHPUnit\Framework\MockObject\MockObject;

final class Pkcs7HandlerTest extends \OCA\Libresign\Tests\Unit\TestCase {
	/**
	 * @param array $methods
	 * @return MockObject|Pkcs7Handler
	 */
	protected function getInstance(array $methods = []): Pkcs7Handler {
		if (empty($methods)) {
			return new Pkcs7Handler();
		}
		return $this->getMockBuilder(Pkcs7Handler::class)
			->setConstructorArgs([])
			->onlyMethods($methods)
			->getMock();
	}

	public function testSignWithSuccess() {
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

		$certificate = $this->createMock(\OCP\Files\File::class);
		$certKeys = json_decode(file_get_contents(__DIR__ . '/../../fixtures/cfssl/newcert-with-success.json'), true);
		$certKeys = $certKeys['result'];
		openssl_pkcs12_export($certKeys['certificate'], $certContent, $certKeys['private_key'], 'password');
		$certificate
			->method('getContent')
			->willReturn($certContent);
		$handler->setCertificate($certificate);

		$handler->setPassword('password');

		$actual = $handler->sign();
		$signedFile = $actual->getInternalPath();
		$this->assertStringContainsString($content, file_get_contents($signedFile));
	}
}
