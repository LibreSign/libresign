<?php

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\MailService;
use OCA\Libresign\Service\WebhookService;
use OCP\Files\Folder;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @internal
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
	/** @var FolderService */
	private $folder;
	/** @var LoggerInterface */
	private $logger;

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
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->service = new WebhookService(
			$this->config,
			$this->groupManager,
			$this->l10n,
			$this->file,
			$this->fileUser,
			$this->folder,
			$this->client,
			$this->userManager,
			$this->mail,
			$this->logger
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
		$folder = $this->createMock(Folder::class);
		$folder
			->expects($this->once())
			->method('nodeExists')
			->willReturn(false);
		$folder
			->expects($this->once())
			->method('newFolder')
			->willReturn($folder);
		$this->folder
			->expects($this->once())
			->method('getFolder')
			->willReturn($folder);
		$this->service->save([
			'file' => ['url' => 'qwert'],
			'name' => 'test',
			'userManager' => $this->user
		]);
	}

	public function testValidateFileWithoutAllNecessaryData() {
		$this->expectExceptionMessage('Inform URL or base64 or fileId to sign');
		$this->service->validateFile([
			'file' => ['invalid'],
			'name' => 'test'
		]);
	}

	public function testValidateFileWithInvalidFileId() {
		$this->expectExceptionMessage('Invalid fileId');
		$this->service->validateFile([
			'file' => ['fileId' => 'invalid'],
			'name' => 'test'
		]);
	}

	public function testValidateFileWhenFileIdDoesNotExist() {
		$this->expectExceptionMessage('Invalid fileId');
		$this->service->validateFile([
			'file' => ['fileId' => 123],
			'name' => 'test'
		]);
	}

	public function testValidateFileByFileIdWhenAlreadyAskedToSignThisDocument() {
		$this->file->method('getByFileId')->will($this->returnValue('exists'));
		$this->expectExceptionMessage('Already asked to sign this document');
		$this->service->validateFileByFileId(1);
	}

	public function testValidateFileByFileIdWhenFileIdNotExists() {
		$this->file->method('getByFileId')->will($this->returnCallback(function () {
			throw new \Exception('not found');
		}));
		$this->expectExceptionMessage('Invalid fileId');
		$this->service->validateFileByFileId(1);
	}

	public function testValidateFileByFileIdWhenFileNotExists() {
		$this->file->method('getByFileId')->will($this->returnCallback(function () {
			throw new \Exception('not found');
		}));
		$folder = $this->createMock(\OCP\Files\IRootFolder::class);
		$folder->method('getById')->will($this->returnValue(null));
		$this->folder->method('getFolder')->will($this->returnValue($folder));
		$this->expectExceptionMessage('Invalid fileId');
		$this->service->validateFileByFileId(1);
	}

	public function testValidateFileByFileIdWhenFileIsNotPDF() {
		$this->file->method('getByFileId')->will($this->returnCallback(function () {
			throw new \Exception('not found');
		}));
		$folder = $this->createMock(\OCP\Files\IRootFolder::class);
		$file = $this->createMock(\OCP\Files\File::class);
		$file->method('getMimeType')->will($this->returnValue('html'));
		$folder->method('getById')->will($this->returnValue([$file]));
		$this->folder->method('getFolder')->will($this->returnValue($folder));
		$this->expectExceptionMessage('Must be a fileId of a PDF');
		$this->service->validateFileByFileId(1);
	}

	public function testValidateFileByFileIdWhenSuccess() {
		$folder = $this->createMock(\OCP\Files\IRootFolder::class);
		$file = $this->createMock(\OCP\Files\File::class);
		$file->method('getMimeType')->will($this->returnValue('application/pdf'));
		$folder->method('getById')->will($this->returnValue([$file]));
		$this->folder->method('getFolder')->will($this->returnValue($folder));
		$actual = $this->service->validateFileByFileId(1);
		$this->assertNull($actual);
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
		$this->expectExceptionMessage('User data needs to be an array: user of position %s in list');

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
		$this->expectExceptionMessage('User data needs to be an array with values: user of position %s in list');

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
		$this->expectExceptionMessage('User %s needs an email address');

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
		$this->expectExceptionMessage('Remove duplicated users, email address need to be unique');

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

	public function testValidateSuccess() {
		$actual = $this->service->validate([
			'file' => ['base64' => 'dGVzdA=='],
			'name' => 'test',
			'users' => [
				[
					'email' => 'jhondoe@test.coop'
				]
			],
			'userManager' => $this->user
		]);
		$this->assertNull($actual);
	}

	public function testIndexWithoutPermission() {
		$this->expectExceptionMessage('You are not allowed to request signing');
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
