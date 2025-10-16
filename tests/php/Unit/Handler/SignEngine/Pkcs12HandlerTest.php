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

	public function testSavePfxWhenNoPermission(): void {
		$node = $this->createMock(\OCP\Files\Folder::class);
		$node->method('newFile')->willThrowException(new NotPermittedException());
		$this->folderService->method('getFolder')->willReturn($node);

		$this->expectExceptionMessage('You do not have permission');
		$this->getHandler()->savePfx('userId', 'content');
	}

	public function testSavePfxReturnsContent(): void {
		$actual = $this->getHandler()->savePfx('userId', 'content');
		$this->assertEquals('content', $actual);
	}

	public function testGetPfxOfCurrentSignerWithInvalidPfx(): void {
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

	public function testGetLastSignedDateWithoutFile(): void {
		$handler = $this->getHandler();

		$this->expectException(\Error::class);
		$handler->getLastSignedDate();
	}

	public function testGetCertificateChainWithUnsignedFile(): void {
		$handler = $this->getHandler();

		$resourceContent = 'Not a signed PDF - missing ByteRange';
		$resource = fopen('php://memory', 'r+');
		fwrite($resource, $resourceContent);
		rewind($resource);

		$this->expectException(\OCA\Libresign\Exception\LibresignException::class);
		$this->expectExceptionMessage('Unsigned file.');

		$handler->getCertificateChain($resource);
		fclose($resource);
	}

	public function testIsHandlerOkReturnsBoolean(): void {
		$engineMock = $this->createMock(\OCA\Libresign\Handler\CertificateEngine\AEngineHandler::class);
		$engineMock->method('isSetupOk')->willReturn(true);

		$this->certificateEngineFactory->method('getEngine')->willReturn($engineMock);

		$handler = $this->getHandler();
		$result = $handler->isHandlerOk();

		$this->assertIsBool($result);
		$this->assertTrue($result);
	}

	public function testSavePfxCreatesFileSuccessfully(): void {
		$folder = $this->createMock(\OCP\Files\Folder::class);
		$file = $this->createMock(\OCP\Files\File::class);

		$folder->expects($this->once())
			->method('newFile')
			->with('signature.pfx', 'test pfx content')
			->willReturn($file);

		$this->folderService->method('getFolder')->willReturn($folder);

		$handler = $this->getHandler();
		$result = $handler->savePfx('testUser', 'test pfx content');

		$this->assertEquals('test pfx content', $result);
	}

	public function testGetPfxWithValidUser(): void {
		$folder = $this->createMock(\OCP\Files\Folder::class);
		$file = $this->createMock(\OCP\Files\File::class);

		$file->method('getContent')->willReturn('test cert');
		$folder->method('get')->with('signature.pfx')->willReturn($file);
		$this->folderService->method('getFolder')->willReturn($folder);

		$handler = $this->getHandler();
		$handler->setCertificate('test cert');
		$result = $handler->getPfxOfCurrentSigner('testUser');

		$this->assertEquals('test cert', $result);
	}

	public function testSignWithoutRequiredInputFails(): void {
		$handler = $this->getHandler();

		$this->expectException(\Error::class);
		$handler->sign();
	}

	public function testCertificateChainProcessingBehavior(): void {
		$handler = $this->getHandler();

		$emptyContent = 'some content without signatures';
		$resource = fopen('php://memory', 'r+');
		fwrite($resource, $emptyContent);
		rewind($resource);

		$this->expectException(\OCA\Libresign\Exception\LibresignException::class);
		$handler->getCertificateChain($resource);

		fclose($resource);
	}

	public function testOrderCertificatesIntegration(): void {
		$handler = $this->getHandler();

		$mockCerts = [
			[
				'name' => '/CN=Root CA',
				'subject' => ['CN' => 'Root CA'],
				'issuer' => ['CN' => 'Root CA'],
			],
			[
				'name' => '/CN=End Entity',
				'subject' => ['CN' => 'End Entity'],
				'issuer' => ['CN' => 'Root CA'],
			],
		];

		$ordered = $handler->orderCertificates($mockCerts);

		$this->assertIsArray($ordered);
		$this->assertCount(2, $ordered);
		$this->assertEquals('End Entity', $ordered[0]['subject']['CN']);
		$this->assertEquals('Root CA', $ordered[1]['subject']['CN']);
	}

	public function testGetCertificateChainWithInvalidInput(): void {
		$handler = $this->getHandler();
		$invalidResource = fopen('php://memory', 'r');

		$this->expectException(\OCA\Libresign\Exception\LibresignException::class);
		$handler->getCertificateChain($invalidResource);
		fclose($invalidResource);
	}

	public function testRealWorldUsagePattern(): void {
		$handler = $this->getHandler();

		$this->assertInstanceOf(Pkcs12Handler::class, $handler);

		$this->expectException(\OCA\Libresign\Exception\LibresignException::class);
		$this->expectExceptionMessage('Password to sign not defined');
		$handler->getPfxOfCurrentSigner('test_user');
	}

	public function testBasicPublicInterfaceContract(): void {
		$handler = $this->getHandler();

		$this->assertTrue(method_exists($handler, 'savePfx'));
		$this->assertTrue(method_exists($handler, 'getPfxOfCurrentSigner'));
		$this->assertTrue(method_exists($handler, 'sign'));
		$this->assertTrue(method_exists($handler, 'getCertificateChain'));
		$this->assertTrue(method_exists($handler, 'getLastSignedDate'));
		$this->assertTrue(method_exists($handler, 'isHandlerOk'));
		$this->assertTrue(method_exists($handler, 'orderCertificates'));
	}

	public function testCertificateChainProcessingPublicBehavior(): void {
		$handler = $this->getHandler();

		$certs = [
			[
				'name' => '/CN=Intermediate',
				'subject' => ['CN' => 'Intermediate'],
				'issuer' => ['CN' => 'Root'],
			],
			[
				'name' => '/CN=Root',
				'subject' => ['CN' => 'Root'],
				'issuer' => ['CN' => 'Root'],
			],
		];

		$ordered = $handler->orderCertificates($certs);
		$this->assertCount(2, $ordered);
		$this->assertEquals('Intermediate', $ordered[0]['subject']['CN']);

		$singleCert = [
			[
				'name' => '/CN=Single',
				'subject' => ['CN' => 'Single'],
				'issuer' => ['CN' => 'Single'],
			]
		];

		$result = $handler->orderCertificates($singleCert);
		$this->assertCount(1, $result);
		$this->assertEquals('Single', $result[0]['subject']['CN']);
	}

	public function testErrorHandlingThroughPublicInterface(): void {
		$handler = $this->getHandler();

		$this->expectException(\OCA\Libresign\Exception\LibresignException::class);
		$this->expectExceptionCode(400);
		$handler->getPfxOfCurrentSigner('nonexistent_user');
	}

	public function testIntegrationWithFileSystem(): void {
		$folder = $this->createMock(\OCP\Files\Folder::class);
		$folder->method('get')->willThrowException(new \OCP\Files\NotFoundException());
		$this->folderService->method('getFolder')->willReturn($folder);

		$handler = $this->getHandler();

		$this->expectException(\OCA\Libresign\Exception\LibresignException::class);
		$handler->getPfxOfCurrentSigner('test_user');
	}
}
