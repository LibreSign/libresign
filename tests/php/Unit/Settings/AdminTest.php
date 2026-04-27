<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Settings;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\CertificateEngine\OpenSslHandler;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\CertificatePolicyService;
use OCA\Libresign\Service\DocMdp\ConfigService as DocMdpConfigService;
use OCA\Libresign\Service\FooterService;
use OCA\Libresign\Service\IdDocsPolicyService;
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
	private IAppConfig $appConfig;
	private SignatureTextService&MockObject $signatureTextService;
	private SignatureBackgroundService&MockObject $signatureBackgroundService;
	private FooterService&MockObject $footerService;
	private DocMdpConfigService&MockObject $docMdpConfigService;
	private PolicyService&MockObject $policyService;
	private IdDocsPolicyService&MockObject $idDocsPolicyService;
	public function setUp(): void {
		$this->initialState = $this->createMock(IInitialState::class);
		$this->accountService = $this->createMock(AccountService::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$this->certificateEngineFactory = $this->createMock(CertificateEngineFactory::class);
		$this->certificatePolicyService = $this->createMock(CertificatePolicyService::class);
		$this->appConfig = static::getMockAppConfigWithReset();
		$this->signatureTextService = $this->createMock(SignatureTextService::class);
		$this->signatureBackgroundService = $this->createMock(SignatureBackgroundService::class);
		$this->footerService = $this->createMock(FooterService::class);
		$this->docMdpConfigService = $this->createMock(DocMdpConfigService::class);
		$this->policyService = $this->createMock(PolicyService::class);
		$this->idDocsPolicyService = $this->createMock(IdDocsPolicyService::class);
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
			$this->idDocsPolicyService,
		);
		$this->stubGetFormDependencies();
	}

	/**
	 * Stubs all service dependencies of getForm() with safe neutral values so
	 * individual tests only need to configure what they actually exercise.
	 */
	private function stubGetFormDependencies(): void {
		$this->accountService->method('getConfig')->willReturn([]);
		$this->userSession->method('getUser')->willReturn($this->createMock(IUser::class));
		$this->identifyMethodService->method('getIdentifyMethodsSettings')->willReturn([]);
		$this->docMdpConfigService->method('getConfig')->willReturn([]);
		$this->policyService->method('resolveKnownPolicies')->willReturn([]);
		$this->idDocsPolicyService->method('isIdentificationDocumentsEnabled')->willReturn(false);

		$engine = $this->createMock(OpenSslHandler::class);
		$engine->method('getName')->willReturn('openssl');
		$this->certificateEngineFactory->method('getEngine')->willReturn($engine);

		$this->certificatePolicyService->method('getOid')->willReturn('');
		$this->certificatePolicyService->method('getCps')->willReturn('');

		$this->signatureTextService->method('parse')->willReturn(['parsed' => '']);
		$this->signatureTextService->method('getDefaultTemplate')->willReturn('');
		$this->signatureTextService->method('getDefaultTemplateFontSize')->willReturn(10.0);
		$this->signatureTextService->method('getAvailableVariables')->willReturn([]);
		$this->signatureTextService->method('getRenderMode')->willReturn('description_only');
		$this->signatureTextService->method('getTemplate')->willReturn('');
		$this->signatureTextService->method('getTemplateFontSize')->willReturn(10.0);
		$this->signatureTextService->method('getSignatureFontSize')->willReturn(10.0);
		$this->signatureTextService->method('getFullSignatureHeight')->willReturn(100.0);
		$this->signatureTextService->method('getFullSignatureWidth')->willReturn(350.0);

		$this->signatureBackgroundService->method('getSignatureBackgroundType')->willReturn('none');

		$this->footerService->method('getTemplateVariablesMetadata')->willReturn([]);
		$this->footerService->method('getTemplate')->willReturn('');
		$this->footerService->method('isDefaultTemplate')->willReturn(true);
	}

	public function testGetSessionReturningAppId():void {
		$this->assertEquals($this->admin->getSection(), Application::APP_ID);
	}

	public function testGetPriority():void {
		$this->assertEquals($this->admin->getPriority(), 100);
	}

	public function testGetFormSetsWorkerSrcCspForPdfPreview(): void {
		$response = $this->admin->getForm();

		$this->assertInstanceOf(TemplateResponse::class, $response);
		$policy = $response->getContentSecurityPolicy()->buildPolicy();
		$this->assertStringContainsString("worker-src 'self' blob:", $policy);
	}
}
