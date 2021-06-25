<?php

namespace OCA\Libresign\Tests\Unit;

/**
 * @group DB
 */
final class PageControllerTest extends TestCase {
	use UserTrait;

	/**
	 * @runInSeparateProcess
	 */
	public function testIndexScriptsAndTemplate() {
		$controller = \OC::$server->get(\OCA\Libresign\Controller\PageController::class);
		$response = $controller->index();
		$this->assertEquals('main', $response->getTemplateName());
		$this->assertContains('libresign/js/libresign-main', \OC_Util::$scripts);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testIndexInitialState() {
		$controller = \OC::$server->get(\OCA\Libresign\Controller\PageController::class);
		$controller->index();
		$initialState = \OC::$server->get(\OC\InitialStateService::class);
		$initialStates = $initialState->getInitialStates();
		$this->assertArrayHasKey('libresign-config', $initialStates);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSignScriptsAndTemplate() {
		$controller = \OC::$server->get(\OCA\Libresign\Controller\PageController::class);
		$response = $controller->sign('uuid');
		$this->assertEquals('external', $response->getTemplateName());
		$this->assertContains('libresign/js/libresign-external', \OC_Util::$scripts);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSignPolices() {
		$controller = \OC::$server->get(\OCA\Libresign\Controller\PageController::class);
		$response = $controller->sign('uuid');
		$polices = $response->getContentSecurityPolicy();
		$this->assertCount(1, $polices->getAllowedFrameDomains());
		$this->assertContains("'self'", $polices->getAllowedFrameDomains());
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSignInitialState() {
		$controller = \OC::$server->get(\OCA\Libresign\Controller\PageController::class);
		$controller->sign('uuid');
		$initialState = \OC::$server->get(\OC\InitialStateService::class);
		$initialStates = $initialState->getInitialStates();
		$this->assertArrayHasKey('libresign-config', $initialStates);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testGetPdfNotFound() {
		$controller = \OC::$server->get(\OCA\Libresign\Controller\PageController::class);
		$response = $controller->getPdf('uuid');
		$this->assertInstanceOf(\OCP\AppFramework\Http\DataResponse::class, $response);
		$this->assertEquals(404, $response->getStatus());
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testGetPdfHeader() {
		$user = $this->createUser('username', 'password');

		$user->setEMailAddress('person@test.coop');
		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			'name' => 'test',
			'users' => [
				[
					'email' => 'person@test.coop'
				]
			],
			'userManager' => $user
		]);

		$controller = \OC::$server->get(\OCA\Libresign\Controller\PageController::class);
		$response = $controller->getPdf($file['uuid']);
		$headers = $response->getHeaders();
		$this->assertArrayHasKey('Content-Type', $headers);
		$this->assertEquals('application/pdf', $headers['Content-Type']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testGetPdfStatusCode() {
		$user = $this->createUser('username', 'password');

		$user->setEMailAddress('person@test.coop');
		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			'name' => 'test',
			'users' => [
				[
					'email' => 'person@test.coop'
				]
			],
			'userManager' => $user
		]);

		$controller = \OC::$server->get(\OCA\Libresign\Controller\PageController::class);
		$response = $controller->getPdf($file['uuid']);
		$this->assertEquals(200, $response->getStatus());
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testGetPdfUserNotFound() {
		$controller = \OC::$server->get(\OCA\Libresign\Controller\PageController::class);
		$response = $controller->getPdfUser('uuid');
		$this->assertInstanceOf(\OCP\AppFramework\Http\DataResponse::class, $response);
		$this->assertEquals(404, $response->getStatus());
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testGetPdfUserHeaderAndStatusCode() {
		$user = $this->createUser('username', 'password');

		$user->setEMailAddress('person@test.coop');
		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			'name' => 'test',
			'users' => [
				[
					'email' => 'person@test.coop'
				]
			],
			'userManager' => $user
		]);

		$controller = \OC::$server->get(\OCA\Libresign\Controller\PageController::class);

		$session = \OC::$server->get(\OCP\ISession::class);
		$session->set('user_id', 'username');

		$response = $controller->getPdfUser($file['users'][0]->getUuid());
		$headers = $response->getHeaders();
		$this->assertArrayHasKey('Content-Type', $headers);
		$this->assertEquals('application/pdf', $headers['Content-Type']);
		$this->assertEquals(200, $response->getStatus());
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testValidationScriptsAndTemplate() {
		$controller = \OC::$server->get(\OCA\Libresign\Controller\PageController::class);
		$response = $controller->validation();
		$this->assertEquals('validation', $response->getTemplateName());
		$this->assertContains('libresign/js/libresign-validation', \OC_Util::$scripts);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testValidationInitialState() {
		$controller = \OC::$server->get(\OCA\Libresign\Controller\PageController::class);
		$controller->validation();
		$initialState = \OC::$server->get(\OC\InitialStateService::class);
		$initialStates = $initialState->getInitialStates();
		$this->assertArrayHasKey('libresign-config', $initialStates);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testResetPasswordScriptsAndTemplate() {
		$controller = \OC::$server->get(\OCA\Libresign\Controller\PageController::class);
		$response = $controller->resetPassword();
		$this->assertEquals('reset_password', $response->getTemplateName());
		$this->assertContains('libresign/js/libresign-main', \OC_Util::$scripts);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testResetPasswordInitialState() {
		$controller = \OC::$server->get(\OCA\Libresign\Controller\PageController::class);
		$controller->validation();
		$initialState = \OC::$server->get(\OC\InitialStateService::class);
		$initialStates = $initialState->getInitialStates();
		$this->assertArrayHasKey('libresign-config', $initialStates);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testValidationFileScriptsAndTemplate() {
		$controller = \OC::$server->get(\OCA\Libresign\Controller\PageController::class);
		$response = $controller->validationFile('uuid');
		$this->assertEquals('validation', $response->getTemplateName());
		$this->assertContains('libresign/js/libresign-validation', \OC_Util::$scripts);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testValidationFileInitialState() {
		$controller = \OC::$server->get(\OCA\Libresign\Controller\PageController::class);
		$controller->validationFile('uuid');
		$initialState = \OC::$server->get(\OC\InitialStateService::class);
		$initialStates = $initialState->getInitialStates();
		$this->assertArrayHasKey('libresign-config', $initialStates);
	}
}
