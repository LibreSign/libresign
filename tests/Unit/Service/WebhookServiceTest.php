<?php

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\MailService;
use OCA\Libresign\Service\WebhookService;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
final class WebhookServiceTest extends TestCase {
	/** @var IConfig */
	private $config;
	/** @var IGroupManager */
	private $groupManager;
	/** @var IL10N */
	private $l10n;
	/** @var WebhookService */
	private $service;
	/** @var FileMapper */
	private $file;
	/** @var FileUserMapper */
	private $fileUser;
	/** @var IUser */
	private $user;
	/** @var IClientService */
	private $client;
	/** @var IUserManager */
	private $userManager;

	public function setUp(): void {
		$this->config = $this->createMock(IConfig::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));
		$this->file = $this->createMock(FileMapper::class);
		$this->fileUser = $this->createMock(FileUserMapper::class);
		$this->user = $this->createMock(IUser::class);
		$this->client = $this->createMock(IClientService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->mail = $this->createMock(MailService::class);
		$this->folder = $this->createMock(FolderService::class);
		$this->service = new WebhookService(
			$this->config,
			$this->groupManager,
			$this->l10n,
			$this->file,
			$this->fileUser,
			$this->folder,
			$this->client,
			$this->userManager,
			$this->mail
		);
	}

	public function testEmptyFile() {
		$this->expectExceptionMessage('Empty file');

		$this->service->validate([
			'name' => 'test',
			'userManager' => $this->user
		]);
	}

	public function testValidateInvalidBase64File() {
		$this->expectExceptionMessage('Invalid base64 file');

		$this->service->validate([
			'file' => ['base64' => 'qwert'],
			'name' => 'test',
			'userManager' => $this->user
		]);
	}

	public function testValidateFileUrl() {
		$this->expectExceptionMessage('Invalid URL file');

		$this->service->validate([
			'file' => ['url' => 'qwert'],
			'name' => 'test',
			'userManager' => $this->user
		]);
	}

	public function testValidateNameIsMandatory() {
		$this->expectExceptionMessage('Name is mandatory');

		$this->service->validate([
			'file' => ['url' => 'qwert'],
			'userManager' => $this->user
		]);
	}

	public function testValidateInvalidName() {
		$this->expectExceptionMessage('The name can only contain "a-z", "A-Z", "0-9" and "-_" chars.');

		$this->service->validate([
			'file' => ['url' => 'qwert'],
			'userManager' => $this->user,
			'name' => '@#$%*('
		]);
	}

	public function testValidateEmptyUserCollection() {
		$this->expectExceptionMessage('Empty users list');

		$response = $this->createMock(IResponse::class);
		$response
			->method('getHeaders')
			->will($this->returnValue(['Content-Type' => ['application/pdf']]));
		$client = $this->createMock(IClient::class);
		$client
			->method('get')
			->will($this->returnValue($response));
		$this->client
			->method('newClient')
			->will($this->returnValue($client));

		$this->service->validate([
			'file' => ['url' => 'http://test.coop'],
			'name' => 'test',
			'userManager' => $this->user
		]);
	}

	public function testValidateEmptyUsersCollection() {
		$this->expectExceptionMessage('Empty users list');

		$this->service->validate([
			'file' => ['base64' => 'dGVzdA=='],
			'name' => 'test',
			'userManager' => $this->user
		]);
	}

	public function testValidateUserCollectionNotArray() {
		$this->expectExceptionMessage('User list needs to be an array');

		$this->service->validate([
			'file' => ['base64' => 'dGVzdA=='],
			'name' => 'test',
			'users' => 'asdfg',
			'userManager' => $this->user
		]);
	}

	public function testValidateUserEmptyCollection() {
		$this->expectExceptionMessage('Empty users list');

		$this->service->validate([
			'file' => ['base64' => 'dGVzdA=='],
			'name' => 'test',
			'users' => null,
			'userManager' => $this->user
		]);
	}

	public function testValidateUserInvalidCollection() {
		$this->expectExceptionMessage('User data needs to be an array: user %s');

		$this->service->validate([
			'file' => ['base64' => 'dGVzdA=='],
			'name' => 'test',
			'users' => [
				''
			],
			'userManager' => $this->user
		]);
	}

	public function testValidateUserEmpty() {
		$this->expectExceptionMessage('User data needs to be an array with values: user %s');

		$this->service->validate([
			'file' => ['base64' => 'dGVzdA=='],
			'name' => 'test',
			'users' => [
				[]
			],
			'userManager' => $this->user
		]);
	}

	public function testValidateUserWithoutEmail() {
		$this->expectExceptionMessage('User need to be email: user %s');

		$this->service->validate([
			'file' => ['base64' => 'dGVzdA=='],
			'name' => 'test',
			'users' => [
				[
					''
				]
			],
			'userManager' => $this->user
		]);
	}

	public function testValidateUserWithInvalidEmail() {
		$this->expectExceptionMessage('Invalid email: user %s');

		$this->service->validate([
			'file' => ['base64' => 'dGVzdA=='],
			'name' => 'test',
			'users' => [
				[
					'email' => 'invalid'
				]
			],
			'userManager' => $this->user
		]);
	}

	public function testValidateUserDuplicatedEmail() {
		$this->expectExceptionMessage('Remove duplicated users, email need to be unique');

		$this->service->validate([
			'file' => ['base64' => 'dGVzdA=='],
			'name' => 'test',
			'users' => [
				[
					'email' => 'jhondoe@test.coop'
				],
				[
					'email' => 'jhondoe@test.coop'
				]
			],
			'userManager' => $this->user
		]);
	}

	public function testIndexWithoutPermission() {
		$this->expectExceptionMessage('Insufficient permissions to use API');
		$this->config
			->expects($this->once())
			->method('getAppValue')
			->willReturn('["admin"]');
		$this->groupManager
			->expects($this->once())
			->method('getUserGroupIds')
			->willReturn([]);

		$this->service->validate([
			'file' => ['base64' => 'dGVzdA=='],
			'name' => 'test',
			'users' => [
				[
					'email' => 'jhondoe@test.coop'
				]
			],
			'userManager' => $this->user
		]);
	}
}
