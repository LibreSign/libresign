<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Signing\External;

use OCA\Libresign\Service\Signing\External\ImmutableArtifactStore;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

final class ImmutableArtifactStoreTest extends TestCase {
	private const SESSION = '0123456789abcdef0123456789abcdef';

	public function testExistingMatchingArtifactIsReusedWithoutOverwrite(): void {
		$folder = $this->createMock(Folder::class);
		$existing = $this->createMock(File::class);
		$existing->method('getContent')->willReturn('signed bytes');
		$folder->method('nodeExists')->willReturn(true);
		$folder->method('get')->willReturn($existing);
		$folder->expects($this->never())->method('newFile');
		$source = $this->createMock(File::class);
		$source->method('getName')->willReturn('source.pdf');
		$source->method('getParent')->willReturn($folder);
		$userSession = $this->createMock(IUserSession::class);
		$userSession->expects($this->never())->method('getUser');
		$created = (new ImmutableArtifactStore($userSession))->ensure(
			$source, self::SESSION, 23, 42, 'signed bytes', hash('sha256', 'signed bytes'),
		);
		self::assertSame($existing, $created);
	}

	public function testExistingDifferentArtifactIsNeverOverwritten(): void {
		$folder = $this->createMock(Folder::class);
		$existing = $this->createMock(File::class);
		$existing->method('getContent')->willReturn('different bytes');
		$folder->method('nodeExists')->willReturn(true);
		$folder->method('get')->willReturn($existing);
		$folder->expects($this->never())->method('newFile');
		$source = $this->createMock(File::class);
		$source->method('getName')->willReturn('source.pdf');
		$source->method('getParent')->willReturn($folder);
		$this->expectException(\DomainException::class);
		(new ImmutableArtifactStore($this->createMock(IUserSession::class)))->ensure(
			$source, self::SESSION, 23, 42, 'signed bytes', hash('sha256', 'signed bytes'),
		);
	}
}
