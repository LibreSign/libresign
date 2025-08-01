<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\FooterHandler;
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Service\FolderService;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\ITempManager;
use OCP\L10N\IFactory as IL10NFactory;
use PHPUnit\Framework\MockObject\MockObject;

final class Pkcs12HandlerTest extends \OCA\Libresign\Tests\Unit\TestCase {
	protected Pkcs12Handler $pkcs12Handler;
	protected FolderService&MockObject $folderService;
	private IAppConfig $appConfig;
	private IL10N $l10n;
	private FooterHandler&MockObject $footerHandler;
	private ITempManager $tempManager;
	private CertificateEngineFactory&MockObject $certificateEngineFactory;

	public function setUp(): void {
		$this->folderService = $this->createMock(FolderService::class);
		$this->appConfig = $this->getMockAppConfig();
		$this->certificateEngineFactory = $this->createMock(CertificateEngineFactory::class);
		$this->l10n = \OCP\Server::get(IL10NFactory::class)->get(Application::APP_ID);
		$this->footerHandler = $this->createMock(FooterHandler::class);
		$this->tempManager = \OCP\Server::get(ITempManager::class);
	}

	private function getHandler(): Pkcs12Handler {
		return new Pkcs12Handler(
			$this->folderService,
			$this->appConfig,
			$this->certificateEngineFactory,
			$this->l10n,
			$this->footerHandler,
			$this->tempManager,
		);
	}

	public function testSavePfxWhenPfxFileIsAFolder():void {
		$node = $this->createMock(\OCP\Files\Folder::class);
		$node->method('nodeExists')->willReturn(true);
		$node->method('get')->willReturn($node);
		$this->folderService->method('getFolder')->willReturn($node);

		$this->expectExceptionMessage('path signature.pfx already exists and is not a file!');
		$this->getHandler()->savePfx('userId', 'content');
	}

	public function testSavePfxWhenPfxFileExsitsAndIsAFile():void {
		$node = $this->createMock(\OCP\Files\Folder::class);
		$node->method('nodeExists')->willReturn(true);
		$file = $this->createMock(\OCP\Files\File::class);
		$node->method('get')->willReturn($file);
		$this->folderService->method('getFolder')->willReturn($node);

		$actual = $this->getHandler()->savePfx('userId', 'content');
		$this->assertEquals('content', $actual);
	}

	public function testGetPfxOfCurrentSignerWithInvalidPfx():void {
		$node = $this->createMock(\OCP\Files\Folder::class);
		$node->method('nodeExists')->willReturn(false);
		$this->folderService->method('getFolder')->willReturn($node);
		$this->expectExceptionMessage('Password to sign not defined. Create a password to sign');
		$this->expectExceptionCode(400);
		$this->getHandler()->getPfxOfCurrentSigner('userId');
	}

	public function testGetPfxOfCurrentSignerOk():void {
		$folder = $this->createMock(\OCP\Files\Folder::class);
		$folder->method('nodeExists')->willReturn(true);
		$file = $this->createMock(\OCP\Files\File::class);
		$file->method('getContent')
			->willReturn('valid pfx content');
		$folder->method('get')->willReturn($file);
		$this->folderService->method('getFolder')->willReturn($folder);
		$actual = $this->getHandler()->getPfxOfCurrentSigner('userId');
		$this->assertEquals('valid pfx content', $actual);
	}
}
