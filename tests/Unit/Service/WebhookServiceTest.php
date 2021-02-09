<?php

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\WebhookService;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
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
	/** @var IRootFolder */
	private $rootFolder;
	/** @var IUser */
	private $user;
	/** @var FolderService */
	private $folderService;

	public function setUp(): void {
		$this->config = $this->createMock(IConfig::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->file = $this->createMock(FileMapper::class);
		$this->fileUser = $this->createMock(FileUserMapper::class);
		$this->user = $this->createMock(IUser::class);
		$this->folderService = $this->createMock(FolderService::class);
		$this->service = new WebhookService(
			$this->config,
			$this->groupManager,
			$this->l10n,
			$this->rootFolder,
			$this->file,
			$this->fileUser,
			$this->folderService
		);
	}

	public function testEmptyFile() {
		$this->expectExceptionMessage('Empty file');

		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$this->service->validate([
			'name' => 'test',
			'userManager' => $this->user
		]);
	}

	public function testValidateInvalidBase64File() {
		$this->expectExceptionMessage('Invalid base64 file');

		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$this->service->validate([
			'file' => ['base64' => 'qwert'],
			'name' => 'test',
			'userManager' => $this->user
		]);
	}

	public function testValidateFileUrl() {
		$this->expectExceptionMessage('Invalid url file');

		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$this->service->validate([
			'file' => ['url' => 'qwert'],
			'name' => 'test',
			'userManager' => $this->user
		]);
	}

	public function testValidateNameIsMandatory() {
		$this->expectExceptionMessage('Name is mandatory');

		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$this->service->validate([
			'file' => ['url' => 'qwert'],
			'userManager' => $this->user
		]);
	}

	public function testValidateInvalidName() {
		$this->expectExceptionMessage('The name can only contain "a-z", "A-Z", "0-9" and "-_." chars.');

		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$this->service->validate([
			'file' => ['url' => 'qwert'],
			'userManager' => $this->user,
			'name' => '@#$%*('
		]);
	}

	public function testValidateEmptyUserCollection() {
		$this->expectExceptionMessage('Empty users collection');

		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$this->service->validate([
			'file' => ['url' => 'http://test.coop'],
			'name' => 'test',
			'userManager' => $this->user
		]);
	}

	public function testValidateEmptyUsersCollection() {
		$this->expectExceptionMessage('Empty users collection');

		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$this->service->validate([
			'file' => ['url' => 'http://test.coop'],
			'name' => 'test',
			'userManager' => $this->user
		]);
	}

	public function testValidateUserCollectionNotArray() {
		$this->expectExceptionMessage('User collection need is an array');

		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$this->service->validate([
			'file' => ['url' => 'http://test.coop'],
			'name' => 'test',
			'users' => 'asdfg',
			'userManager' => $this->user
		]);
	}

	public function testValidateUserEmptyCollection() {
		$this->expectExceptionMessage('Empty users collection');

		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$this->service->validate([
			'file' => ['url' => 'http://test.coop'],
			'name' => 'test',
			'users' => null,
			'userManager' => $this->user
		]);
	}

	public function testValidateUserInvalidCollection() {
		$this->expectExceptionMessage('User collection need is an array: user 0');

		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$this->service->validate([
			'file' => ['url' => 'http://test.coop'],
			'name' => 'test',
			'users' => [
				''
			],
			'userManager' => $this->user
		]);
	}

	public function testValidateUserEmpty() {
		$this->expectExceptionMessage('User collection need is an array with values: user 0');

		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$this->service->validate([
			'file' => ['url' => 'http://test.coop'],
			'name' => 'test',
			'users' => [
				[]
			],
			'userManager' => $this->user
		]);
	}

	public function testValidateUserWithoutEmail() {
		$this->expectExceptionMessage('User need an email: user 0');

		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$this->service->validate([
			'file' => ['url' => 'http://test.coop'],
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
		$this->expectExceptionMessage('Invalid email: user 0');

		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$this->service->validate([
			'file' => ['url' => 'http://test.coop'],
			'name' => 'test',
			'users' => [
				[
					'email' => 'invalid'
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

		$this->l10n
			->method('t')
			->will($this->returnArgument(0));

		$this->service->validate([
			'file' => ['url' => 'http://test.coop'],
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
