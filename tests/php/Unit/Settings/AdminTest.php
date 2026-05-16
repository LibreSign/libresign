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
use OCA\Libresign\Service\FooterService;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
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
	private CertificateEngineFactory&MockObject $certificateEngineFactory;
	private CertificatePolicyService&MockObject $certificatePolicyService;
	private IAppConfig $appConfig;
	private FooterService&MockObject $footerService;
	private PolicyService&MockObject $policyService;
	public function setUp(): void {
		$this->initialState = $this->createMock(IInitialState::class);
		$this->accountService = $this->createMock(AccountService::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->certificateEngineFactory = $this->createMock(CertificateEngineFactory::class);
		$this->certificatePolicyService = $this->createMock(CertificatePolicyService::class);
		$this->appConfig = static::getMockAppConfigWithReset();
		$this->footerService = $this->createMock(FooterService::class);
		$this->policyService = $this->createMock(PolicyService::class);
		$this->admin = new Admin(
			$this->initialState,
			$this->accountService,
			$this->userSession,
			$this->certificateEngineFactory,
			$this->certificatePolicyService,
			$this->appConfig,
			$this->footerService,
			$this->policyService,
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
		$this->policyService->method('resolveKnownPolicies')->willReturn([]);
		$this->policyService->method('resolve')->willReturn(
			(new ResolvedPolicy())->setEffectiveValue(''),
		);

		$engine = $this->createMock(OpenSslHandler::class);
		$engine->method('getName')->willReturn('openssl');
		$this->certificateEngineFactory->method('getEngine')->willReturn($engine);

		$this->certificatePolicyService->method('getOid')->willReturn('');
		$this->certificatePolicyService->method('getCps')->willReturn('');

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
