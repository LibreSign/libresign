<?php

namespace OCA\Libresign\Tests\Unit\Controller;

use OC\Files\Node\File;
use OC\Files\Node\Folder;
use OCA\Libresign\Controller\LibresignController;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Handler\JLibresignHandler;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\LibresignService;
use OCA\Libresign\Service\WebhookService;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;

/**
 * @internal
 * @coversNothing
 */
final class LibresignControllerTest extends TestCase {
	use ProphecyTrait;
	public function testSignFile() {
		$userId = 'john';
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
		$webhook = $this->createMock(WebhookService::class);
		$logger = $this->createMock(LoggerInterface::class);
		$file = $this->prophesize(File::class);
		$file->getInternalPath()->willReturn("/path/to/someFileSigned");
		$config = $this->createMock(IConfig::class);
		
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
			$userId
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
		$userId = 'john';
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
			$userId
		);

		$result = $controller->signDeprecated($inputFilePath, $outputFolderPath, $certificatePath, $password);

		static::assertSame(["parameter '{$paramenterMissing}' is required!"], $result->getData()['errors']);
		static::assertSame(422, $result->getStatus());
	}
}
