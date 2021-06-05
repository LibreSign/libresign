<?php

namespace OCA\Libresign\Tests\Unit\Controller;

use Jeidison\JSignPDF\JSignPDF;
use OC\Files\Node\File;
use OC\Files\Node\Folder;
use OCA\Libresign\Controller\LibresignController;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Handler\JLibresignHandler;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\LibresignService;
use OCA\Libresign\Service\WebhookService;
use OCA\Libresign\Tests\Unit\LibresignFileTrait;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;

/**
 * @internal
 * @group DB
 */
final class LibresignControllerTest extends \OCA\Libresign\Tests\Unit\ApiTestCase {
	use LibresignFileTrait;
	use ProphecyTrait;
	public function testSignFile() {
		$request = $this->prophesize(IRequest::class);
		$fileUserMapper = $this->prophesize(FileUserMapper::class);
		$fileMapper = $this->prophesize(FileMapper::class);
		$root = $this->createMock(IRootFolder::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n
			->method('t')
			->will($this->returnArgument(0));
		$accountService = $this->createMock(AccountService::class);
		$webhook = $this->createMock(WebhookService::class);
		$logger = $this->createMock(LoggerInterface::class);
		$file = $this->prophesize(File::class);
		$file->getInternalPath()->willReturn("/path/to/someFileSigned");
		$config = $this->createMock(IConfig::class);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user');
		$userSession = $this->createMock(IUserSession::class);
		$userSession
			->method('getUser')
			->willReturn($user);
		$groupManager = $this->createMock(IGroupManager::class);
		
		$inputFilePath = '/path/to/someInputFilePath';
		$outputFolderPath = '/path/to/someOutputFolderPath';
		$certificatePath = '/path/to/someCertificatePath';
		$password = 'somePassword';

		$folder = $this->createMock(Folder::class);
		$folder
			->method('nodeExists')
			->willReturn(true);
		$outputFolder = $this->createMock(Folder::class);
		$signedFile = $this->createMock(File::class);
		$signedFile
			->method('getInternalPath')
			->willReturn('/path/to/someFileSigned');
		$outputFolder->method('newFile')->willReturn($signedFile);
		$folder
			->method('get')
			->will($this->returnValueMap([
				[$inputFilePath, $this->createMock(File::class)],
				[$certificatePath, $this->createMock(File::class)],
				[$outputFolderPath, $outputFolder]
			]));

		$root
			->method('getUserFolder')
			->willReturn($folder);
		$libresignHandler = $this->createMock(JLibresignHandler::class);
		$libresignHandler
			->method('signExistingFile')
			->willReturn(['signedFileName', 'contentOfSignedFile']);

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$controller = new LibresignController(
			$request->reveal(),
			$fileUserMapper->reveal(),
			$fileMapper->reveal(),
			$root,
			$l10n,
			$accountService,
			$libresignHandler,
			$webhook,
			$logger,
			$urlGenerator,
			$config,
			$userSession,
			$groupManager
		);

		$result = $controller->signDeprecated($inputFilePath, $outputFolderPath, $certificatePath, $password);

		static::assertSame(['fileSigned' => '/path/to/someFileSigned'], $result->getData());
	}

	public function failParameterMissingProvider() {
		$inputFilePath = '/path/to/someInputFilePath';
		$outputFolderPath = '/path/to/someOutputFolderPath';
		$certificatePath = '/path/to/someCertificatePath';
		$password = 'somePassword';

		return [
			[null, $outputFolderPath, $certificatePath, $password, 'inputFilePath'],
			[$inputFilePath, null,  $certificatePath, $password, 'outputFolderPath'],
			[$inputFilePath, $outputFolderPath,  null, $password, 'certificatePath'],
			[$inputFilePath, $outputFolderPath, $certificatePath, null, 'password'],
		];
	}

	/** @dataProvider failParameterMissingProvider */
	public function testSignFileFailParameterMissing(
		$inputFilePath,
		$outputFolderPath,
		$certificatePath,
		$password,
		$paramenterMissing
	) {
		$request = $this->prophesize(IRequest::class);
		$service = $this->prophesize(LibresignService::class);
		$fileUserMapper = $this->prophesize(FileUserMapper::class);
		$fileMapper = $this->prophesize(FileMapper::class);
		$root = $this->createMock(IRootFolder::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n
			->method('t')
			->will($this->returnArgument(0));
		$accountService = $this->createMock(AccountService::class);
		$libresignHandler = $this->createMock(JLibresignHandler::class);
		$webhook = $this->createMock(WebhookService::class);
		$logger = $this->createMock(LoggerInterface::class);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$config = $this->createMock(IConfig::class);
		$userSession = $this->createMock(IUserSession::class);
		$groupManager = $this->createMock(IGroupManager::class);

		$service->sign(\Prophecy\Argument::cetera())
			->shouldNotBeCalled();

		$controller = new LibresignController(
			$request->reveal(),
			$fileUserMapper->reveal(),
			$fileMapper->reveal(),
			$root,
			$l10n,
			$accountService,
			$libresignHandler,
			$webhook,
			$logger,
			$urlGenerator,
			$config,
			$userSession,
			$groupManager
		);

		$result = $controller->signDeprecated($inputFilePath, $outputFolderPath, $certificatePath, $password);

		static::assertSame(["parameter '{$paramenterMissing}' is required!"], $result->getData()['errors']);
		static::assertSame(422, $result->getStatus());
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSignUsingFileIdWithInvalidFileToSign() {
		$this->createUser('username', 'password');
		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withPath('/sign/file_id/invalid')
			->withRequestBody([
				'password' => 'secretPassword'
			])
			->assertResponseCode(422);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals('Invalid data to sign file', $body['errors'][0]);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSignUsingFileIdWithInvalidUuidToSign() {
		$this->createUser('username', 'password');
		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withPath('/sign/uuid/invalid')
			->withRequestBody([
				'password' => 'secretPassword'
			])
			->assertResponseCode(422);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals('Invalid data to sign file', $body['errors'][0]);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSignUsingFileIdWithAlreadySignedFile() {
		$user = $this->createUser('username', 'password');

		$user->setEMailAddress('person@test.coop');
		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			'name' => 'test',
			'users' => [
				[
					'email' => 'person@test.coop'
				]
			],
			'userManager' => $user
		]);
		$file['users'][0]->setSigned(time());
		$fileUser = \OC::$server->get(\OCA\Libresign\Db\FileUserMapper::class);
		$fileUser->update($file['users'][0]);

		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withPath('/sign/uuid/' . $file['users'][0]->getUuid())
			->withRequestBody([
				'password' => 'secretPassword'
			])
			->assertResponseCode(422);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals('File already signed by you', $body['errors'][0]);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSignUsingFileIdWithNotFoundFile() {
		$user = $this->createUser('username', 'password');

		$user->setEMailAddress('person@test.coop');
		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			'name' => 'test',
			'users' => [
				[
					'email' => 'person@test.coop'
				]
			],
			'userManager' => $user
		]);
		$folderService = \OC::$server->get(\OCA\Libresign\Service\FolderService::class);
		$libresignFolder = $folderService->getFolder();
		$libresignFolder->delete();

		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withPath('/sign/uuid/' . $file['users'][0]->getUuid())
			->withRequestBody([
				'password' => 'secretPassword'
			])
			->assertResponseCode(422);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals('File not found', $body['errors'][0]);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSignUsingFileIdWithoutPfx() {
		$user = $this->createUser('username', 'password');

		$user->setEMailAddress('person@test.coop');
		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			'name' => 'test',
			'users' => [
				[
					'email' => 'person@test.coop'
				]
			],
			'userManager' => $user
		]);

		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withPath('/sign/uuid/' . $file['users'][0]->getUuid())
			->withRequestBody([
				'password' => ''
			])
			->assertResponseCode(422);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals('Password to sign not defined. Create a password to sign', $body['errors'][0]);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSignUsingFileIdWithEmptyCertificatePassword() {
		$user = $this->createUser('username', 'password');

		$user->setEMailAddress('person@test.coop');
		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			'name' => 'test',
			'users' => [
				[
					'email' => 'person@test.coop'
				]
			],
			'userManager' => $user
		]);
		$accountService = \OC::$server->get(\OCA\Libresign\Service\AccountService::class);
		$accountService->generateCertificate('person@test.coop', 'secretPassword', 'username');

		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withPath('/sign/uuid/' . $file['users'][0]->getUuid())
			->withRequestBody([
				'password' => ''
			])
			->assertResponseCode(422);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals('Certificate Password is Empty.', $body['errors'][0]);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSignUsingFileIdWithSuccess() {
		$user = $this->createUser('username', 'password');

		$user->setEMailAddress('person@test.coop');
		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			'name' => 'test',
			'users' => [
				[
					'email' => 'person@test.coop'
				]
			],
			'userManager' => $user
		]);
		$accountService = \OC::$server->get(\OCA\Libresign\Service\AccountService::class);
		$accountService->generateCertificate('person@test.coop', 'secretPassword', 'username');

		$mock = $this->createMock(JSignPDF::class);
		$mock->method('sign')->willReturn('content');
		$jsignHandler = \OC::$server->get(\OCA\Libresign\Handler\JLibresignHandler::class);
		$jsignHandler->setJSignPdf($mock);
		\OC::$server->registerService(\OCA\Libresign\Handler\JLibresignHandler::class, function () use ($mock) {
			return $mock;
		});

		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withPath('/sign/uuid/' . $file['users'][0]->getUuid())
			->withRequestBody([
				'password' => 'secretPassword'
			]);

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testValidateUsignUuidWithInvalidData() {
		$this->request
			->withPath('/file/validate/uuid/invalid')
			->assertResponseCode(404);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals('Invalid data to validate file', $body['errors'][0]);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testValidateUsignFileIdWithInvalidData() {
		$this->request
			->withPath('/file/validate/file_id/171')
			->assertResponseCode(404);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals('Invalid data to validate file', $body['errors'][0]);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testValidateWithSuccessUsingUnloggedUser() {
		$user = $this->createUser('username', 'password');

		$user->setEMailAddress('person@test.coop');
		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			'name' => 'test',
			'users' => [
				[
					'email' => 'person@test.coop'
				]
			],
			'userManager' => $user
		]);

		$this->request
			->withPath('/file/validate/uuid/' . $file['uuid']);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertFalse($body['signatures'][0]['me'], "It's me");
		$this->assertFalse($body['settings']['canRequestSign'], 'Can permission to request sign');
		$this->assertFalse($body['settings']['canSign'], 'Can permission to sign');
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testValidateWithSuccessUsingLoggedUserWithoutPermissionToRequestSign() {
		$user = $this->createUser('username', 'password');

		$user->setEMailAddress('person@test.coop');
		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			'name' => 'test',
			'users' => [
				[
					'email' => 'person@test.coop'
				]
			],
			'userManager' => $user
		]);

		$this->request
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password')
			])
			->withPath('/file/validate/uuid/' . $file['uuid']);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertTrue($body['signatures'][0]['me'], "It's me");
		$this->assertFalse($body['settings']['canRequestSign'], 'Can permission to request sign');
		$this->assertTrue($body['settings']['canSign'], 'Can permission to sign');
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testValidateWithSuccessUsingLoggedUserAndWithPermissionToRequestSign() {
		$user = $this->createUser('username', 'password');

		$user->setEMailAddress('person@test.coop');
		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			'name' => 'test',
			'users' => [
				[
					'email' => 'person@test.coop'
				]
			],
			'userManager' => $user
		]);

		$this->mockConfig(['libresign' => ['webhook_authorized' => '["admin","testGroup"]']]);

		$this->request
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password')
			])
			->withPath('/file/validate/uuid/' . $file['uuid']);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertTrue($body['signatures'][0]['me'], "It's me");
		$this->assertTrue($body['settings']['canRequestSign'], 'Can permission to request sign');
		$this->assertTrue($body['settings']['canSign'], 'Can permission to sign');
	}
}
