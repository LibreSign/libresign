<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use bovigo\vfs\vfsStream;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\TemporarySignatureImageService;
use OCP\IL10N;
use OCP\ITempManager;

final class TemporarySignatureImageServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private ITempManager $tempManager;
	private TemporarySignatureImageService $images;

	protected function setUp(): void {
		parent::setUp();
		vfsStream::setup('home');
		$this->tempManager = $this->createMock(ITempManager::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$this->images = new TemporarySignatureImageService($this->tempManager, $l10n);
	}

	public function testCreatesReadableTemporaryImage(): void {
		$this->tempManager->method('getTemporaryFile')->willReturn('vfs://home/signature.png');

		$path = $this->images->create(277524, 'synthetic image');

		$this->assertTrue($this->images->isAvailable($path));
		$this->assertSame('synthetic image', file_get_contents($path));
	}

	public function testRejectsTemporaryFileAllocationFailure(): void {
		$this->tempManager->method('getTemporaryFile')->willReturn(false);
		$this->expectException(LibresignException::class);

		$this->images->create(277524, 'synthetic image');
	}

	public function testRejectsUnwritableTemporaryPath(): void {
		$this->tempManager->method('getTemporaryFile')->willReturn('vfs://home');
		$this->expectException(LibresignException::class);

		$this->images->create(277524, 'synthetic image');
	}

	public function testRejectsMissingImageBeforeSigning(): void {
		$this->expectException(LibresignException::class);

		$this->images->requireAvailable('vfs://home/missing.png');
	}

	public function testAllowsImageFreeSigning(): void {
		$this->images->requireAvailable('');

		$this->assertFalse($this->images->isAvailable(''));
	}
}
