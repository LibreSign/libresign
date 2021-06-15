<?php

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Handler\JLibresignHandler;
use OCA\Libresign\Service\LibresignService;
use OCA\Libresign\Storage\ClientStorage;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @internal
 */
final class LibresignServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	use ProphecyTrait;
	public function testSignFile() {
		$inputFilePath = '/path/to/someInputFilePath';
		$outputFolderPath = '/path/to/someOutputFolderPath';
		$certificatePath = '/path/to/someCertificatePath';
		$password = 'somePassword';
		$libresignHandler = $this->prophesize(JLibresignHandler::class);
		$clientStorage = $this->prophesize(ClientStorage::class);

		$filename = 'someFilename';
		$content = 'someContent';

		$clientStorage->getFile($inputFilePath)
			->shouldBeCalled()
		;

		$clientStorage->getFile($certificatePath)
			->shouldBeCalled()
		;

		$libresignHandler->signExistingFile(\Prophecy\Argument::any(), \Prophecy\Argument::any(), $password)
			->shouldBeCalled()
			->willReturn([$filename, $content])
		;

		$clientStorage->createFolder($outputFolderPath)
			->shouldBeCalled()
		;

		$clientStorage->saveFile($filename, $content, \Prophecy\Argument::any())
			->shouldBeCalled()
		;

		$service = new LibresignService($libresignHandler->reveal(), $clientStorage->reveal());
		$service->sign($inputFilePath, $outputFolderPath, $certificatePath, $password);
	}
}
