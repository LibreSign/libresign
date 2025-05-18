<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\FooterHandler;
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\IdentifyMethod\IdentifyService;
use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\Password;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\ITempManager;
use OCP\IUserSession;
use OCP\L10N\IFactory as IL10NFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class PasswordTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IdentifyService&MockObject $identifyService;
	private Pkcs12Handler&MockObject $pkcs12Handler;
	private IUserSession&MockObject $userSession;
	private IAppConfig $appConfig;
	private FolderService&MockObject $folderService;
	private CertificateEngineFactory&MockObject $certificateEngineFactory;
	private IL10N $l10n;
	private FooterHandler&MockObject $footerHandler;
	private ITempManager $tempManager;

	public function setUp(): void {
		$this->identifyService = $this->createMock(IdentifyService::class);
		$this->appConfig = $this->getMockAppConfig();
		$this->folderService = $this->createMock(FolderService::class);
		$this->certificateEngineFactory = $this->createMock(CertificateEngineFactory::class);
		$this->l10n = \OCP\Server::get(IL10NFactory::class)->get(Application::APP_ID);
		$this->footerHandler = $this->createMock(FooterHandler::class);
		$this->tempManager = \OCP\Server::get(ITempManager::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->pkcs12Handler = $this->getPkcs12Instance();
	}

	private function getClass(): Password {
		return new Password(
			$this->identifyService,
			$this->pkcs12Handler,
			$this->userSession,
		);
	}

	/**
	 * @return Pkcs12Handler&MockObject
	 */
	private function getPkcs12Instance(array $methods = []) {
		return $this->getMockBuilder(Pkcs12Handler::class)
			->setConstructorArgs([
				$this->folderService,
				$this->appConfig,
				$this->certificateEngineFactory,
				$this->l10n,
				$this->footerHandler,
				$this->tempManager,
			])
			->onlyMethods($methods)
			->getMock();
	}

	#[DataProvider('providerValidateToIdentify')]
	public function testValidateToIdentify(string $pfx, bool $shouldThrow): void {
		$this->pkcs12Handler = $this->getPkcs12Instance(['getPfxOfCurrentSigner']);
		$this->pkcs12Handler->method('getPfxOfCurrentSigner')->willReturn($pfx);

		$password = $this->getClass();
		$password->setCodeSentByUser('senha');

		if ($shouldThrow) {
			$this->expectException(LibresignException::class);
			$password->validateToIdentify();
		} else {
			$password->validateToIdentify();
			$this->expectNotToPerformAssertions();
		}
	}

	public static function providerValidateToIdentify(): array {
		return [
			'valid pfx' => ['mock-pfx', false],
			'empty pfx' => ['', true],
		];
	}

	#[DataProvider('providerValidateToSignWithError')]
	public function testValidateToSignWithError(bool $throwsException, string $pfx): void {
		$this->pkcs12Handler = $this->getPkcs12Instance(['getPfxOfCurrentSigner']);
		$this->pkcs12Handler->method('getPfxOfCurrentSigner')->willReturn($pfx);
		if ($throwsException) {
			$this->expectException(LibresignException::class);
		} else {
			$this->expectNotToPerformAssertions();
		}

		$password = $this->getClass();
		$password->setCodeSentByUser('senha');
		$password->validateToSign();
	}

	public static function providerValidateToSignWithError(): array {
		return [
			'Invalid certificate' => [true, ''],
			'throws InvalidPasswordException' => [true, 'mock-pfx'],
		];
	}
}
