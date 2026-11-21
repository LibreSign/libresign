<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Controller;

use OCA\Libresign\Controller\DirectSignController;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Middleware\Attribute\RequireManager;
use OCA\Libresign\Service\Signing\DirectSigningService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class DirectSignControllerTest extends TestCase {
	private IRequest&MockObject $request;
	private IUserSession&MockObject $session;
	private DirectSigningService&MockObject $signing;
	private LoggerInterface&MockObject $logger;
	private DirectSignController $controller;
	private IUser&MockObject $actor;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->session = $this->createMock(IUserSession::class);
		$this->signing = $this->createMock(DirectSigningService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->actor = $this->createMock(IUser::class);
		$this->session->method('getUser')->willReturn($this->actor);
		$this->controller = new DirectSignController($this->request, $this->session, $this->signing, $this->logger);
	}

	public function testDedicatedRouteRequiresAuthenticatedRequestManagerAndIsNotPublic(): void {
		$method = new \ReflectionMethod(DirectSignController::class, 'signDirect');
		self::assertCount(1, $method->getAttributes(RequireManager::class));
		self::assertCount(0, $method->getAttributes(PublicPage::class));
		self::assertCount(1, $method->getAttributes(ApiRoute::class));
		self::assertSame('/api/{apiVersion}/sign/direct', $method->getAttributes(ApiRoute::class)[0]->getArguments()['url']);
	}

	public function testReturnsBinaryPdfAndDoesNotSignOnBehalfOfCallerSuppliedPerson(): void {
		$pdf = '%PDF-1.7 example bytes';
		$this->signing->expects(self::once())->method('sign')
			->with($this->actor, ['base64' => 'pdf-data'], 'contract.pdf', 'clickToSign', '', [], null)
			->willReturn(['content' => $pdf, 'filename' => 'contract-signed.pdf', 'uuid' => 'document-uuid']);

		$response = $this->controller->signDirect(
			file: ['base64' => 'pdf-data'],
			name: 'contract.pdf',
			method: 'clickToSign',
		);

		self::assertInstanceOf(DataDownloadResponse::class, $response);
		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertSame('application/pdf', $response->getHeaders()['Content-Type']);
		self::assertSame('document-uuid', $response->getHeaders()['X-LibreSign-File-UUID']);
		self::assertSame('no-store', $response->getHeaders()['Cache-Control']);
	}

	#[DataProvider('unsupportedDirectSignParameters')]
	public function testRejectsUnsupportedParametersBeforeCreatingRequest(string $parameter, mixed $value): void {
		$this->request->method('getParam')->willReturnCallback(
			static fn (string $name, mixed $default = null): mixed => $name === $parameter ? $value : $default,
		);
		$this->signing->expects(self::never())->method('sign');
		$response = $this->controller->signDirect(name: 'contract.pdf', method: 'clickToSign');
		self::assertInstanceOf(DataResponse::class, $response);
		self::assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public static function unsupportedDirectSignParameters(): iterable {
		yield 'other signers' => ['signers', [['email' => 'other@example.test']]];
		yield 'visible signature positions' => ['visibleElements', []];
		yield 'multiple files' => ['files', []];
		yield 'callback' => ['callback', 'https://example.invalid/notify'];
		yield 'arbitrary identity' => ['identifyValue', 'another-account'];
		yield 'existing request UUID' => ['uuid', 'existing-uuid'];
	}

	public function testRejectsUnauthenticatedRequest(): void {
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn(null);
		$this->signing->expects(self::never())->method('sign');
		$controller = new DirectSignController($this->request, $session, $this->signing, $this->logger);

		self::assertSame(Http::STATUS_FORBIDDEN, $controller->signDirect()->getStatus());
	}

	public function testPreservesSignedStatusConflictWithoutLeakingOtherFailures(): void {
		$this->signing->method('sign')->willThrowException(
			new LibresignException('The signing request has not completed.', Http::STATUS_CONFLICT),
		);
		$response = $this->controller->signDirect(name: 'contract.pdf', method: 'clickToSign');
		self::assertSame(Http::STATUS_CONFLICT, $response->getStatus());
		self::assertSame('The signing request has not completed.', $response->getData()['message']);
	}

	public function testUnexpectedErrorsAreLoggedButNotReturnedToClient(): void {
		$this->signing->method('sign')->willThrowException(new \RuntimeException('sensitive detail'));
		$this->logger->expects(self::once())->method('error');
		$response = $this->controller->signDirect(name: 'contract.pdf', method: 'clickToSign');
		self::assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		self::assertStringNotContainsString('sensitive', $response->getData()['message']);
	}
}
