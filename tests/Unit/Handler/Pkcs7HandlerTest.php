<?php

use OCA\Libresign\Handler\Pkcs7Handler;
use org\bovigo\vfs\vfsStream;

final class Pkcs7HandlerTest extends \OCA\Libresign\Tests\Unit\TestCase {
	public function testSignWithSuccess() {
		$this->markTestSkipped();
		vfsStream::setup('home');
		$fileToSign = $this->createMock(\OCP\Files\File::class);
		$fileToSign
			->method('getName')
			->willReturn('filename.txt');
		$fileToSign
			->method('getInternalPath')
			->willReturn(vfsStream::url('home/filename.txt'));
		$p7sFile = $this->createMock(\OCP\Files\File::class);
		$p7sFile
			->method('getInternalPath')
			->willReturn(vfsStream::url('home/filename.txt.p7s'));
		$parentFolder = $this->createMock(\OCP\Files\Folder::class);
		$parentFolder
			->method('newFile')
			->willReturn($p7sFile);
		$fileToSign
			->method('getParent')
			->willReturn($parentFolder);

		$certificate = $this->createMock(\OCP\Files\File::class);
		$certKeys = json_decode(file_get_contents(__DIR__ . '/../../fixtures/cfssl/newcert-with-success.json'), true);
		$certKeys = $certKeys['result'];
		openssl_pkcs12_export($certKeys['certificate'], $certContent, $certKeys['private_key'], 'password');
		$certificate
			->method('getContent')
			->willReturn($certContent);
		$certificate
			->method('getInternalPath')
			->willReturn(vfsStream::url('home/certificate.pfx'));

		$handler = new Pkcs7Handler();
		$actual = $handler->sign(
			$fileToSign,
			$certificate,
			'password'
		);
		$this->assertEquals('p7s', $actual->getExtension());
		$this->assertEquals('filename.txt.p7s', $actual->getName());
	}
}
