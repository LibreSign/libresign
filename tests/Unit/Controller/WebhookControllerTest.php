<?php

namespace OCA\Libresign\Tests\Unit\Controller;

use OCA\Libresign\Controller\WebhookController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class WebhookControllerTest extends TestCase {
	/** @var WebhookController */
	private $controller;
	/** @var IConfig */
	private $config;
	/** @var IGroupManager */
	private $groupManager;
	/** @var IL10N */
	private $l;
	/** @var IUserSession */
	private $userSession;
	/** @var IRequest */
	private $request;

	public function setUp(): void {
		parent::setUp();
		$this->config = $this->createMock(IConfig::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->l = $this->createMock(IL10N::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->request = $this->createMock(IRequest::class);

		$this->controller = new WebhookController(
			$this->request,
			$this->config,
			$this->groupManager,
			$this->userSession,
			$this->l
		);
	}

	public function testIndexWithoutPermission() {
		$this->config
			->expects($this->once())
			->method('getAppValue')
			->willReturn('["admin"]');

		$user = $this->createMock(IUser::class);
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$this->l
			->expects($this->once())
			->method('t')
			->willReturn('Insufficient permissions to use API');

		$actual = $this->controller->register();
		$expected = new JSONResponse([
			'message' => 'Insufficient permissions to use API',
		], Http::STATUS_FORBIDDEN);

		$this->assertEquals($expected, $actual);
	}

	public function testIndexSuccess() {
		$this->l
			->expects($this->once())
			->method('t')
			->willReturn('Success');

		$actual = $this->controller->register();
		$expected = new JSONResponse([
			'message' => 'Success',
		], Http::STATUS_OK);
		$this->assertEquals($expected, $actual);
	}

}