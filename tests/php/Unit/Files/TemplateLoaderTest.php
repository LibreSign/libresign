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
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\Signature\SignatureFlowPolicy;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\App\IAppManager;
use OCP\AppFramework\Services\IInitialState;
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
	private PolicyService&MockObject $policyService;
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
		$this->policyService = $this->createMock(PolicyService::class);
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

		$this->validateHelper
			->method('canRequestSign');

		$user = $this->createMock(IUser::class);
		$this->userSession
			->method('getUser')
			->willReturn($user);

		$this->policyService
			->method('resolve')
			->with(SignatureFlowPolicy::KEY)
			->willReturn(
				(new ResolvedPolicy())
					->setPolicyKey('signature_flow')
					->setEffectiveValue('parallel')
					->setSourceScope('group')
					->setVisible(true)
					->setEditableByCurrentActor(true)
					->setAllowedValues(['parallel', 'ordered_numeric'])
					->setCanSaveAsUserDefault(true)
					->setCanUseAsRequestOverride(true)
			);

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
			'signature_flow_policy' => [
				'policyKey' => 'signature_flow',
				'effectiveValue' => 'parallel',
				'sourceScope' => 'group',
				'visible' => true,
				'editableByCurrentActor' => true,
				'allowedValues' => ['parallel', 'ordered_numeric'],
				'canSaveAsUserDefault' => true,
				'canUseAsRequestOverride' => true,
				'preferenceWasCleared' => false,
				'blockedBy' => null,
			],
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

		$this->validateHelper
			->method('canRequestSign')
			->willThrowException(new \OCA\Libresign\Exception\LibresignException('no'));

		$user = $this->createMock(IUser::class);
		$this->userSession
			->method('getUser')
			->willReturn($user);

		$this->policyService
			->method('resolve')
			->with(SignatureFlowPolicy::KEY)
			->willReturn(
				(new ResolvedPolicy())
					->setPolicyKey('signature_flow')
					->setEffectiveValue('none')
					->setSourceScope('system')
					->setVisible(true)
					->setEditableByCurrentActor(true)
					->setAllowedValues(['none', 'parallel', 'ordered_numeric'])
					->setCanSaveAsUserDefault(true)
					->setCanUseAsRequestOverride(true)
			);

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
			$this->policyService,
			$this->appManager,
			$this->docMdpConfigService,
		);
	}
}
