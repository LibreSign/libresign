<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Libresign\Handler\SignEngine\Pkcs7Handler;
use OCA\Libresign\Service\FolderService;
use OCP\IL10N;
use OCP\L10N\IFactory as IL10NFactory;
use PHPUnit\Framework\MockObject\MockObject;

final class Pkcs7HandlerTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IL10N $l10n;
	private FolderService&MockObject $folderService;
	public function setUp(): void {
		parent::setUp();
		$this->l10n = \OCP\Server::get(IL10NFactory::class)->get(\OCA\Libresign\AppInfo\Application::APP_ID);
		$this->folderService = $this->createMock(\OCA\Libresign\Service\FolderService::class);
	}

	protected function getInstance(array $methods = []): Pkcs7Handler|MockObject {
		if (empty($methods)) {
			return new Pkcs7Handler(
				$this->l10n,
				$this->folderService,
			);
		}
		return $this->getMockBuilder(Pkcs7Handler::class)
			->setConstructorArgs([
				$this->l10n,
				$this->folderService,
			])
			->onlyMethods($methods)
			->getMock();
	}

	public function testSignWithSuccess():void {
		$p7sRealFile = tempnam(sys_get_temp_dir(), 'p7s');
		$p7sFile = $this->createMock(\OCP\Files\File::class);
		$p7sFile->method('getInternalPath')->willReturn($p7sRealFile);

		$fileToSignRealFile = tempnam(sys_get_temp_dir(), 'txt');
		$content = 'A simple test';
		file_put_contents($fileToSignRealFile, $content);
		$fileToSign = $this->createMock(\OCP\Files\File::class);
		$fileToSign->method('getInternalPath')->willReturn($fileToSignRealFile);

		$handler = $this->getInstance(['getP7sFile', 'getInputFile']);
		$handler->method('getP7sFile')->willReturn($p7sFile);
		$handler->method('getInputFile')->willReturn($fileToSign);

		$certKeys = json_decode(file_get_contents(__DIR__ . '/../../../fixtures/cfssl/newcert-with-success.json'), true);
		$certKeys = $certKeys['result'];
		openssl_pkcs12_export($certKeys['certificate'], $certContent, $certKeys['private_key'], 'password');
		$handler->setCertificate($certContent);

		$handler->setPassword('password');

		$handler->sign();

		$this->assertStringContainsString($content, file_get_contents($p7sRealFile));
		$this->assertGreaterThan($content, file_get_contents($p7sRealFile));
	}
}
