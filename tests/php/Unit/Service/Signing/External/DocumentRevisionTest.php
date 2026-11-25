<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Signing\External;

use OCA\Libresign\Service\Signing\External\DocumentRevision;
use OCP\Files\File;
use PHPUnit\Framework\TestCase;

final class DocumentRevisionTest extends TestCase {
	public function testSameRevisionAndContentCanBePublished(): void {
		$file = $this->createMock(File::class);
		$file->method('getEtag')->willReturn('etag-1');
		$file->method('getContent')->willReturn('%PDF-1.7 bytes');
		(new DocumentRevision($file, '%PDF-1.7 bytes', 'etag-1'))->assertUnchanged();
		$this->addToAssertionCount(1);
	}

	public function testChangedRevisionRejectsPublication(): void {
		$file = $this->createMock(File::class);
		$file->method('getEtag')->willReturn('etag-2');
		$file->expects($this->never())->method('getContent');
		$this->expectException(\DomainException::class);
		(new DocumentRevision($file, '%PDF-1.7 bytes', 'etag-1'))->assertUnchanged();
	}

	public function testChangedContentWithSameRevisionRejectsPublication(): void {
		$file = $this->createMock(File::class);
		$file->method('getEtag')->willReturn('etag-1');
		$file->method('getContent')->willReturn('%PDF-1.7 altered');
		$this->expectException(\DomainException::class);
		(new DocumentRevision($file, '%PDF-1.7 bytes', 'etag-1'))->assertUnchanged();
	}
}
