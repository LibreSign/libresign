<?php

declare(strict_types=1);

use OC\AppFramework\Bootstrap\Coordinator;
use OC\AppFramework\Services\InitialState;
use OC\InitialStateService;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Exception\PageException;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Middleware\InjectionMiddleware;
use OCA\Libresign\Service\SignFileService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
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

final class InjectionMiddlewareTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IRequest&MockObject $request;
	private ISession&MockObject $session;
	private IUserSession&MockObject $userSession;
	private ValidateHelper&MockObject $validateHelper;
	private SignRequestMapper&MockObject $signRequestMapper;
	private CertificateEngineFactory $certificateEngineFactory;
	private FileMapper&MockObject $fileMapper;
	private IInitialState $initialState;
	private SignFileService&MockObject $signFileService;
	private IL10N&MockObject $l10n;
	private IappConfig&MockObject $appConfig;
	private IurlGenerator&MockObject $urlGenerator;
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
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);

		$this->initialStateService = new InitialStateService(
			$this->createMock(LoggerInterface::class),
			$this->createMock(Coordinator::class),
			$this->createMock(IServerContainer::class)
		);
		$this->initialState = new InitialState($this->initialStateService, 'libresign');
		$this->signFileService = $this->createMock(SignFileService::class);
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
			$this->signFileService,
			$this->l10n,
			$this->appConfig,
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
}
