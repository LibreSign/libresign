<?php

declare(strict_types=1);

use OC\AppFramework\Bootstrap\Coordinator;
use OC\AppFramework\Services\InitialState;
use OC\InitialStateService;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Exception\PageException;
use OCA\Libresign\Handler\CertificateEngine\Handler as CertificateEngineHandler;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Middleware\InjectionMiddleware;
use OCA\Libresign\Service\SignFileService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IServerContainer;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

final class InjectionMiddlewareTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IRequest&MockObject $request;
	private IUserSession&MockObject $userSession;
	private ValidateHelper&MockObject $validateHelper;
	private SignRequestMapper&MockObject $signRequestMapper;
	private CertificateEngineHandler $certificateEngineHandler;
	private FileMapper&MockObject $fileMapper;
	private IInitialState $initialState;
	private SignFileService&MockObject $signFileService;
	private IL10N&MockObject $l10n;
	private ?string $userId;

	private InitialStateService $initialStateService;

	public function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->validateHelper = $this->createMock(ValidateHelper::class);
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->certificateEngineHandler = $this->createMock(CertificateEngineHandler::class);
		$this->fileMapper = $this->createMock(FileMapper::class);

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
			$this->userSession,
			$this->validateHelper,
			$this->signRequestMapper,
			$this->certificateEngineHandler,
			$this->fileMapper,
			$this->initialState,
			$this->signFileService,
			$this->l10n,
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
		];
	}
}
