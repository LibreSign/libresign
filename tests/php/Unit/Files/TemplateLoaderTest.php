<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Files;

use OCA\Libresign\Files\TemplateLoader;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\CertificateEngine\IEngineHandler;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\DocMdp\ConfigService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\App\IAppManager;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;

final class TemplateLoaderTest extends TestCase {
	private IRequest&MockObject $request;
	private IUserSession&MockObject $userSession;
	private AccountService&MockObject $accountService;
	private IInitialState&MockObject $initialState;
	private ValidateHelper&MockObject $validateHelper;
	private IdentifyMethodService&MockObject $identifyMethodService;
	private CertificateEngineFactory&MockObject $certificateEngineFactory;
	private IAppConfig&MockObject $appConfig;
	private IAppManager&MockObject $appManager;
	private ConfigService&MockObject $docMdpConfigService;

	public function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->accountService = $this->createMock(AccountService::class);
		$this->initialState = $this->createMock(IInitialState::class);
		$this->validateHelper = $this->createMock(ValidateHelper::class);
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$this->certificateEngineFactory = $this->createMock(CertificateEngineFactory::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->docMdpConfigService = $this->createMock(ConfigService::class);
	}

	public function testGetInitialStatePayload(): void {
		$engine = $this->createMock(IEngineHandler::class);
		$engine->method('isSetupOk')->willReturn(true);
		$this->certificateEngineFactory
			->method('getEngine')
			->willReturn($engine);

		$this->identifyMethodService
			->method('getIdentifyMethodsSettings')
			->willReturn([]);

		$this->appConfig
			->method('getValueString')
			->willReturn('none');

		$this->validateHelper
			->method('canRequestSign');

		$user = $this->createMock(IUser::class);
		$this->userSession
			->method('getUser')
			->willReturn($user);

		$docMdpConfig = [
			'enabled' => true,
			'defaultLevel' => 1,
			'availableLevels' => [],
		];
		$this->docMdpConfigService
			->method('getConfig')
			->willReturn($docMdpConfig);

		$loader = $this->getLoader();
		$payload = self::invokePrivate($loader, 'getInitialStatePayload');

		$this->assertSame([
			'certificate_ok' => true,
			'identify_methods' => [],
			'signature_flow' => 'none',
			'docmdp_config' => $docMdpConfig,
			'can_request_sign' => true,
		], $payload);
	}

	public function testGetInitialStatePayloadWhenCannotRequestSign(): void {
		$engine = $this->createMock(IEngineHandler::class);
		$engine->method('isSetupOk')->willReturn(true);
		$this->certificateEngineFactory
			->method('getEngine')
			->willReturn($engine);

		$this->identifyMethodService
			->method('getIdentifyMethodsSettings')
			->willReturn([]);

		$this->appConfig
			->method('getValueString')
			->willReturn('none');

		$this->validateHelper
			->method('canRequestSign')
			->willThrowException(new \OCA\Libresign\Exception\LibresignException('no'));

		$user = $this->createMock(IUser::class);
		$this->userSession
			->method('getUser')
			->willReturn($user);

		$this->docMdpConfigService
			->method('getConfig')
			->willReturn([]);

		$loader = $this->getLoader();
		$payload = self::invokePrivate($loader, 'getInitialStatePayload');

		$this->assertFalse($payload['can_request_sign']);
	}

	private function getLoader(): TemplateLoader {
		return new TemplateLoader(
			$this->request,
			$this->userSession,
			$this->accountService,
			$this->initialState,
			$this->validateHelper,
			$this->identifyMethodService,
			$this->certificateEngineFactory,
			$this->appConfig,
			$this->appManager,
			$this->docMdpConfigService,
		);
	}
}
