<?php

namespace OCA\Libresign\Tests\Unit;

/**
 * @group DB
 */
final class PageControllerTest extends TestCase {
	/**
	 * @runInSeparateProcess
	 */
	public function testIndexResponse() {
		$controller = \OC::$server->get(\OCA\Libresign\Controller\PageController::class);
		$actual = $controller->index();
		$this->assertEquals('main', $actual->getTemplateName());
	}
}
