<?php

namespace OCA\Libresign\Tests\Unit;

/**
 * @group DB
 */
final class PageControllerTest extends TestCase {
	public function testIndexScriptsAndTemplate() {
		$controller = \OC::$server->get(\OCA\Libresign\Controller\PageController::class);
		$response = $controller->index();
		$this->assertEquals('main', $response->getTemplateName());
		$this->assertContains('libresign/js/libresign-main', \OC_Util::$scripts);
	}

	public function testIndexInitialState() {
		$controller = \OC::$server->get(\OCA\Libresign\Controller\PageController::class);
		$controller->index();
		$initialState = \OC::$server->get(\OC\InitialStateService::class);
		$initialStates = $initialState->getInitialStates();
		$this->assertArrayHasKey('libresign-config', $initialStates);
	}

	public function testSignScriptsAndTemplate() {
		$controller = \OC::$server->get(\OCA\Libresign\Controller\PageController::class);
		$response = $controller->sign('uuid');
		$this->assertEquals('external', $response->getTemplateName());
		$this->assertContains('libresign/js/libresign-external', \OC_Util::$scripts);
	}

	public function testSignPolices() {
		$controller = \OC::$server->get(\OCA\Libresign\Controller\PageController::class);
		$response = $controller->sign('uuid');
		$polices = $response->getContentSecurityPolicy();
		$this->assertCount(1, $polices->getAllowedFrameDomains());
		$this->assertContains("'self'", $polices->getAllowedFrameDomains());
	}

	public function testSignInitialState() {
		$controller = \OC::$server->get(\OCA\Libresign\Controller\PageController::class);
		$controller->sign('uuid');
		$initialState = \OC::$server->get(\OC\InitialStateService::class);
		$initialStates = $initialState->getInitialStates();
		$this->assertArrayHasKey('libresign-config', $initialStates);
	}

	public function testGetPdfNotFound() {
		$controller = \OC::$server->get(\OCA\Libresign\Controller\PageController::class);
		$response = $controller->getPdf('uuid');
		$this->assertInstanceOf(\OCP\AppFramework\Http\DataResponse::class, $response);
		$this->assertEquals(404, $response->getStatus());
	}
}
