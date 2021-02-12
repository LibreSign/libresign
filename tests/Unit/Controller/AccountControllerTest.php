<?php

namespace OCA\Libresign\Tests\Unit\Controller;

use OCA\Libresign\Controller\AccountController;
use OCA\Libresign\Service\AccountService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

final class AccountControllerTest extends TestCase {
	/** @var IRequest */
	private $request;
	/** @var IL10N */
	private $l10n;
	/** @var AccountService */
	private $account;

	public function setUp(): void {
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->account = $this->createMock(AccountService::class);
		$this->controller = new AccountController(
			$this->request,
			$this->l10n,
			$this->account
		);
	}

	public function testCreateSuccess() {
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$actual = $this->controller->createToSign('uuid', 'email');
		$expected = new JSONResponse([
			'message' => 'Success',
			'data' => null
		], Http::STATUS_OK);
		$this->assertEquals($expected, $actual);
	}
}
