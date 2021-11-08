<?php

use OC\AppFramework\Utility\TimeFactory;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Db\UserElementMapper;
use OCA\Libresign\Handler\Pkcs12Handler;
use OCA\Libresign\Handler\Pkcs7Handler;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\FileElementService;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\MailService;
use OCA\Libresign\Service\SignFileService;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IL10N;
use OCP\ITempManager;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

final class SignFileServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	/** @var IL10N|MockObject */
	private $l10n;
	/** @var Pkcs7Handler|MockObject */
	private $pkcs7Handler;
	/** @var Pkcs12Handler|MockObject */
	private $pkcs12Handler;
	/** @var SignFileService|MockObject */
	private $service;
	/** @var FileMapper|MockObject */
	private $fileMapper;
	/** @var FileUserMapper|MockObject */
	private $fileUserMapper;
	/** @var IUser|MockObject */
	private $user;
	/** @var IClientService|MockObject */
	private $clientService;
	/** @var IUserManager|MockObject */
	private $userManager;
	/** @var FolderService|MockObject */
	private $folderService;
	/** @var LoggerInterface|MockObject */
	private $logger;
	/** @var ValidateHelper|MockObject */
	private $validateHelper;
	/** @var IRootFolder|MockObject */
	private $root;
	/** @var FileElementMapper|MockObject */
	private $fileElementMapper;
	/** @var UserElementMapper|MockObject */
	private $userElementMapper;
	/** @var FileElementService|MockObject */
	private $fileElementService;
	/** @var TimeFactory|MockObject */
	private $timeFactory;
	/** @var ITempManager|MockObject */
	private $tempManager;

	public function setUp(): void {
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->fileUserMapper = $this->createMock(FileUserMapper::class);
		$this->user = $this->createMock(IUser::class);
		$this->pkcs7Handler = $this->createMock(Pkcs7Handler::class);
		$this->pkcs12Handler = $this->createMock(Pkcs12Handler::class);
		$this->clientService = $this->createMock(IClientService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->mail = $this->createMock(MailService::class);
		$this->folderService = $this->createMock(FolderService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->validateHelper = $this->createMock(\OCA\Libresign\Helper\ValidateHelper::class);
		$this->root = $this->createMock(\OCP\Files\IRootFolder::class);
		$this->fileElementMapper = $this->createMock(FileElementMapper::class);
		$this->userElementMapper = $this->createMock(UserElementMapper::class);
		$this->fileElementService = $this->createMock(FileElementService::class);
		$this->timeFactory = $this->createMock(TimeFactory::class);
		$this->tempManager = $this->createMock(ITempManager::class);
		$this->service = new SignFileService(
			$this->l10n,
			$this->fileMapper,
			$this->fileUserMapper,
			$this->pkcs7Handler,
			$this->pkcs12Handler,
			$this->folderService,
			$this->clientService,
			$this->userManager,
			$this->mail,
			$this->logger,
			$this->validateHelper,
			$this->root,
			$this->fileElementMapper,
			$this->userElementMapper,
			$this->fileElementService,
			$this->timeFactory,
			$this->tempManager
		);
	}

	public function testSaveFileWithInvalidUrl() {
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
		$this->folderService
			->expects($this->once())
			->method('getFolder')
			->willReturn($folder);
		$this->user
			->method('getUID')
			->willReturn('test');
		$this->service->saveFile([
			'file' => ['url' => 'qwert'],
			'name' => 'test',
			'users' => [
				[
					'email' => 'valid@test.coop'
				]
			],
			'userManager' => $this->user
		]);
	}

	public function testCanDeleteSignRequestWhenDocumentAlreadySigned() {
		$file = $this->createMock(\OCA\Libresign\Db\File::class);
		$file->method('__call')->with($this->equalTo('getId'))->will($this->returnValue(1));
		$this->fileMapper->method('getByUuid')->will($this->returnValue($file));
		$fileUser = $this->createMock(\OCA\Libresign\Db\FileUser::class);
		$fileUser
			->method('__call')
			->withConsecutive(
				[$this->equalTo('getSigned')]
			)
			->will($this->returnValueMap([
				['getSigned', [], '2021-01-01 01:01:01'],
			]));
		$this->fileUserMapper->method('getByFileUuid')->will($this->returnValue([$fileUser]));
		$this->expectErrorMessage('Document already signed');
		$this->service->canDeleteSignRequest(['uuid' => 'valid']);
	}

	public function testCanDeleteSignRequestWhenNoSignatureWasRequested() {
		$file = $this->createMock(\OCA\Libresign\Db\File::class);
		$file->method('__call')->with($this->equalTo('getId'))->will($this->returnValue(1));
		$this->fileMapper->method('getByUuid')->will($this->returnValue($file));
		$fileUser = $this->createMock(\OCA\Libresign\Db\FileUser::class);
		$fileUser
			->method('__call')
			->withConsecutive(
				[$this->equalTo('getSigned')],
				[$this->equalTo('getEmail')]
			)
			->will($this->returnValueMap([
				['getSigned', [], null],
				['getEmail', [], 'otheremail@test.coop']
			]));
		$this->fileUserMapper->method('getByFileUuid')->will($this->returnValue([$fileUser]));
		$this->expectErrorMessage('No signature was requested to %');
		$this->service->canDeleteSignRequest([
			'uuid' => 'valid',
			'users' => [
				[
					'email' => 'test@test.coop'
				]
			]
		]);
	}

	public function testCanDeleteSignRequestSuccess() {
		$fileUser = $this->createMock(\OCA\Libresign\Db\FileUser::class);
		$fileUser
			->method('__call')
			->withConsecutive(
				[$this->equalTo('getSigned')],
				[$this->equalTo('getEmail')]
			)
			->will($this->returnValueMap([
				['getSigned', [], null],
				['getEmail', [], 'valid@test.coop']
			]));
		$this->fileUserMapper
			->method('getByFileUuid')
			->willReturn([$fileUser]);
		$actual = $this->service->canDeleteSignRequest([
			'uuid' => 'valid',
			'users' => [
				[
					'email' => 'valid@test.coop'
				]
			]
		]);
		$this->assertNull($actual);
	}

	public function testDeleteSignRequestDeprecatedSuccessUsingUuid() {
		$file = $this->createMock(\OCA\Libresign\Db\File::class);
		$file->method('__call')
			->withConsecutive(
				[$this->equalTo('getSigned')],
				[$this->equalTo('getEmail')],
				[$this->equalTo('getId')],
				[$this->equalTo('getUuid')]
			)
			->will($this->returnValueMap([
				['getSigned', [], null],
				['getEmail', [], 'test@test.coop'],
				['getId', [], 123],
				['getUuid', [], 'valid']
			]));
		$this->fileMapper->method('getByUuid')->will($this->returnValue($file));
		$this->fileUserMapper->method('getByFileUuid')->will($this->returnValue([$file]));
		$this->fileUserMapper->method('getByEmailAndFileId')->will($this->returnValue($file));
		$this->fileUserMapper->method('delete')->willThrowException($this->createMock(\Exception::class));
		$actual = $this->service->deleteSignRequestDeprecated([
			'uuid' => 'valid',
			'users' => [
				[
					'email' => 'test@test.coop'
				]
			]
		]);
		$this->assertIsArray($actual);
		$this->assertCount(1, $actual);
	}

	public function testDeleteSignRequestDeprecatedSuccessUsingFileId() {
		$file = $this->createMock(\OCA\Libresign\Db\File::class);
		$file->method('__call')
			->withConsecutive(
				[$this->equalTo('getSigned')],
				[$this->equalTo('getEmail')],
				[$this->equalTo('getId')],
				[$this->equalTo('getFile')]
			)
			->will($this->returnValueMap([
				['getSigned', [], null],
				['getEmail', [], 'test@test.coop'],
				['getId', [], 123],
				['getFile', [], 'valid']
			]));
		$this->fileMapper->method('getByFileId')->will($this->returnValue($file));
		$this->fileUserMapper->method('getByNodeId')->will($this->returnValue([$file]));
		$this->fileUserMapper->method('getByEmailAndFileId')->will($this->returnValue($file));
		$this->fileUserMapper->method('delete')->willThrowException($this->createMock(\Exception::class));
		$actual = $this->service->deleteSignRequestDeprecated([
			'file' => [
				'fileId' => 171
			],
			'users' => [
				[
					'email' => 'test@test.coop'
				]
			]
		]);
		$this->assertIsArray($actual);
		$this->assertCount(1, $actual);
	}

	public function testSaveFileUsingFileIdSuccess() {
		$file = $this->createMock(OCP\Files\File::class);
		$file->method('getContent')
			->willReturn(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'));
		$folder = $this->createMock(\OCP\Files\IRootFolder::class);
		$folder->method('getById')->willReturn([$file]);
		$this->folderService->method('getFolder')->will($this->returnValue($folder));
		$this->user
			->method('getUID')
			->willReturn('user');
		$actual = $this->service->saveFile([
			'file' => ['fileId' => 123],
			'userManager' => $this->user,
			'name' => 'nameOfFile'
		]);
		$this->assertInstanceOf('\OCA\Libresign\Db\File', $actual);

		$actual = $this->service->saveFile([
			'file' => ['fileId' => 123],
			'userManager' => $this->user,
			'name' => 'nameOfFile',
			'callback' => 'http://callback.coop'
		]);
		$this->assertInstanceOf('\OCA\Libresign\Db\File', $actual);
	}

	public function testSaveFileWhenFileAlreadyExists() {
		$folder = $this->createMock(\OCP\Files\IRootFolder::class);
		$folder->method('nodeExists')->willReturn(true);
		$this->folderService->method('getFolder')->will($this->returnValue($folder));
		$this->user->method('getUID')->willReturn('uuid');
		$this->expectErrorMessage('File already exists');
		$this->service->saveFile([
			'file' => [],
			'userManager' => $this->user
		]);
	}

	public function testSaveFileWhenNotIsAUrlOfPdf() {
		$folder = $this->createMock(\OCP\Files\IRootFolder::class);
		$folder->method('nodeExists')->willReturn(false);
		$folder->method('newFolder')->willReturn($folder);
		$this->folderService->method('getFolder')->will($this->returnValue($folder));
		$this->user->method('getUID')->willReturn('uuid');
		$this->expectErrorMessage('The URL should be a PDF.');
		$this->service->saveFile([
			'name' => 'Name',
			'file' => [
				'url' => 'https://invalid.coop'
			],
			'userManager' => $this->user
		]);
	}

	public function testSaveFileWhenUrlReturnEmptyBody() {
		$folder = $this->createMock(\OCP\Files\IRootFolder::class);
		$folder->method('nodeExists')->willReturn(false);
		$folder->method('newFolder')->willReturn($folder);
		$this->folderService->method('getFolder')->will($this->returnValue($folder));
		$this->user->method('getUID')->willReturn('uuid');

		$response = $this->createMock(IResponse::class);
		$response->expects($this->once())
			->method('getHeader')
			->with('Content-Type')
			->willReturn('application/pdf');
		$client = $this->createMock(IClient::class);
		$client->expects($this->once())
			->method('get')
			->willReturn($response);
		$this->clientService->expects($this->once())
			->method('newClient')
			->with()
			->willReturn($client);

		$this->expectErrorMessage('Empty file');
		$this->service->saveFile([
			'name' => 'Name',
			'file' => [
				'url' => 'https://vaild.coop/file.pdf'
			],
			'userManager' => $this->user
		]);
	}

	public function testSaveFileWithBase64ContainingInvalidPdf() {
		$folder = $this->createMock(\OCP\Files\IRootFolder::class);
		$folder->method('nodeExists')->willReturn(false);
		$folder->method('newFolder')->willReturn($folder);
		$this->folderService->method('getFolder')->will($this->returnValue($folder));
		$this->user->method('getUID')->willReturn('uuid');

		$this->expectErrorMessage('Invalid PDF');
		$this->service->saveFile([
			'name' => 'Name',
			'file' => [
				'base64' => 'dGVzdA=='
			],
			'userManager' => $this->user
		]);
	}

	public function testSaveFileWithValidPdf() {
		$folder = $this->createMock(\OCP\Files\IRootFolder::class);
		$folder->method('nodeExists')->willReturn(false);
		$folder->method('newFolder')->willReturn($folder);
		$file = $this->createMock(\OCP\Files\File::class);
		$file
			->method('getContent')
			->willReturn(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'));
		$folder->method('newFile')->willReturn($file);
		$this->folderService->method('getFolder')->will($this->returnValue($folder));
		$this->user->method('getUID')->willReturn('uuid');

		$actual = $this->service->saveFile([
			'name' => 'Name',
			'file' => [
				'base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))
			],
			'userManager' => $this->user
		]);
		$this->assertInstanceOf('\OCA\Libresign\Db\File', $actual);
	}

	public function testSaveUsingUuid() {
		$file = $this->createMock(\OCA\Libresign\Db\File::class);
		$file
			->method('__call')
			->withConsecutive(
				[$this->equalTo('getUuid')],
				[$this->equalTo('getNodeId')],
				[$this->equalTo('getId')]
			)
			->will($this->returnValueMap([
				['getUuid', [], 'uuid-here'],
				['getNodeId', [], 123],
				['getId', [], 123]
			]));
		$this->fileMapper->method('getByUuid')->will($this->returnValue($file));

		$fileUser = $this->createMock(\OCA\Libresign\Db\FileUser::class);
		$fileUser
			->method('__call')
			->withConsecutive(
				[$this->equalTo('setFileId')],
				[$this->equalTo('getUuid')],
				[$this->equalTo('setUuid'), $this->callback(function ($subject) {
					$this->assertIsString($subject[0]);
					$this->assertEquals(36, strlen($subject[0]));
					return true;
				})],
				[$this->equalTo('setEmail'), $this->equalTo(['user@test.coop'])],
				[$this->equalTo('getDescription')],
				[$this->equalTo('setDescription'), $this->equalTo(['Please, sign'])],
				[$this->equalTo('setUserId')],
				[$this->equalTo('setDisplayName')],
				[$this->equalTo('getId')],
				[$this->equalTo('getId')],
				[$this->equalTo('getFileId')]
			)
			->will($this->returnValueMap([
				['setFileId', [], null],
				['getUuid', [], null],
				['setUuid', [], null],
				['setEmail', [], null],
				['getDescription', [], null],
				['setDescription', [], null],
				['setUserId', [], 123],
				['setDisplayName', [], 123],
				['getId', [], 123],
				['getId', [], 123],
				['getFileId', [], 123]
			]));
		$this->fileUserMapper
			->method('getByEmailAndFileId')
			->with('user@test.coop')
			->will($this->returnValue($fileUser));
		$user = $this->createMock(\OCP\IUser::class);
		$user->method('getDisplayName')->willReturn('John Doe');
		$this->userManager->method('getByEmail')->willReturn([$user]);
		$actual = $this->service->save([
			'uuid' => 'the-uuid-here',
			'users' => [
				[
					'email' => 'USER@TEST.COOP',
					'description' => 'Please, sign'
				]
			]
		]);
		$this->assertArrayHasKey('uuid', $actual);
		$this->assertEquals('uuid-here', $actual['uuid']);
		$this->assertArrayHasKey('users', $actual);
		$this->assertCount(1, $actual['users']);
		$this->assertInstanceOf('\OCA\Libresign\Db\FileUser', $actual['users'][0]);
	}

	public function testSaveUsingFileId() {
		$this->fileMapper->method('getByFileId')->willThrowException($this->createMock(\Exception::class));

		$fileUser = $this->createMock(\OCA\Libresign\Db\FileUser::class);
		$fileUser
			->method('__call')
			->withConsecutive(
				[$this->equalTo('setFileId')],
				[$this->equalTo('getUuid')],
				[$this->equalTo('setUuid'), $this->callback(function ($subject) {
					$this->assertIsString($subject[0]);
					$this->assertEquals(36, strlen($subject[0]));
					return true;
				})],
				[$this->equalTo('setEmail'), $this->equalTo(['user@test.coop'])],
				[$this->equalTo('getDescription')],
				[$this->equalTo('setDescription'), $this->equalTo(['Please, sign'])]
			)
			->will($this->returnValueMap([
				['setFileId', [], null],
				['getUuid', [], null],
				['setUuid', [], null],
				['setEmail', [], null],
				['getDescription', [], null],
				['setDescription', [], null]
			]));
		$this->fileUserMapper
			->method('getByEmailAndFileId')
			->with('user@test.coop')
			->will($this->returnValue($fileUser));
		$user = $this->createMock(\OCP\IUser::class);
		$user->method('getDisplayName')->willReturn('John Doe');
		$this->userManager->method('getByEmail')->willReturn([$user]);
		$this->fileMapper
			->method('insert')
			->willReturnCallback(function (\OCA\Libresign\Db\File $c) {
				$c->setId(123);
				return $c;
			});

		$file = $this->createMock(\OCP\Files\File::class);
		$file
			->method('getContent')
			->willReturn(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'));
		$folder = $this->createMock(\OCP\Files\Folder::class);
		$folder
			->method('getById')
			->willReturn([$file]);
		$this->folderService
			->method('getFolder')
			->willReturn($folder);
		$this->folderService
			->method('getUserId')
			->willReturn('user');
		$actual = $this->service->save([
			'file' => [
				'fileId' => 123
			],
			'users' => [
				[
					'email' => 'USER@TEST.COOP',
					'description' => 'Please, sign'
				]
			],
			'name' => 'Filename',
			'userManager' => $this->user
		]);
		$this->assertArrayHasKey('uuid', $actual);
		$this->assertArrayHasKey('users', $actual);
		$this->assertCount(1, $actual['users']);
		$this->assertInstanceOf('\OCA\Libresign\Db\FileUser', $actual['users'][0]);
	}

	public function testSaveFileUserWhenUserExists() {
		$fileUser = $this->createMock(\OCA\Libresign\Db\FileUser::class);
		$fileUser
			->method('__call')
			->with('getId')
			->willReturn(123);
		$actual = $this->service->saveFileUser($fileUser);
		$this->assertNull($actual);
	}

	public function testSaveFileUserWhenUserDontExists() {
		$fileUser = $this->createMock(\OCA\Libresign\Db\FileUser::class);
		$fileUser
			->method('__call')
			->with('getId')
			->willReturn(null);
		$actual = $this->service->saveFileUser($fileUser);
		$this->assertNull($actual);
	}

	public function testValidateNameIsMandatory() {
		$this->expectExceptionMessage('Name is mandatory');

		$this->service->validateNewRequestToFile([
			'file' => ['url' => 'qwert'],
			'userManager' => $this->user
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
		$this->clientService
			->method('newClient')
			->will($this->returnValue($client));

		$this->service->validateNewRequestToFile([
			'file' => ['url' => 'http://test.coop'],
			'name' => 'test',
			'userManager' => $this->user
		]);
	}

	public function testValidateEmptyUsersCollection() {
		$this->expectExceptionMessage('Empty users list');

		$this->service->validateNewRequestToFile([
			'file' => ['base64' => 'dGVzdA=='],
			'name' => 'test',
			'userManager' => $this->user
		]);
	}

	public function testValidateUserCollectionNotArray() {
		$this->expectExceptionMessage('User list needs to be an array');

		$this->service->validateNewRequestToFile([
			'file' => ['base64' => 'dGVzdA=='],
			'name' => 'test',
			'users' => 'asdfg',
			'userManager' => $this->user
		]);
	}

	public function testValidateUserEmptyCollection() {
		$this->expectExceptionMessage('Empty users list');

		$this->service->validateNewRequestToFile([
			'file' => ['base64' => 'dGVzdA=='],
			'name' => 'test',
			'users' => null,
			'userManager' => $this->user
		]);
	}

	public function testValidateUserDuplicatedEmail() {
		$this->expectExceptionMessage('Remove duplicated users, email address need to be unique');

		$this->service->validateNewRequestToFile([
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
		$actual = $this->service->validateNewRequestToFile([
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

	public function testNotifyCallback() {
		$file = $this->createMock(\OCP\Files\File::class);
		$actual = $this->service->notifyCallback('https://test.coop', 'uuid', $file);
		$this->assertInstanceOf('\OCP\Http\Client\IResponse', $actual);
	}

	public function testSignWithFileNotFound() {
		$this->expectErrorMessage('File not found');

		$this->createUser('username', 'password');

		$file = new \OCA\Libresign\Db\File();
		$file->setUserId('username');

		$folder = $this->createMock(\OCP\Files\Folder::class);
		$folder
			->method('getById')
			->willReturn([]);
		$this->root->method('getUserFolder')
			->willReturn($folder);

		$fileUser = new \OCA\Libresign\Db\FileUser();
		$this->service
			->setLibreSignFile($file)
			->setFileUser($fileUser)
			->setPassword('password')
			->sign();
	}

	public function testValidateUserManagerWithoutUserManager() {
		$this->expectExceptionMessage('You are not allowed to request signing');
		$this->service->validateUserManager([]);
	}

	/**
	 * @dataProvider dataSaveVisibleElements
	 */
	public function testSaveVisibleElements($elements) {
		$libreSignFile = new \OCA\Libresign\Db\File();
		if (!empty($elements)) {
			$libreSignFile->setId(1);
			$this->fileElementService
				->expects($this->exactly(count($elements)))
				->method('saveVisibleElement');
		}
		$actual = self::invokePrivate($this->service, 'saveVisibleElements', [
			['visibleElements' => $elements], $libreSignFile
		]);
		$this->assertSameSize($elements, $actual);
	}

	public function dataSaveVisibleElements() {
		return [
			[[]],
			[[['uid' => 1]]],
			[[['uid' => 1], ['uid' => 1]]],
		];
	}
}
