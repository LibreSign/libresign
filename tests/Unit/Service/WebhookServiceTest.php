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
	private $clientService;
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
		$this->clientService = $this->createMock(IClientService::class);
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
			$this->clientService,
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
		$this->expectExceptionMessage('Inform URL or base64 or fileID to sign');
		$this->service->validateFile([
			'file' => ['invalid'],
			'name' => 'test'
		]);
	}

	public function testValidateFileWithInvalidFileId() {
		$this->expectExceptionMessage('Invalid fileID');
		$this->service->validateFile([
			'file' => ['fileId' => 'invalid'],
			'name' => 'test'
		]);
	}

	public function testValidateFileWhenFileIdDoesNotExist() {
		$this->expectExceptionMessage('Invalid fileID');
		$this->service->validateFile([
			'file' => ['fileId' => 123],
			'name' => 'test'
		]);
	}

	public function testValidateFileByNodeIdWhenAlreadyAskedToSignThisDocument() {
		$this->fileUser->method('getByNodeId')->will($this->returnValue('exists'));
		$this->expectExceptionMessage('Already asked to sign this document');
		$this->service->validateFileByNodeId(1);
	}

	public function testValidateFileByNodeIdWhenFileIdNotExists() {
		$this->fileUser->method('getByNodeId')->will($this->returnCallback(function () {
			throw new \Exception('not found');
		}));
		$this->expectExceptionMessage('Invalid fileID');
		$this->service->validateFileByNodeId(1);
	}

	public function testValidateFileByNodeIdWhenFileNotExists() {
		$this->fileUser->method('getByNodeId')->will($this->returnCallback(function () {
			throw new \Exception('not found');
		}));
		$folder = $this->createMock(\OCP\Files\IRootFolder::class);
		$folder->method('getById')->will($this->returnValue(null));
		$this->folder->method('getFolder')->will($this->returnValue($folder));
		$this->expectExceptionMessage('Invalid fileID');
		$this->service->validateFileByNodeId(1);
	}

	public function testValidateFileByNodeIdWhenFileIsNotPDF() {
		$folder = $this->createMock(\OCP\Files\IRootFolder::class);
		$file = $this->createMock(\OCP\Files\File::class);
		$file->method('getMimeType')->will($this->returnValue('html'));
		$folder->method('getById')->will($this->returnValue([$file]));
		$this->folder->method('getFolder')->will($this->returnValue($folder));
		$this->expectExceptionMessage('Must be a fileID of a PDF');
		$this->service->validateFileByNodeId(1);
	}

	public function testValidateFileByNodeIdWhenSuccess() {
		$folder = $this->createMock(\OCP\Files\IRootFolder::class);
		$file = $this->createMock(\OCP\Files\File::class);
		$file->method('getMimeType')->will($this->returnValue('application/pdf'));
		$folder->method('getById')->will($this->returnValue([$file]));
		$this->folder->method('getFolder')->will($this->returnValue($folder));
		$actual = $this->service->validateFileByNodeId(1);
		$this->assertNull($actual);
	}

	public function testValidateFileUuidWithInvalidUuid() {
		$this->expectExceptionMessage('Invalid UUID file');
		$this->service->validateFileUuid([]);
	}

	public function testValidateFileUuidWithValidUuid() {
		$file = $this->createMock(\OCA\Libresign\Db\File::class);
		$this->file->method('getByUuid')->will($this->returnValue($file));
		$actual = $this->service->validateFileUuid(['uuid' => 'valid']);
		$this->assertNull($actual);
	}

	public function testCanDeleteSignRequestWhenDocumentAlreadySigned() {
		$file = $this->createMock(\OCA\Libresign\Db\File::class);
		$file->method('__call')->with($this->equalTo('getId'))->will($this->returnValue(1));
		$this->file->method('getByUuid')->will($this->returnValue($file));
		$fileUser = $this->createMock(\OCA\Libresign\Db\FileUser::class);
		$fileUser->method('__call')->with($this->equalTo('getSigned'))->willReturn(1234564);
		$this->fileUser->method('getByFileId')->will($this->returnValue([$fileUser]));
		$this->expectErrorMessage('Document already signed');
		$this->service->canDeleteSignRequest(['uuid' => 'valid']);
	}

	public function testCanDeleteSignRequestWhenNoSignatureWasRequested() {
		$file = $this->createMock(\OCA\Libresign\Db\File::class);
		$file->method('__call')->with($this->equalTo('getId'))->will($this->returnValue(1));
		$this->file->method('getByUuid')->will($this->returnValue($file));
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
		$this->fileUser->method('getByFileId')->will($this->returnValue([$fileUser]));
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
		$file = $this->createMock(\OCA\Libresign\Db\File::class);
		$file->method('__call')->with($this->equalTo('getId'))->will($this->returnValue(1));
		$this->file->method('getByUuid')->will($this->returnValue($file));
		$fileUser = $this->createMock(\OCA\Libresign\Db\FileUser::class);
		$fileUser->method('__call')->with($this->equalTo('getSigned'))->willReturn(null);
		$this->fileUser->method('getByFileId')->will($this->returnValue([$fileUser]));
		$actual = $this->service->canDeleteSignRequest([
			'uuid' => 'valid',
			'users' => []
		]);
		$this->assertNull($actual);
	}

	public function testDeleteSignRequestSuccess() {
		$file = $this->createMock(\OCA\Libresign\Db\File::class);
		$file->method('__call')->with($this->equalTo('getId'))->will($this->returnValue(1));
		$this->file->method('getByUuid')->will($this->returnValue($file));
		$this->fileUser->method('getByFileId')->will($this->returnValue([$file]));
		$this->fileUser->method('getByEmailAndFileId')->will($this->returnValue($file));
		$actual = $this->service->deleteSignRequest([
			'uuid' => 'valid',
			'users' => [
				[
					'email' => 'test@test.coop'
				]
			]
		]);
		$this->assertNull($actual);
	}

	public function testSaveFileUsingFileIdSuccess() {
		$folder = $this->createMock(\OCP\Files\IRootFolder::class);
		$folder->method('getById')->willReturn([$folder]);
		$this->folder->method('getFolder')->will($this->returnValue($folder));
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
		$this->folder->method('getFolder')->will($this->returnValue($folder));
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
		$this->folder->method('getFolder')->will($this->returnValue($folder));
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
		$this->folder->method('getFolder')->will($this->returnValue($folder));
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
		$this->folder->method('getFolder')->will($this->returnValue($folder));
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
		$folder->method('newFile')->willReturn($file);
		$this->folder->method('getFolder')->will($this->returnValue($folder));
		$this->user->method('getUID')->willReturn('uuid');

		// $this->expectErrorMessage('Invalid PDF');
		$actual = $this->service->saveFile([
			'name' => 'Name',
			'file' => [
				'base64' => <<<PDF
				JVBERi0xLjYKJcOkw7zDtsOfCjIgMCBvYmoKPDwvTGVuZ3RoIDMgMCBSL0ZpbHRlci9GbGF0ZURl
				Y29kZT4+CnN0cmVhbQp4nDPQM1Qo5ypUMFAw0DMwslAw0rMwMIOSRalc4VoKeVyGCiBYlM5lAJIw
				NlHI5YIrA/JyoMohBuTAjQKxYCoyuNK0uAIVAMl8FhMKZW5kc3RyZWFtCmVuZG9iagoKMyAwIG9i
				ago3NQplbmRvYmoKCjUgMCBvYmoKPDwKPj4KZW5kb2JqCgo2IDAgb2JqCjw8L0ZvbnQgNSAwIFIK
				L1Byb2NTZXRbL1BERi9UZXh0XQo+PgplbmRvYmoKCjEgMCBvYmoKPDwvVHlwZS9QYWdlL1BhcmVu
				dCA0IDAgUi9SZXNvdXJjZXMgNiAwIFIvTWVkaWFCb3hbMCAwIDIuODM0NjQ1NjY5MjkxMzQgMi44
				MzQ2NDU2NjkyOTEzNF0vR3JvdXA8PC9TL1RyYW5zcGFyZW5jeS9DUy9EZXZpY2VSR0IvSSB0cnVl
				Pj4vQ29udGVudHMgMiAwIFI+PgplbmRvYmoKCjQgMCBvYmoKPDwvVHlwZS9QYWdlcwovUmVzb3Vy
				Y2VzIDYgMCBSCi9NZWRpYUJveFsgMCAwIDIgMiBdCi9LaWRzWyAxIDAgUiBdCi9Db3VudCAxPj4K
				ZW5kb2JqCgo3IDAgb2JqCjw8L1R5cGUvQ2F0YWxvZy9QYWdlcyA0IDAgUgovT3BlbkFjdGlvblsx
				IDAgUiAvWFlaIG51bGwgbnVsbCAwXQo+PgplbmRvYmoKCjggMCBvYmoKPDwvQ3JlYXRvcjxGRUZG
				MDA0NDAwNzIwMDYxMDA3Nz4KL1Byb2R1Y2VyPEZFRkYwMDRDMDA2OTAwNjIwMDcyMDA2NTAwNEYw
				MDY2MDA2NjAwNjkwMDYzMDA2NTAwMjAwMDM3MDAyRTAwMzA+Ci9DcmVhdGlvbkRhdGUoRDoyMDIx
				MDUxNDE0MzA1NS0wMycwMCcpPj4KZW5kb2JqCgp4cmVmCjAgOQowMDAwMDAwMDAwIDY1NTM1IGYg
				CjAwMDAwMDAyNTkgMDAwMDAgbiAKMDAwMDAwMDAxOSAwMDAwMCBuIAowMDAwMDAwMTY1IDAwMDAw
				IG4gCjAwMDAwMDA0MjcgMDAwMDAgbiAKMDAwMDAwMDE4NCAwMDAwMCBuIAowMDAwMDAwMjA2IDAw
				MDAwIG4gCjAwMDAwMDA1MjEgMDAwMDAgbiAKMDAwMDAwMDYwNCAwMDAwMCBuIAp0cmFpbGVyCjw8
				L1NpemUgOS9Sb290IDcgMCBSCi9JbmZvIDggMCBSCi9JRCBbIDw0ODRCRUFEODVDNDI3MUJFNUM0
				MEFGQkEwRDEzQ0U2Mz4KPDQ4NEJFQUQ4NUM0MjcxQkU1QzQwQUZCQTBEMTNDRTYzPiBdCi9Eb2ND
				aGVja3N1bSAvRUUyMThGOURBRDY5RDU3RDNDNUYzRjFCRTQ5NzVBQjkKPj4Kc3RhcnR4cmVmCjc3
				MAolJUVPRgo=
				PDF
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
				[$this->equalTo('getId')]
			)
			->will($this->returnValueMap([
				['getUuid', [], 'uuid-here'],
				['getId', [], 123]
			]));
		$this->file->method('getByUuid')->will($this->returnValue($file));

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
		$this->fileUser
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

		$this->service->validate([
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

	public function testNotifyCallback() {
		$file = $this->createMock(\OCP\Files\File::class);
		$actual = $this->service->notifyCallback('https://test.coop', 'uuid', $file);
		$this->assertInstanceOf('\OCP\Http\Client\IResponse', $actual);
	}
}
