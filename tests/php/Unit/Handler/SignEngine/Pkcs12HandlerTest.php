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
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\ITempManager;
use OCP\L10N\IFactory as IL10NFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

final class Pkcs12HandlerTest extends \OCA\Libresign\Tests\Unit\TestCase {
	protected Pkcs12Handler $pkcs12Handler;
	protected FolderService&MockObject $folderService;
	private IAppConfig $appConfig;
	private IL10N $l10n;
	private FooterHandler&MockObject $footerHandler;
	private ITempManager $tempManager;
	private LoggerInterface&MockObject $logger;
	private CertificateEngineFactory&MockObject $certificateEngineFactory;

	public function setUp(): void {
		$this->folderService = $this->createMock(FolderService::class);
		$this->appConfig = $this->getMockAppConfig();
		$this->certificateEngineFactory = $this->createMock(CertificateEngineFactory::class);
		$this->l10n = \OCP\Server::get(IL10NFactory::class)->get(Application::APP_ID);
		$this->footerHandler = $this->createMock(FooterHandler::class);
		$this->tempManager = \OCP\Server::get(ITempManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	private function getHandler(array $methods = []): Pkcs12Handler|MockObject {
		if ($methods) {
			return $this->getMockBuilder(Pkcs12Handler::class)
				->setConstructorArgs([
					$this->folderService,
					$this->appConfig,
					$this->certificateEngineFactory,
					$this->l10n,
					$this->footerHandler,
					$this->tempManager,
					$this->logger,
				])
				->onlyMethods($methods)
				->getMock();
		}
		return new Pkcs12Handler(
			$this->folderService,
			$this->appConfig,
			$this->certificateEngineFactory,
			$this->l10n,
			$this->footerHandler,
			$this->tempManager,
			$this->logger,
		);
	}

	public function testSavePfxWhenHaventPermission():void {
		$node = $this->createMock(\OCP\Files\Folder::class);
		$node->method('newFile')->willThrowException(new NotPermittedException());
		$this->folderService->method('getFolder')->willReturn($node);

		$this->expectExceptionMessage('You do not have permission');
		$this->getHandler()->savePfx('userId', 'content');
	}

	public function testSavePfxWhenPfxFileExsitsAndIsAFile():void {
		$actual = $this->getHandler()->savePfx('userId', 'content');
		$this->assertEquals('content', $actual);
	}

	public function testGetPfxOfCurrentSignerWithInvalidPfx():void {
		$node = $this->createMock(\OCP\Files\Folder::class);
		$node->method('get')->willThrowException(new NotFoundException());
		$this->folderService->method('getFolder')->willReturn($node);
		$this->expectExceptionMessage('Password to sign not defined. Create a password to sign');
		$this->expectExceptionCode(400);
		$this->getHandler()->getPfxOfCurrentSigner('userId');
	}

	public function testGetPfxOfCurrentSignerOk():void {
		$folder = $this->createMock(\OCP\Files\Folder::class);
		$file = $this->createMock(\OCP\Files\File::class);
		$file->method('getContent')
			->willReturn('valid pfx content');
		$folder->method('get')->willReturn($file);
		$this->folderService->method('getFolder')->willReturn($folder);
		$actual = $this->getHandler()->getPfxOfCurrentSigner('userId');
		$this->assertEquals('valid pfx content', $actual);
	}

	public function testGetLastSignedDateWithProblemAtInputFile(): void {
		$this->expectException(\RuntimeException::class);

		$handler = $this->getHandler();

		$fileMock = $this->createMock(\OCP\Files\File::class);
		$fileMock->method('fopen')->willReturn(false);
		$handler->setInputFile($fileMock);

		$handler->getLastSignedDate();
	}

	public function testGetLastSignedDateWithEmptyCertificateChain(): void {
		$handler = $this->getHandler(['getCertificateChain']);
		$handler->method('getCertificateChain')->wilLReturn([]);
		$this->expectException(\UnexpectedValueException::class);
		$this->expectExceptionMessageMatches('/empty/');

		$fileMock = $this->createMock(\OCP\Files\File::class);
		$handler->setInputFile($fileMock);

		$handler->getLastSignedDate();
	}

	#[DataProvider('providerGetLastSignedDateWithInvalidSigningTime')]
	public function testGetLastSignedDateWithInvalidSigningTime(array $chain): void {
		$handler = $this->getHandler(['getCertificateChain', 'getFileStream']);
		$handler->method('getCertificateChain')->wilLReturn($chain);
		$this->expectException(\UnexpectedValueException::class);
		$this->expectExceptionMessageMatches('/signingTime/');

		$handler->getLastSignedDate();
	}

	public static function providerGetLastSignedDateWithInvalidSigningTime(): array {
		return [
			// is not an array
			'invalid: string' => [['not-an-array']],
			'invalid: int' => [[123]],
			'invalid: null' => [[null]],
			'invalid: bool' => [[true]],
			'invalid: object' => [[new \stdClass()]],

			// is an array but missing 'signingTime' key
			'missing signingTime' => [[[]]],
			'wrong key' => [[['otherKey' => 'value']]],

			// 'signingTime' exists but is not a DateTime instance
			'signingTime null' => [[['signingTime' => null]]],
			'signingTime string' => [[['signingTime' => '2024-01-01']]],
			'signingTime int' => [[['signingTime' => 1234567890]]],
			'signingTime object' => [[['signingTime' => new \stdClass()]]],

			// Valid element followed by an invalid one at the end
			'multiple, last is null' => [
				[
					['signingTime' => new \DateTime()],
					['signingTime' => null],
				],
			],
			'multiple, last is string' => [
				[
					['signingTime' => new \DateTime()],
					['notEvenAnArray'],
				],
			],
			'future date' => [
				[
					['signingTime' => (new DateTime())->modify('+30 years')],
				],
			],
		];
	}


	#[DataProvider('providerGetLastSignedDateWillReturnTheBiggestDate')]
	public function testGetLastSignedDateWillReturnTheBiggestDate(array $chain, \DateTime $signedDate): void {
		$handler = $this->getHandler(['getCertificateChain', 'getFileStream']);
		$handler->method('getCertificateChain')->wilLReturn($chain);

		$actual = $handler->getLastSignedDate();
		$this->assertEquals($signedDate, $actual);
	}

	public static function providerGetLastSignedDateWillReturnTheBiggestDate(): array {
		$date = new DateTime();
		return [
			[
				[
					['signingTime' => (clone $date)->modify('-3 day')],
					['signingTime' => (clone $date)->modify('-2 day')],
					['signingTime' => (clone $date)->modify('-1 day')],
				],
				$date->modify('-1 day'),
			],
		];
	}
}
