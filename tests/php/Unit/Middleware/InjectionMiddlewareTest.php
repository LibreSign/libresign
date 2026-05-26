<?php

declare(strict_types=1);


namespace OCA\Libresign\Tests\Unit\Middleware;

use OC\AppFramework\Bootstrap\Coordinator;
use OC\AppFramework\Services\InitialState;
use OC\InitialStateService;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Exception\PageException;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Middleware\Attribute\PrivateValidation;
use OCA\Libresign\Middleware\Attribute\RequireSignRequestUuid;
use OCA\Libresign\Middleware\InjectionMiddleware;
use OCA\Libresign\Service\FileAccessService;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\ValidationAccess\ValidationAccessPolicy;
use OCA\Libresign\Service\SignFileService;
use OCA\Libresign\Service\UuidResolverService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IServerContainer;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

final class PrivateValidationBypassController extends Controller {
	#[PrivateValidation(allowValidSignRequestUuid: true)]
	#[RequireSignRequestUuid]
	public function sign(): void {
	}
}

final class PrivateValidationProtectedController extends Controller {
	#[PrivateValidation]
	#[RequireSignRequestUuid]
	public function validation(): void {
	}
}

final class PrivateValidationFallbackProtectedController extends Controller {
	#[PrivateValidation]
	public function show(): void {
	}
}

final class InjectionMiddlewareTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IRequest&MockObject $request;
	private ISession&MockObject $session;
	private IUserSession&MockObject $userSession;
	private ValidateHelper&MockObject $validateHelper;
	private SignRequestMapper&MockObject $signRequestMapper;
	private CertificateEngineFactory $certificateEngineFactory;
	private FileMapper&MockObject $fileMapper;
	private IInitialState $initialState;
	private FileAccessService&MockObject $fileAccessService;
	private SignFileService&MockObject $signFileService;
	private UuidResolverService&MockObject $uuidResolverService;
	private PolicyService&MockObject $policyService;
	private IL10N&MockObject $l10n;
	private IURLGenerator&MockObject $urlGenerator;
	private ?string $userId = null;

	private InitialStateService $initialStateService;

	public function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->session = $this->createMock(ISession::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->validateHelper = $this->createMock(ValidateHelper::class);
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->certificateEngineFactory = $this->createMock(CertificateEngineFactory::class);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);

		$this->initialStateService = new InitialStateService(
			$this->createMock(LoggerInterface::class),
			$this->createMock(Coordinator::class),
			$this->createMock(IServerContainer::class)
		);
		$this->initialState = new InitialState($this->initialStateService, 'libresign');
		$this->fileAccessService = $this->createMock(FileAccessService::class);
		$this->signFileService = $this->createMock(SignFileService::class);
		$this->uuidResolverService = $this->createMock(UuidResolverService::class);
		$this->policyService = $this->createMock(PolicyService::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->userId = null;
	}

	public function getInjectionMiddleware(): InjectionMiddleware {
		return new InjectionMiddleware(
			$this->request,
			$this->session,
			$this->userSession,
			$this->validateHelper,
			$this->signRequestMapper,
			$this->certificateEngineFactory,
			$this->fileMapper,
			$this->initialState,
			$this->fileAccessService,
			$this->signFileService,
			$this->uuidResolverService,
			$this->policyService,
			$this->l10n,
			$this->urlGenerator,
			$this->userId,
		);
	}

	/**
	 * @dataProvider providerAfterException
	 */
	public function testAfterException(string $message, int $code, string $exception, callable $expected): void {
		$controller = $this->createMock(Controller::class);
		$methodName = 'fake';
		try {
			throw new $exception($message, $code);
		} catch (\Throwable $exception) {
		}
		if ($exception instanceof PageException) {
			$this->request
				->method('getHeader')
				->with('Accept')
				->willReturn('text/html');
		}
		$injectionMiddleware = $this->getInjectionMiddleware();
		$actual = $injectionMiddleware->afterException($controller, $methodName, $exception);
		$expected($this, $message, $code, $actual);
	}

	public static function providerAfterException(): array {
		return [
			[
				json_encode(['action' => 1000]), 1, LibresignException::class,
				function (self $self, $message, int $code, $actual):void {
					/** @var JSONResponse $actual */
					$self->assertInstanceOf(
						JSONResponse::class,
						$actual,
						'The response need to be JSONResponse'
					);
					$self->assertJsonStringEqualsJsonString(
						$message,
						json_encode($actual->getData()),
						'Invalid response json content'
					);
					$self->assertEquals(
						$code,
						$actual->getStatus(),
						'Invalid response status code'
					);
				},
			],
			[
				'a text here', 1, LibresignException::class,
				function (self $self, $message, int $code, $actual):void {
					/** @var JSONResponse $actual */
					$self->assertInstanceOf(
						JSONResponse::class,
						$actual,
						'The response need to be JSONResponse'
					);
					$self->assertJsonStringEqualsJsonString(
						json_encode(['message' => $message]),
						json_encode($actual->getData()),
						'Invalid response json content'
					);
					$self->assertEquals(
						$code,
						$actual->getStatus(),
						'Invalid response status code'
					);
				},
			],
			[
				'a text here', 1, PageException::class,
				function (self $self, $message, int $code, $actual):void {
					/** @var TemplateResponse $actual */
					$self->assertInstanceOf(
						TemplateResponse::class,
						$actual,
						'The response need to be TemplateResponse'
					);
					$states = $self->initialStateService->getInitialStates();
					$self->assertArrayHasKey('libresign-error', $states);
					$self->assertJsonStringEqualsJsonString(
						json_encode(['message' => $message]),
						$states['libresign-error'],
						'Invalid response params content'
					);
					$self->assertEquals(
						$code,
						$actual->getStatus(),
						'Invalid response status code'
					);
				},
			],
			[
				json_encode(['action' => 1000]), 1, PageException::class,
				function (self $self, $message, int $code, $actual):void {
					/** @var TemplateResponse $actual */
					$self->assertInstanceOf(
						TemplateResponse::class,
						$actual,
						'The response need to be TemplateResponse'
					);
					$states = $self->initialStateService->getInitialStates();
					$self->assertJsonStringEqualsJsonString(
						json_encode([
							'libresign-action' => '1000',
						]),
						json_encode($states),
						'Invalid response params content'
					);
					$self->assertEquals(
						$code,
						$actual->getStatus(),
						'Invalid response status code'
					);
				},
			],
			[
				json_encode(['action' => 1000, 'redirect' => 'http://fake.url']), 1, PageException::class,
				function (self $self, $message, int $code, $actual):void {
					/** @var RedirectResponse $actual */
					$self->assertInstanceOf(
						RedirectResponse::class,
						$actual,
						'The response need to be RedirectResponse'
					);
					$self->assertEquals(
						'http://fake.url',
						$actual->getRedirectURL(),
						'Invalid redirect URL'
					);
					$self->assertEquals(
						303,
						$actual->getStatus(),
						'Invalid response status code'
					);
				},
			],
		];
	}

	public function testBeforeControllerAllowsUnauthenticatedAccessWithValidUuidWhenAttributeEnablesBypass(): void {
		$controller = new PrivateValidationBypassController('libresign', $this->request);
		$resolvedPolicy = (new ResolvedPolicy())
			->setEffectiveValue(true);

		$this->userSession
			->expects($this->once())
			->method('isLoggedIn')
			->willReturn(false);

		$this->policyService
			->expects($this->once())
			->method('resolve')
			->with(ValidationAccessPolicy::KEY)
			->willReturn($resolvedPolicy);

		$this->request
			->expects($this->once())
			->method('getParam')
			->willReturnCallback(function (string $key, $default = null) {
				return match ($key) {
					'uuid' => 'valid-uuid',
					default => $default,
				};
			});

		$this->request
			->expects($this->once())
			->method('getHeader')
			->with('libresign-sign-request-uuid')
			->willReturn('');

		$this->signRequestMapper
			->expects($this->once())
			->method('getByUuid')
			->with('valid-uuid')
			->willReturn($this->createStub(SignRequest::class));

		$injectionMiddleware = $this->getInjectionMiddleware();
		$injectionMiddleware->beforeController($controller, 'sign');

		$this->addToAssertionCount(1);
	}

	public function testBeforeControllerRedirectsUnauthenticatedAccessWhenAttributeDoesNotEnableBypass(): void {
		$controller = new PrivateValidationProtectedController('libresign', $this->request);
		$resolvedPolicy = (new ResolvedPolicy())
			->setEffectiveValue(true);

		$this->userSession
			->expects($this->once())
			->method('isLoggedIn')
			->willReturn(false);

		$this->policyService
			->expects($this->once())
			->method('resolve')
			->with(ValidationAccessPolicy::KEY)
			->willReturn($resolvedPolicy);

		$this->request
			->expects($this->once())
			->method('getRawPathInfo')
			->willReturn('/apps/libresign/validation/valid-uuid');

		$this->l10n
			->expects($this->once())
			->method('t')
			->with('You are not logged in. Please log in.')
			->willReturn('You are not logged in. Please log in.');

		$this->urlGenerator
			->expects($this->once())
			->method('linkToRoute')
			->with('core.login.showLoginForm', [
				'redirect_url' => '/apps/libresign/validation/valid-uuid',
			])
			->willReturn('/index.php/login?redirect_url=/apps/libresign/validation/valid-uuid');

		$this->signRequestMapper
			->expects($this->never())
			->method('getByUuid');

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(Http::STATUS_UNAUTHORIZED);

		$injectionMiddleware = $this->getInjectionMiddleware();
		$injectionMiddleware->beforeController($controller, 'validation');
	}

	public function testBeforeControllerRedirectsProtectedValidationPathWithoutResolvedRouteName(): void {
		$controller = new PrivateValidationFallbackProtectedController('libresign', $this->request);
		$resolvedPolicy = (new ResolvedPolicy())
			->setEffectiveValue(true);

		$this->userSession
			->expects($this->once())
			->method('isLoggedIn')
			->willReturn(false);

		$this->policyService
			->expects($this->once())
			->method('resolve')
			->with(ValidationAccessPolicy::KEY)
			->willReturn($resolvedPolicy);

		$this->request
			->expects($this->once())
			->method('getRawPathInfo')
			->willReturn('/apps/libresign/validation/fakeuuid-6037-47be-9d9e-3d90b9d0a3ea');

		$this->l10n
			->expects($this->once())
			->method('t')
			->with('You are not logged in. Please log in.')
			->willReturn('You are not logged in. Please log in.');

		$this->urlGenerator
			->expects($this->once())
			->method('linkToRoute')
			->with('core.login.showLoginForm', [
				'redirect_url' => '/apps/libresign/validation/fakeuuid-6037-47be-9d9e-3d90b9d0a3ea',
			])
			->willReturn('/index.php/login?redirect_url=/apps/libresign/validation/fakeuuid-6037-47be-9d9e-3d90b9d0a3ea');

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(Http::STATUS_UNAUTHORIZED);

		$injectionMiddleware = $this->getInjectionMiddleware();
		$injectionMiddleware->beforeController($controller, 'show');
	}
}
