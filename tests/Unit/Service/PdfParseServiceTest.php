<?php

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\PdfParserService;
use OCP\Files\File;
use OCP\ITempManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
final class PdfParseServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	/** @var ITempManager */
	private $tempManager;
	/** @var LoggerInterface|MockObject */
	private $loggerInterface;

	public function setUp(): void {
		parent::setUp();
		$this->tempManager = \OC::$server->get(ITempManager::class);
		$this->loggerInterface = $this->createMock(LoggerInterface::class);
	}

	private function getService(): PdfParserService {
		return new PdfParserService(
			$this->tempManager,
			$this->loggerInterface
		);
	}

	/**
	 * @dataProvider dataGetMetadataWithFail
	 */
	public function testGetMetadataWithFail(string $path, string $errorMessage): void {
		$this->expectException(LibresignException::class);
		$this->expectErrorMessageMatches($errorMessage);
		$file = $this->createMock(File::class);
		if (file_exists($path)) {
			$file->method('getContent')
				->willReturn(file_get_contents($path));
		}
		$this->getService()->getMetadata($file);
	}

	public function dataGetMetadataWithFail(): array {
		return [
			['/fail', '/Empty file/'],
			['README.md', '/Impossible get metadata/'],
		];
	}

	/**
	 * @dataProvider providerGetMetadataWithSuccess
	 */
	public function testGetMetadataWithSuccess(string $path, array $expected): void {
		$file = $this->createMock(File::class);
		$file->method('getContent')
			->willReturn(file_get_contents($path));
		$actual = $this->getService()->getMetadata($file);
		$this->assertEquals($expected, $actual);
	}

	public function providerGetMetadataWithSuccess(): array {
		return [
			[
				'tests/fixtures/small_valid.pdf',
				[
					'p' => 1,
					'd' => [
						['w' => 595.276, 'h' => 841.89],
					],
				]
			],
			[
				'tests/fixtures/small_valid-signed.pdf',
				[
					'p' => 1,
					'd' => [
						['w' => 595.276, 'h' => 841.89],
					],
				]
			],
		];
	}
}
