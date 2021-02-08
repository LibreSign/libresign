<?php

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Service\WebhookService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
final class WebhookServiceTest extends TestCase {
	/** @var IL10N */
	private $l10n;
	/** @var WebhookService */
	private $service;

	public function setUp(): void {
		$this->l10n = $this
			->createMock(IL10N::class);
		$this->service = new WebhookService(
			$this->l10n
		);
	}

	public function testEmptyFile() {
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$actual = $this->service->validate([]);
		$expected = new DataResponse(
			[
				'message' => 'Empty file',
			],
			Http::STATUS_UNPROCESSABLE_ENTITY
		);
		$this->assertEquals($expected, $actual);
	}

	public function testValidateeFileBase64() {
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$actual = $this->service->validate([
			'file_base64' => 'qwert'
		]);
		$expected = new DataResponse(
			[
				'message' => 'Invalid base64 file',
			],
			Http::STATUS_UNPROCESSABLE_ENTITY
		);
		$this->assertEquals($expected, $actual);
	}

	public function testValidateeFileUrl() {
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$actual = $this->service->validate([
			'file_url' => 'qwert'
		]);
		$expected = new DataResponse(
			[
				'message' => 'Invalid url file',
			],
			Http::STATUS_UNPROCESSABLE_ENTITY
		);
		$this->assertEquals($expected, $actual);
	}

	public function testValidateeEmptyUserCollection() {
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$actual = $this->service->validate([
			'file_url' => 'http://test.coop'
		]);
		$expected = new DataResponse(
			[
				'message' => 'Empty users collection',
			],
			Http::STATUS_UNPROCESSABLE_ENTITY
		);
		$this->assertEquals($expected, $actual);
	}

	public function testValidateEmptyUsersCollection() {
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$actual = $this->service->validate([
			'file_url' => 'http://test.coop'
		]);
		$expected = new DataResponse(
			[
				'message' => 'Empty users collection',
			],
			Http::STATUS_UNPROCESSABLE_ENTITY
		);
		$this->assertEquals($expected, $actual);
	}

	public function testValidateUserCollectionNotArray() {
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$actual = $this->service->validate([
			'file_url' => 'http://test.coop',
			'users' => 'asdfg'
		]);
		$expected = new DataResponse(
			[
				'message' => 'User collection need is an array',
			],
			Http::STATUS_UNPROCESSABLE_ENTITY
		);
		$this->assertEquals($expected, $actual);
	}

	public function testValidateInvalidUserEmail() {
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$actual = $this->service->validate([
			'file_url' => 'http://test.coop',
			'users' => [
				[
					'name' => 'Jhon Doe',
					'email' => 'jhon@test.coop'
				]
			]
		]);
		$expected = new DataResponse(
			[
				'message' => 'User email is necessary: Index 0',
			],
			Http::STATUS_UNPROCESSABLE_ENTITY
		);
		$this->assertEquals($expected, $actual);
	}
}
