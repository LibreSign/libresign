<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

/**
 * Overwrite shell_exec in the OCA\Libresign\Service namespace.
 */
function shell_exec($command) {
	if (\OCA\Libresign\Tests\Unit\Service\PdfParseServiceTest::$disablePdfInfo) {
		return null;
	}
	return \shell_exec($command);
}

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
	private ITempManager $tempManager;
	private LoggerInterface&MockObject $loggerInterface;
	public static $disablePdfInfo = false;

	public function setUp(): void {
		parent::setUp();
		$this->tempManager = \OCP\Server::get(ITempManager::class);
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
		$this->expectExceptionMessageMatches($errorMessage);
		/** @var File|MockObject */
		$file = $this->createMock(File::class);
		if (file_exists($path)) {
			$file->method('getContent')
				->willReturn(file_get_contents($path));
		}
		$this->getService()
			->setFile($file)->getPageDimensions();
	}

	public static function dataGetMetadataWithFail(): array {
		return [
			['/fail', '/Empty file/'],
			['README.md', '/Impossible get metadata/'],
		];
	}

	/**
	 * @dataProvider providerGetMetadataWithSuccess
	 */
	public function testGetMetadataWithSuccess(bool $disablePdfInfo, string $path, array $expected): void {
		self::$disablePdfInfo = $disablePdfInfo;
		/** @var File|MockObject */
		$file = $this->createMock(File::class);
		$file->method('getContent')
			->willReturn(file_get_contents($path));
		$actual = $this->getService()
			->setFile($file)
			->getPageDimensions();
		$this->assertEquals($expected, $actual);
	}

	public static function providerGetMetadataWithSuccess(): array {
		return [
			[
				'disablePdfInfo' => true,
				'tests/php/fixtures/small_valid.pdf',
				[
					'p' => 1,
					'd' => [
						['w' => 595.275590551181, 'h' => 841.889763779528],
					],
				]
			],
			[
				'disablePdfInfo' => true,
				'tests/php/fixtures/small_valid-signed.pdf',
				[
					'p' => 1,
					'd' => [
						['w' => 595.275590551181, 'h' => 841.889763779528],
					],
				]
			],
			[
				'disablePdfInfo' => false,
				'tests/php/fixtures/small_valid.pdf',
				[
					'p' => 1,
					'd' => [
						['w' => 595.276, 'h' => 841.89],
					],
				]
			],
			[
				'disablePdfInfo' => false,
				'tests/php/fixtures/small_valid-signed.pdf',
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
