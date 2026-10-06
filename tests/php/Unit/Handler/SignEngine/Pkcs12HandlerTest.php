<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Handler\SignEngine;

use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\DocMdpHandler;
use OCA\Libresign\Handler\FooterHandler;
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Service\CaIdentifierService;
use OCA\Libresign\Service\Crl\CrlService;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\Signature\PdfSignatureValidationService;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\GenericFileException;
use OCP\IAppConfig;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

final class Pkcs12HandlerTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private FolderService&MockObject $folderService;

	public function setUp(): void {
		parent::setUp();
		$this->folderService = $this->createMock(FolderService::class);
	}

	private function getHandler(): Pkcs12Handler {
		return new Pkcs12Handler(
			$this->folderService,
			$this->createMock(IAppConfig::class),
			$this->createMock(CertificateEngineFactory::class),
			$this->createMock(IL10N::class),
			$this->createMock(FooterHandler::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(CaIdentifierService::class),
			$this->createMock(DocMdpHandler::class),
			$this->createMock(CrlService::class),
			$this->createMock(PdfSignatureValidationService::class),
		);
	}

	public function testGetPfxOfCurrentSignerRestoresFolderContext(): void {
		$folder = $this->createMock(Folder::class);
		$file = $this->createMock(File::class);
		$file->method('getContent')->willReturn('alice-pfx');
		$folder->method('get')->with('signature.pfx')->willReturn($file);

		$this->folderService->method('getUserId')->willReturn('original-user');
		$this->folderService->method('getFolder')->willReturn($folder);
		$this->folderService
			->expects($this->exactly(2))
			->method('setUserId')
			->willReturnCallback(function (?string $uid): void {
				static $call = 0;
				$expected = ['alice', 'original-user'];
				$this->assertSame($expected[$call++], $uid);
			});

		$this->assertSame('alice-pfx', $this->getHandler()->getPfxOfCurrentSigner('alice'));
	}

	public function testGetPfxOfCurrentSignerPropagatesUnexpectedStorageFailure(): void {
		$folder = $this->createMock(Folder::class);
		$file = $this->createMock(File::class);
		$file->method('getContent')->willThrowException(new GenericFileException());
		$folder->method('get')->with('signature.pfx')->willReturn($file);

		$this->folderService->method('getUserId')->willReturn('original-user');
		$this->folderService->method('getFolder')->willReturn($folder);
		$this->folderService
			->expects($this->exactly(2))
			->method('setUserId');

		$this->expectException(GenericFileException::class);
		$this->getHandler()->getPfxOfCurrentSigner('alice');
	}

	public function testGetPfxOfCurrentSignerDoesNotReuseCertificateFromDifferentUser(): void {
		$aliceFolder = $this->createMock(Folder::class);
		$aliceFile = $this->createMock(File::class);
		$aliceFile->method('getContent')->willReturn('alice-pfx');
		$aliceFolder->method('get')->with('signature.pfx')->willReturn($aliceFile);

		$bobFolder = $this->createMock(Folder::class);
		$bobFile = $this->createMock(File::class);
		$bobFile->method('getContent')->willReturn('bob-pfx');
		$bobFolder->method('get')->with('signature.pfx')->willReturn($bobFile);

		$this->folderService
			->method('getFolder')
			->willReturnOnConsecutiveCalls($aliceFolder, $bobFolder);

		$handler = $this->getHandler();

		$this->assertSame('alice-pfx', $handler->getPfxOfCurrentSigner('alice'));
		$this->assertSame('bob-pfx', $handler->getPfxOfCurrentSigner('bob'));
	}
}
