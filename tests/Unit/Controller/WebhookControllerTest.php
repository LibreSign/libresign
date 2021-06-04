<?php

namespace OCA\Libresign\Tests\Unit\Controller;

/**
 * @group DB
 */
final class WebhookControllerTest extends \OCA\Libresign\Tests\Unit\ApiTestCase {
	public function testIndexSuccess() {
		$l10n = $this->createMock(\OCP\IL10N::class);
		$userSession = $this->createMock(\OCP\IUserSession::class);
		$request = $this->createMock(\OCP\IRequest::class);
		$webhook = $this->createMock(\OCA\Libresign\Service\WebhookService::class);
		$mail = $this->createMock(\OCA\Libresign\Service\MailService::class);

		$controller = new \OCA\Libresign\Controller\WebhookController(
			$request,
			$userSession,
			$l10n,
			$webhook,
			$mail
		);

		$l10n
			->method('t')
			->will($this->returnArgument(0));

		$actual = $controller->register([], [], '');
		$expected = new \OCP\AppFramework\Http\JSONResponse([
			'message' => 'Success',
			'data' => null
		], \OCP\AppFramework\Http::STATUS_OK);
		$this->assertEquals($expected, $actual);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testMeWithoutAuthenticatedUser() {
		$this->request
			->withPath('/webhook/me')
			->assertResponseCode(404);

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testMeWithAuthenticatedUser() {
		$user = $this->createUser('username', 'password');
		$this->request
			->withPath('/webhook/me')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password')
			]);

		$this->assertRequest();
	}
}
