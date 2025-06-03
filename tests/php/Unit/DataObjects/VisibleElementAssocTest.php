<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\DataObjects\VisibleElementAssoc;
use OCA\Libresign\Db\FileElement;
use PHPUnit\Framework\Attributes\DataProvider;

final class VisibleElementAssocTest extends \OCA\Libresign\Tests\Unit\TestCase {
	public function testIfIsTheSameFileElement(): void {
		$fileElement = new FileElement();
		$fileElement->setId(1);
		$visibleElementAssoc = new VisibleElementAssoc($fileElement, '');
		$this->assertSame($fileElement, $visibleElementAssoc->getFileElement());
	}

	#[DataProvider('tempFileProvider')]
	public function testTempFile(string $tempFile): void {
		$fileElement = new FileElement();
		$visibleElementAssoc = new VisibleElementAssoc($fileElement, $tempFile);
		$this->assertSame($tempFile, $visibleElementAssoc->getTempFile());
	}

	public static function tempFileProvider(): array {
		return [
			[''],
			['/tmp/somefile'],
			['/tmp/somefile.txt'],
			['/tmp/somefile.pdf'],
			['/tmp/somefile.docx'],
			['/tmp/somefile.odt'],
			['/tmp/somefile.pptx'],
			['/tmp/somefile.xlsx'],
		];
	}
}
