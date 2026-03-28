<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Settings;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Handler\CertificateEngine\AEngineHandler;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\CertificatePolicyService;
use OCA\Libresign\Service\DocMdp\ConfigService as DocMdpConfigService;
use OCA\Libresign\Service\FooterService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\SignatureBackgroundService;
use OCA\Libresign\Service\SignatureTextService;
use OCA\Libresign\Settings\Admin;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @internal
 */
final class AdminTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private Admin $admin;
	private IInitialState&MockObject $initialState;
	private AccountService&MockObject $accountService;
	private IUserSession&MockObject $userSession;
	private IdentifyMethodService&MockObject $identifyMethodService;
	private CertificateEngineFactory&MockObject $certificateEngineFactory;
	private CertificatePolicyService&MockObject $certificatePolicyService;
	private IAppConfig&MockObject $appConfig;
	private SignatureTextService&MockObject $signatureTextService;
	private SignatureBackgroundService&MockObject $signatureBackgroundService;
	private FooterService&MockObject $footerService;
	private DocMdpConfigService&MockObject $docMdpConfigService;
	private PolicyService&MockObject $policyService;
	public function setUp(): void {
		$this->initialState = $this->createMock(IInitialState::class);
		$this->accountService = $this->createMock(AccountService::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$this->certificateEngineFactory = $this->createMock(CertificateEngineFactory::class);
		$this->certificatePolicyService = $this->createMock(CertificatePolicyService::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->signatureTextService = $this->createMock(SignatureTextService::class);
		$this->signatureBackgroundService = $this->createMock(SignatureBackgroundService::class);
		$this->footerService = $this->createMock(FooterService::class);
		$this->docMdpConfigService = $this->createMock(DocMdpConfigService::class);
		$this->policyService = $this->createMock(PolicyService::class);
		$this->admin = new Admin(
			$this->initialState,
			$this->accountService,
			$this->userSession,
			$this->identifyMethodService,
			$this->certificateEngineFactory,
			$this->certificatePolicyService,
			$this->appConfig,
			$this->signatureTextService,
			$this->signatureBackgroundService,
			$this->footerService,
			$this->docMdpConfigService,
			$this->policyService,
		);
	}

	public function testGetSessionReturningAppId():void {
		$this->assertEquals($this->admin->getSection(), Application::APP_ID);
	}

	public function testGetPriority():void {
		$this->assertEquals($this->admin->getPriority(), 100);
	}

	public function testGetFormProvidesUserConfigInitialState(): void {
		$user = $this->createMock(IUser::class);
		$engine = $this->getMockBuilder(AEngineHandler::class)
			->disableOriginalConstructor()
			->onlyMethods(['getName'])
			->getMockForAbstractClass();
		$providedState = [];

		$this->userSession
			->method('getUser')
			->willReturn($user);
		$this->accountService
			->expects($this->once())
			->method('getConfig')
			->with($user)
			->willReturn(['policy_workbench_catalog_compact_view' => true]);
		$this->initialState
			->method('provideInitialState')
			->willReturnCallback(function (string $key, mixed $value) use (&$providedState): void {
				$providedState[$key] = $value;
			});
		$this->signatureTextService
			->method('parse')
			->willReturn(['parsed' => '']);
		$engine
			->method('getName')
			->willReturn('OpenSsl');
		$this->certificateEngineFactory
			->method('getEngine')
			->willReturn($engine);
		$this->certificatePolicyService
			->method('getOid')
			->willReturn('');
		$this->certificatePolicyService
			->method('getCps')
			->willReturn('');
		$this->appConfig
			->method('getValueString')
			->willReturnCallback(static fn (string $appId, string $key, string $default = ''): string => $default);
		$this->appConfig
			->method('getValueFloat')
			->willReturnCallback(static fn (string $appId, string $key, float $default = 0.0): float => $default);
		$this->appConfig
			->method('getValueInt')
			->willReturnCallback(static fn (string $appId, string $key, int $default = 0): int => $default);
		$this->appConfig
			->method('getValueBool')
			->willReturnCallback(static fn (string $appId, string $key, bool $default = false): bool => $default);
		$this->appConfig
			->method('getValueArray')
			->willReturnCallback(static fn (string $appId, string $key, array $default = []): array => $default);
		$this->signatureTextService
			->method('getDefaultTemplate')
			->willReturn('');
		$this->signatureTextService
			->method('getDefaultTemplateFontSize')
			->willReturn(12.0);
		$this->identifyMethodService
			->method('getIdentifyMethodsSettings')
			->willReturn([]);
		$this->signatureTextService
			->method('getAvailableVariables')
			->willReturn([]);
		$this->signatureBackgroundService
			->method('getSignatureBackgroundType')
			->willReturn('none');
		$this->signatureTextService
			->method('getSignatureFontSize')
			->willReturn(12.0);
		$this->signatureTextService
			->method('getFullSignatureHeight')
			->willReturn(100.0);
		$this->footerService
			->method('getTemplateVariablesMetadata')
			->willReturn([]);
		$this->footerService
			->method('getTemplate')
			->willReturn('');
		$this->footerService
			->method('isDefaultTemplate')
			->willReturn(true);
		$this->signatureTextService
			->method('getRenderMode')
			->willReturn('text');
		$this->signatureTextService
			->method('getTemplate')
			->willReturn('');
		$this->signatureTextService
			->method('getFullSignatureWidth')
			->willReturn(100.0);
		$this->signatureTextService
			->method('getTemplateFontSize')
			->willReturn(12.0);
		$this->docMdpConfigService
			->method('getConfig')
			->willReturn([]);
		$this->policyService
			->method('resolveKnownPolicies')
			->willReturn([]);

		$response = $this->admin->getForm();

		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertSame(
			['policy_workbench_catalog_compact_view' => true],
			$providedState['config'] ?? null,
		);
	}
}
