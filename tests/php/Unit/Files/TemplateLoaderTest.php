<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Files;

use OCA\Files\Event\LoadSidebar;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Files\TemplateLoader;
use OCA\Libresign\Files\TemplateLoaderAssets;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\CertificateEngine\IEngineHandler;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Policy\PolicyService;
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
	private TemplateLoaderAssets&MockObject $assets;

	#[\Override]
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
		$this->assets = $this->createMock(TemplateLoaderAssets::class);
	}

	public function testHandleProvidesInitialStatePayload(): void {
		$engine = $this->createMock(IEngineHandler::class);
		$engine->method('isSetupOk')->willReturn(true);
		$this->certificateEngineFactory
			->method('getEngine')
			->willReturn($engine);
		$this->appManager
			->method('isEnabledForUser')
			->with('libresign')
			->willReturn(true);

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
			->method('resolveKnownPolicyStates')
			->willReturn([
				'signature_flow' => [
					'policyKey' => 'signature_flow',
					'effectiveValue' => 'parallel',
					'inheritedValue' => null,
					'sourceScope' => 'group',
					'visible' => true,
					'editableByCurrentActor' => true,
					'allowedValues' => ['parallel', 'ordered_numeric'],
					'canSaveAsUserDefault' => true,
					'canUseAsRequestOverride' => true,
					'preferenceWasCleared' => false,
					'blockedBy' => null,
				],
			]);
		$this->assets
			->expects($this->once())
			->method('addInitScript')
			->with(Application::APP_ID, 'libresign-init');
		$this->assets
			->expects($this->once())
			->method('addScript')
			->with(Application::APP_ID, 'libresign-tab');
		$this->assets
			->expects($this->once())
			->method('addStyle')
			->with(Application::APP_ID, 'libresign-tab');

		$capturedState = [];
		$this->initialState
			->expects($this->exactly(3))
			->method('provideInitialState')
			->willReturnCallback(static function (string $key, mixed $value) use (&$capturedState): void {
				$capturedState[$key] = $value;
			});

		$loader = $this->getLoader();
		$loader->handle(new LoadSidebar());

		$expectedState = [
			'certificate_ok' => true,
			'effective_policies' => [
				'policies' => [
					'signature_flow' => [
						'policyKey' => 'signature_flow',
						'effectiveValue' => 'parallel',
						'inheritedValue' => null,
						'sourceScope' => 'group',
						'visible' => true,
						'editableByCurrentActor' => true,
						'allowedValues' => ['parallel', 'ordered_numeric'],
						'canSaveAsUserDefault' => true,
						'canUseAsRequestOverride' => true,
						'preferenceWasCleared' => false,
						'blockedBy' => null,
					],
				],
			],
			'can_request_sign' => true,
		];
		ksort($capturedState);
		ksort($expectedState);
		$this->assertSame($expectedState, $capturedState);
	}

	public function testHandleProvidesCannotRequestSignWhenValidationFails(): void {
		$engine = $this->createMock(IEngineHandler::class);
		$engine->method('isSetupOk')->willReturn(true);
		$this->certificateEngineFactory
			->method('getEngine')
			->willReturn($engine);
		$this->appManager
			->method('isEnabledForUser')
			->with('libresign')
			->willReturn(true);

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
			->method('resolveKnownPolicyStates')
			->willReturn([
				'signature_flow' => [
					'policyKey' => 'signature_flow',
					'effectiveValue' => 'none',
					'inheritedValue' => null,
					'sourceScope' => 'system',
					'visible' => true,
					'editableByCurrentActor' => true,
					'allowedValues' => ['none', 'parallel', 'ordered_numeric'],
					'canSaveAsUserDefault' => true,
					'canUseAsRequestOverride' => true,
					'preferenceWasCleared' => false,
					'blockedBy' => null,
				],
			]);
		$this->assets
			->expects($this->once())
			->method('addInitScript')
			->with(Application::APP_ID, 'libresign-init');
		$this->assets
			->expects($this->once())
			->method('addScript')
			->with(Application::APP_ID, 'libresign-tab');
		$this->assets
			->expects($this->once())
			->method('addStyle')
			->with(Application::APP_ID, 'libresign-tab');

		$capturedState = [];
		$this->initialState
			->expects($this->exactly(3))
			->method('provideInitialState')
			->willReturnCallback(static function (string $key, mixed $value) use (&$capturedState): void {
				$capturedState[$key] = $value;
			});

		$loader = $this->getLoader();
		$loader->handle(new LoadSidebar());

		$this->assertArrayHasKey('can_request_sign', $capturedState);
		$this->assertFalse($capturedState['can_request_sign']);
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
			$this->assets,
		);
	}

	/**
	 * Regression test for https://github.com/LibreSign/libresign/issues/7632
	 *
	 * The `icons` CSS style must NOT be registered separately because:
	 * 1. It does not exist as a standalone CSS file in the Vite build output.
	 * 2. Its content (.icon-libresign) is already bundled inside `libresign-tab`.
	 * Loading a non-existent file causes a 404 on every page load with the
	 * files sidebar, and any unscoped `list-style` rule in that file would
	 * bleed into other Nextcloud apps (e.g. Notes, Markdown).
	 */
	public function testHandleDoesNotRegisterIconsStyleSeparately(): void {
		$this->appManager
			->method('isEnabledForUser')
			->with('libresign')
			->willReturn(true);

		$engine = $this->createMock(IEngineHandler::class);
		$engine->method('isSetupOk')->willReturn(true);
		$this->certificateEngineFactory
			->method('getEngine')
			->willReturn($engine);
		$this->identifyMethodService
			->method('getIdentifyMethodsSettings')
			->willReturn([]);
		$this->policyService
			->method('resolveKnownPolicyStates')
			->willReturn([]);
		$user = $this->createMock(IUser::class);
		$this->userSession
			->method('getUser')
			->willReturn($user);
		$this->assets
			->expects($this->once())
			->method('addStyle')
			->with(
				Application::APP_ID,
				$this->callback(static function (string $style): bool {
					self::assertNotSame('icons', $style, 'The "icons" CSS must not be registered separately because it is bundled in libresign-tab (issue #7632).');
					return $style === 'libresign-tab';
				})
			);
		$this->assets
			->expects($this->once())
			->method('addScript')
			->with(Application::APP_ID, 'libresign-tab');
		$this->assets
			->expects($this->once())
			->method('addInitScript')
			->with(Application::APP_ID, 'libresign-init');

		$loader = $this->getLoader();
		$loader->handle(new LoadSidebar());
	}

	public function testHandleRegistersFilesInitScriptWhenCertificateIsReady(): void {
		$this->appManager
			->method('isEnabledForUser')
			->with('libresign')
			->willReturn(true);

		$engine = $this->createMock(IEngineHandler::class);
		$engine->method('isSetupOk')->willReturn(true);
		$this->certificateEngineFactory
			->method('getEngine')
			->willReturn($engine);
		$this->identifyMethodService
			->method('getIdentifyMethodsSettings')
			->willReturn([]);
		$this->policyService
			->method('resolveKnownPolicyStates')
			->willReturn([]);
		$user = $this->createMock(IUser::class);
		$this->userSession
			->method('getUser')
			->willReturn($user);
		$this->assets
			->expects($this->once())
			->method('addInitScript')
			->with(
				Application::APP_ID,
				'libresign-init'
			);
		$this->assets
			->expects($this->once())
			->method('addScript')
			->with(Application::APP_ID, 'libresign-tab');
		$this->assets
			->expects($this->once())
			->method('addStyle')
			->with(Application::APP_ID, 'libresign-tab');

		$loader = $this->getLoader();
		$loader->handle(new LoadSidebar());
	}
}
