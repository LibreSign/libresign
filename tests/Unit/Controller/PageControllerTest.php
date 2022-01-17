<?php

namespace OCA\Libresign\Tests\Unit;

use OCA\Libresign\Controller\PageController;
use OCA\Libresign\Service\AccountService;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * @group DB
 */
final class PageControllerTest extends TestCase {
	/** @var IRequest|MockObject */
	private $request;
	/** @var IUserSession|MockObject */
	private $userSession;
	/** @var IInitialState|MockObject */
	private $initialState;
	/** @var AccountService|MockObject */
	private $accountService;
	/** @var PageController */
	private $controller;

	public function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->initialState = $this->createMock(IInitialState::class);
		$this->accountService = $this->createMock(AccountService::class);
		$this->controller = new PageController(
			$this->request,
			$this->userSession,
			$this->initialState,
			$this->accountService
		);
	}

	public function testIndexScriptsAndTemplate() {
		$response = $this->controller->index();
		$this->assertEquals('main', $response->getTemplateName());
		$scripts = $this->invokePrivate(\OCP\Util::class, 'scripts');
		$this->assertContains('libresign/js/libresign-main', $scripts['libresign']);
	}

	public function testIndexReturnStatus() {
		$response = $this->controller->index();
		$this->assertEquals(200, $response->getStatus());
	}

	public function testSignScriptsAndTemplate() {
		$response = $this->controller->sign('uuid');
		$this->assertEquals('external', $response->getTemplateName());
		$scripts = $this->invokePrivate(\OCP\Util::class, 'scripts');
		$this->assertContains('libresign/js/libresign-external', $scripts['libresign']);
	}

	public function testSignPolices() {
		$response = $this->controller->sign('uuid');
		$polices = $response->getContentSecurityPolicy();
		$this->assertCount(1, $polices->getAllowedFrameDomains());
		$this->assertContains("'self'", $polices->getAllowedFrameDomains());
	}

	public function testSignReturnStatus() {
		$response = $this->controller->sign('uuid');
		$this->assertEquals(200, $response->getStatus());
	}

	public function testGetPdfNotFound() {
		$file = $this->createMock(\OCP\Files\File::class);
		$this->accountService
			->method('getPdfByUuid')
			->willThrowException($this->createMock(\Exception::class));

		$response = $this->controller->getPdf('uuid');
		$this->assertInstanceOf(\OCP\AppFramework\Http\DataResponse::class, $response);
		$this->assertEquals(404, $response->getStatus());
	}

	public function testGetPdfHeader() {
		$file = $this->createMock(\OCP\Files\File::class);
		$this->accountService
			->method('getPdfByUuid')
			->willReturn($file);

		$response = $this->controller->getPdf('sfsdf');
		$headers = $response->getHeaders();
		$this->assertArrayHasKey('Content-Type', $headers);
		$this->assertEquals('application/pdf', $headers['Content-Type']);
	}

	public function testGetPdfStatusCode() {
		$file = $this->createMock(\OCP\Files\File::class);
		$this->accountService
			->method('getPdfByUuid')
			->willReturn($file);

		$response = $this->controller->getPdf('uuid');
		$this->assertEquals(200, $response->getStatus());
	}

	public function testGetPdfUserNotFound() {
		$response = $this->controller->getPdfUser('uuid');
		$this->assertInstanceOf(\OCP\AppFramework\Http\DataResponse::class, $response);
		$this->assertEquals(404, $response->getStatus());
	}

	public function testGetPdfUserHeaderAndStatusCode() {
		$file = $this->createMock(\OCP\Files\File::class);
		$this->accountService
			->method('getPdfByUuid')
			->willReturn($file);
		$this->accountService
			->method('getConfig')
			->willReturn(['sign' => ['pdf' => ['file' => $file]]]);

		$response = $this->controller->getPdfUser('username');
		$headers = $response->getHeaders();
		$this->assertArrayHasKey('Content-Type', $headers);
		$this->assertEquals('application/pdf', $headers['Content-Type']);
		$this->assertEquals(200, $response->getStatus());
	}

	public function testValidationScriptsAndTemplate() {
		$response = $this->controller->validation();
		$this->assertEquals('validation', $response->getTemplateName());
		$scripts = $this->invokePrivate(\OCP\Util::class, 'scripts');
		$this->assertContains('libresign/js/libresign-validation', $scripts['libresign']);
	}

	public function testValidationReturnStatus() {
		$response = $this->controller->validation();
		$this->assertEquals(200, $response->getStatus());
	}

	public function testResetPasswordScriptsAndTemplate() {
		$response = $this->controller->resetPassword();
		$this->assertEquals('reset_password', $response->getTemplateName());
		$scripts = $this->invokePrivate(\OCP\Util::class, 'scripts');
		$this->assertContains('libresign/js/libresign-main', $scripts['libresign']);
	}

	public function testValidationFileScriptsAndTemplate() {
		$response = $this->controller->validationFile('uuid');
		$this->assertEquals('validation', $response->getTemplateName());
		$scripts = $this->invokePrivate(\OCP\Util::class, 'scripts');
		$this->assertContains('libresign/js/libresign-validation', $scripts['libresign']);
	}

	public function testValidationFileReturnStatus() {
		$response = $this->controller->validationFile('uuid');
		$this->assertEquals(200, $response->getStatus());
	}
}
