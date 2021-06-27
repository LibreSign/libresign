<?php

namespace OCA\Libresign\Tests\Unit\Controller;

use OCA\Libresign\Controller\SignFileDeprecatedController;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Service\MailService;
use OCA\Libresign\Service\SignFileService;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\Files\File;
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
		$l10n = $this->createMock(IL10N::class);
		$l10n
			->method('t')
			->will($this->returnArgument(0));
		$logger = $this->createMock(LoggerInterface::class);
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

		$signedFile = $this->createMock(File::class);
		$signedFile
			->method('getInternalPath')
			->willReturn('/path/to/someFileSigned');
		$signFile = $this->createMock(SignFileService::class);
		$signFile
			->method('signDeprecated')
			->willReturn($signedFile);

		$mail = $this->createMock(MailService::class);

		$controller = new SignFileDeprecatedController(
			$request->reveal(),
			$l10n,
			$fileUserMapper->reveal(),
			$fileMapper->reveal(),
			$userSession,
			$signFile,
			$mail,
			$logger
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
		$l10n = $this->createMock(IL10N::class);
		$l10n
			->method('t')
			->will($this->returnArgument(0));
		$signFile = $this->createMock(SignFileService::class);
		$logger = $this->createMock(LoggerInterface::class);
		$userSession = $this->createMock(IUserSession::class);
		$mail = $this->createMock(MailService::class);

		$signFile = $this->createMock(SignFileService::class);

		$controller = new SignFileDeprecatedController(
			$request->reveal(),
			$l10n,
			$fileUserMapper->reveal(),
			$fileMapper->reveal(),
			$userSession,
			$signFile,
			$mail,
			$logger
		);

		$result = $controller->sign($inputFilePath, $outputFolderPath, $certificatePath, $password);

		static::assertSame(["parameter '{$paramenterMissing}' is required!"], $result->getData()['errors']);
		static::assertSame(422, $result->getStatus());
	}
}
