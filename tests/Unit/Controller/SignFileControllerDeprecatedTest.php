<?php

namespace OCA\Libresign\Tests\Unit\Controller;

use OCA\Libresign\Controller\SignFileDeprecatedController;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Handler\JLibresignHandler;
use OCA\Libresign\Handler\PkcsHandler;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\MailService;
use OCA\Libresign\Service\SignFileService;
use OCA\Libresign\Tests\Unit\TestCase;
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

final class SignFileControllerDeprecatedTest extends TestCase {
	use ProphecyTrait;
	public function testSignFileWithSuccess() {
		$request = $this->prophesize(IRequest::class);
		$fileUserMapper = $this->prophesize(FileUserMapper::class);
		$fileMapper = $this->prophesize(FileMapper::class);
		$root = $this->createMock(IRootFolder::class);
		$pkcsHandler = $this->createMock(PkcsHandler::class);
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

		$controller = new SignFileDeprecatedController(
			$request->reveal(),
			$l10n,
			$fileUserMapper->reveal(),
			$fileMapper->reveal(),
			$root,
			$pkcsHandler,
			$userSession,
			$accountService,
			$signFile,
			$libresignHandler,
			$mail,
			$logger,
			$config
		);

		$result = $controller->sign($inputFilePath, $outputFolderPath, $certificatePath, $password);

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
		$pkcsHandler = $this->createMock(PkcsHandler::class);
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

		$controller = new SignFileDeprecatedController(
			$request->reveal(),
			$l10n,
			$fileUserMapper->reveal(),
			$fileMapper->reveal(),
			$root,
			$pkcsHandler,
			$userSession,
			$accountService,
			$signFile,
			$libresignHandler,
			$mail,
			$logger,
			$config
		);

		$result = $controller->sign($inputFilePath, $outputFolderPath, $certificatePath, $password);

		static::assertSame(["parameter '{$paramenterMissing}' is required!"], $result->getData()['errors']);
		static::assertSame(422, $result->getStatus());
	}
}
