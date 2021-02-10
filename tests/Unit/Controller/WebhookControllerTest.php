<?php

namespace OCA\Libresign\Tests\Unit\Controller;

use OCA\Libresign\Controller\WebhookController;
use OCA\Libresign\Service\WebhookService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

final class WebhookControllerTest extends TestCase {
	/** @var WebhookController */
	private $controller;
	/** @var IL10N */
	private $l10n;
	/** @var IUserSession */
	private $userSession;
	/** @var IRequest */
	private $request;

	public function setUp(): void {
		parent::setUp();
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->request = $this->createMock(IRequest::class);
		$this->webhook = $this->createMock(WebhookService::class);

		$this->controller = new WebhookController(
			$this->request,
			$this->userSession,
			$this->l10n,
			$this->webhook
		);
	}

	public function testIndexSuccess() {
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$actual = $this->controller->register([], [], '');
		$expected = new JSONResponse([
			'message' => 'Success',
			'data' => null
		], Http::STATUS_OK);
		$this->assertEquals($expected, $actual);
	}
}
