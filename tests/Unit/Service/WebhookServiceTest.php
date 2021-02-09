<?php

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Service\WebhookService;
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
		$this->expectExceptionMessage('Empty file');

		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$this->service->validate([]);
	}

	public function testValidateInvalidBase64File() {
		$this->expectExceptionMessage('Invalid base64 file');

		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$this->service->validate([
			'file' => ['base64' => 'qwert']
		]);
	}

	public function testValidateFileUrl() {
		$this->expectExceptionMessage('Invalid url file');

		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$this->service->validate([
			'file' => ['url' => 'qwert']
		]);
	}

	public function testValidateEmptyUserCollection() {
		$this->expectExceptionMessage('Empty users collection');

		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$this->service->validate([
			'file' => ['url' => 'http://test.coop']
		]);
	}

	public function testValidateEmptyUsersCollection() {
		$this->expectExceptionMessage('Empty users collection');

		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$this->service->validate([
			'file' => ['url' => 'http://test.coop']
		]);
	}

	public function testValidateUserCollectionNotArray() {
		$this->expectExceptionMessage('User collection need is an array');

		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$this->service->validate([
			'file' => ['url' => 'http://test.coop'],
			'users' => 'asdfg'
		]);
	}

	public function testValidateUserEmptyCollection() {
		$this->expectExceptionMessage('Empty users collection');

		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$this->service->validate([
			'file' => ['url' => 'http://test.coop'],
			'users' => null
		]);
	}

	public function testValidateUserInvalidCollection() {
		$this->expectExceptionMessage('User collection need is an array: user 0');

		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$this->service->validate([
			'file' => ['url' => 'http://test.coop'],
			'users' => [
				''
			]
		]);
	}

	public function testValidateUserEmpty() {
		$this->expectExceptionMessage('User collection need is an array with values: user 0');

		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$this->service->validate([
			'file' => ['url' => 'http://test.coop'],
			'users' => [
				[]
			]
		]);
	}

	public function testValidateUserWithoutEmail() {
		$this->expectExceptionMessage('User need an email: user 0');

		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$this->service->validate([
			'file' => ['url' => 'http://test.coop'],
			'users' => [
				[
					''
				]
			]
		]);
	}

	public function testValidateUserWithInvalidEmail() {
		$this->expectExceptionMessage('Invalid email: user 0');

		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$this->service->validate([
			'file' => ['url' => 'http://test.coop'],
			'users' => [
				[
					'email' => 'invalid'
				]
			]
		]);
	}
}
