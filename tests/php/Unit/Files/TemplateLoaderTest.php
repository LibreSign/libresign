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
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\CertificateEngine\IEngineHandler;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\DocMdp\ConfigService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
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
			->method('resolveKnownPolicies')
			->willReturn([
				'signature_flow'
				=> (new ResolvedPolicy())
					->setPolicyKey('signature_flow')
					->setEffectiveValue('parallel')
					->setSourceScope('group')
					->setVisible(true)
					->setEditableByCurrentActor(true)
					->setAllowedValues(['parallel', 'ordered_numeric'])
					->setCanSaveAsUserDefault(true)
					->setCanUseAsRequestOverride(true)
			]);

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
			->method('resolveKnownPolicies')
			->willReturn([
				'signature_flow'
				=> (new ResolvedPolicy())
					->setPolicyKey('signature_flow')
					->setEffectiveValue('none')
					->setSourceScope('system')
					->setVisible(true)
					->setEditableByCurrentActor(true)
					->setAllowedValues(['none', 'parallel', 'ordered_numeric'])
					->setCanSaveAsUserDefault(true)
					->setCanUseAsRequestOverride(true)
			]);

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
		$this->appConfig
			->method('getValueString')
			->willReturn('none');
		$this->docMdpConfigService
			->method('getConfig')
			->willReturn([]);
		$user = $this->createMock(IUser::class);
		$this->userSession
			->method('getUser')
			->willReturn($user);

		$stylesBefore = \OC_Util::$styles;
		$loader = $this->getLoader();
		$loader->handle(new LoadSidebar());
		$stylesAfter = \OC_Util::$styles;

		$newStyles = array_diff($stylesAfter, $stylesBefore);
		$iconsStylePath = Application::APP_ID . '/css/icons';

		foreach ($newStyles as $style) {
			$this->assertStringNotContainsString(
				$iconsStylePath,
				$style,
				'The "icons" CSS must not be registered separately — it is already bundled in libresign-tab and loading it would cause a 404 and potential global CSS leaks (issue #7632).'
			);
		}
	}
}
