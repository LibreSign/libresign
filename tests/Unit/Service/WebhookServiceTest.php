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

	public function testValidateInvalidBase64File() {
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$actual = $this->service->validate([
			'file' =>['base64' => 'qwert']
		]);
		$expected = new DataResponse(
			[
				'message' => 'Invalid base64 file',
			],
			Http::STATUS_UNPROCESSABLE_ENTITY
		);
		$this->assertEquals($expected, $actual);
	}

	public function testValidateFileUrl() {
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$actual = $this->service->validate([
			'file' => ['url' => 'qwert']
		]);
		$expected = new DataResponse(
			[
				'message' => 'Invalid url file',
			],
			Http::STATUS_UNPROCESSABLE_ENTITY
		);
		$this->assertEquals($expected, $actual);
	}

	public function testValidateEmptyUserCollection() {
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$actual = $this->service->validate([
			'file' => ['url' => 'http://test.coop']
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
			'file' => ['url' => 'http://test.coop']
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
			'file' => ['url' => 'http://test.coop'],
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

	public function testValidateUserEmptyCollection() {
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$actual = $this->service->validate([
			'file' => ['url' => 'http://test.coop'],
			'users' => null
		]);
		$expected = new DataResponse(
			[
				'message' => 'Empty users collection',
			],
			Http::STATUS_UNPROCESSABLE_ENTITY
		);
		$this->assertEquals($expected, $actual);
	}

	public function testValidateUserInvalidCollection() {
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$actual = $this->service->validate([
			'file' => ['url' => 'http://test.coop'],
			'users' => [
				''
			]
		]);
		$expected = new DataResponse(
			[
				'message' => 'User collection need is an array: user 0',
			],
			Http::STATUS_UNPROCESSABLE_ENTITY
		);
		$this->assertEquals($expected, $actual);
	}

	public function testValidateUserEmpty() {
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$actual = $this->service->validate([
			'file' => ['url' => 'http://test.coop'],
			'users' => [
				[]
			]
		]);
		$expected = new DataResponse(
			[
				'message' => 'User collection need is an array with values: user 0',
			],
			Http::STATUS_UNPROCESSABLE_ENTITY
		);
		$this->assertEquals($expected, $actual);
	}

	public function testValidateUserWithoutEmail() {
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$actual = $this->service->validate([
			'file' => ['url' => 'http://test.coop'],
			'users' => [
				[
					''
				]
			]
		]);
		$expected = new DataResponse(
			[
				'message' => 'User need an email: user 0',
			],
			Http::STATUS_UNPROCESSABLE_ENTITY
		);
		$this->assertEquals($expected, $actual);
	}

	public function testValidateUserWithInvalidEmail() {
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$actual = $this->service->validate([
			'file' => ['url' => 'http://test.coop'],
			'users' => [
				[
					'email' => 'invalid'
				]
			]
		]);
		$expected = new DataResponse(
			[
				'message' => 'Invalid email: user 0',
			],
			Http::STATUS_UNPROCESSABLE_ENTITY
		);
		$this->assertEquals($expected, $actual);
	}
}
