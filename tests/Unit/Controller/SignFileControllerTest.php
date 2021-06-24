<?php

namespace OCA\Libresign\Tests\Unit\Controller;

use donatj\MockWebServer\Response;
use Jeidison\JSignPDF\JSignPDF;
use OCA\Libresign\Controller\SignFileController;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Handler\JLibresignHandler;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\MailService;
use OCA\Libresign\Service\SignFileService;
use OCA\Libresign\Tests\Unit\ApiTestCase;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;

/**
 * @group DB
 */
final class SignFileControllerTest extends ApiTestCase {
	use ProphecyTrait;
	public function testSignFileWithSuccess() {
		$request = $this->prophesize(IRequest::class);
		$fileUserMapper = $this->prophesize(FileUserMapper::class);
		$fileMapper = $this->prophesize(FileMapper::class);
		$root = $this->createMock(IRootFolder::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n
			->method('t')
			->will($this->returnArgument(0));
		$accountService = $this->createMock(AccountService::class);
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
		$signFile = $this->createMock(SignFileService::class);
		$signFile
			->method('sign')
			->willReturn($signedFile);

		$root
			->method('getUserFolder')
			->willReturn($folder);
		$libresignHandler = $this->createMock(JLibresignHandler::class);
		$libresignHandler
			->method('signExistingFile')
			->willReturn(['signedFileName', 'contentOfSignedFile']);
		$mail = $this->createMock(MailService::class);

		$controller = new SignFileController(
			$request->reveal(),
			$l10n,
			$fileUserMapper->reveal(),
			$fileMapper->reveal(),
			$root,
			$userSession,
			$accountService,
			$signFile,
			$libresignHandler,
			$mail,
			$logger,
			$config
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
		$fileUserMapper = $this->prophesize(FileUserMapper::class);
		$fileMapper = $this->prophesize(FileMapper::class);
		$root = $this->createMock(IRootFolder::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n
			->method('t')
			->will($this->returnArgument(0));
		$accountService = $this->createMock(AccountService::class);
		$libresignHandler = $this->createMock(JLibresignHandler::class);
		$signFile = $this->createMock(SignFileService::class);
		$logger = $this->createMock(LoggerInterface::class);
		$config = $this->createMock(IConfig::class);
		$userSession = $this->createMock(IUserSession::class);
		$mail = $this->createMock(MailService::class);

		$signFile = $this->createMock(SignFileService::class);

		$controller = new SignFileController(
			$request->reveal(),
			$l10n,
			$fileUserMapper->reveal(),
			$fileMapper->reveal(),
			$root,
			$userSession,
			$accountService,
			$signFile,
			$libresignHandler,
			$mail,
			$logger,
			$config
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
	public function testPostRegisterWithValidationFailure() {
		$this->createUser('username', 'password');
		$this->request
			->withMethod('POST')
			->withPath('/sign/register')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'name' => 'filename',
				'file' => [],
				'users' => []
			])
			->assertResponseCode(422);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals('You are not allowed to request signing', $body['message']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testPostRegisterWithSuccess() {
		$this->createUser('username', 'password');

		$this->mockConfig([
			'libresign' => [
				'webhook_authorized' => '["admin","testGroup"]',
				'notifyUnsignedUser' => 0
			]
		]);

		$this->request
			->withMethod('POST')
			->withPath('/sign/register')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'name' => 'filename',
				'file' => [
					'base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))
				],
				'users' => [
					[
						'email' => 'user@test.coop'
					]
				]
			]);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$body['data']['users'][] = ['email' => 'user@test.coop'];
		$this->addFile($body['data']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testPatchRegisterWithValidationFailure() {
		$this->createUser('username', 'password');
		$this->request
			->withMethod('PATCH')
			->withPath('/sign/register')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'uuid' => '12345678-1234-1234-1234-123456789012',
				'users' => []
			])
			->assertResponseCode(422);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals('You are not allowed to request signing', $body['message']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testPatchRegisterWithSuccess() {
		$user = $this->createUser('username', 'password');

		$this->mockConfig([
			'libresign' => [
				'webhook_authorized' => '["admin","testGroup"]',
				'notifyUnsignedUser' => 0
			]
		]);

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
			->withMethod('PATCH')
			->withPath('/sign/register')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'uuid' => $file['uuid'],
				'users' => [
					[
						'email' => 'user@test.coop'
					]
				]
			]);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$body['data']['users'][] = ['email' => 'user@test.coop'];
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testDeleteRegisterWithValidationFailure() {
		$user = $this->createUser('username', 'password');

		$this->request
			->withMethod('DELETE')
			->withPath('/sign/register/signature')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'uuid' => 'invalid',
				'users' => []
			])
			->assertResponseCode(422);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals('You are not allowed to request signing', $body['message']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testDeleteRegisterWithSuccess() {
		$user = $this->createUser('username', 'password');

		$this->mockConfig([
			'libresign' => [
				'webhook_authorized' => '["admin","testGroup"]',
				'notifyUnsignedUser' => 0
			]
		]);

		$user->setEMailAddress('person@test.coop');
		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			'name' => 'test',
			'users' => [
				[
					'email' => 'user01@test.coop'
				]
			],
			'userManager' => $user
		]);

		$this->request
			->withMethod('DELETE')
			->withPath('/sign/register/signature')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'uuid' => $file['uuid'],
				'users' => [
					[
						'email' => 'user01@test.coop'
					]
				]
			]);

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testAccountSignatureEndpointWithSuccess() {
		$user = $this->createUser('username', 'password');
		$user->setEMailAddress('person@test.coop');

		self::$server->setResponseOfPath('/api/v1/cfssl/newcert', new Response(
			file_get_contents(__DIR__ . '/../../fixtures/cfssl/newcert-with-success.json')
		));

		$this->mockConfig([
			'libresign' => [
				'notifyUnsignedUser' => 0,
				'commonName' => 'CommonName',
				'country' => 'Brazil',
				'organization' => 'Organization',
				'organizationUnit' => 'organizationUnit',
				'cfsslUri' => self::$server->getServerRoot() . '/api/v1/cfssl/'
			]
		]);

		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'signPassword' => 'password'
			])
			->withPath('/account/signature');

		$home = $user->getHome();
		$this->assertFileDoesNotExist($home . '/files/LibreSign/signature.pfx');
		$this->assertRequest();
		$this->assertFileExists($home . '/files/LibreSign/signature.pfx');
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testAccountSignatureEndpointWithFailure() {
		$this->createUser('username', 'password');

		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'signPassword' => ''
			])
			->withPath('/account/signature')
			->assertResponseCode(401);

		$this->assertRequest();
	}
}
