<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\File;

use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\File\MimeService;
use OCP\Files\IMimeTypeDetector;
use PHPUnit\Framework\MockObject\MockObject;

final class MimeServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IMimeTypeDetector|MockObject $mimeTypeDetector;
	private ValidateHelper|MockObject $validateHelper;

	public function setUp(): void {
		parent::setUp();
		$this->mimeTypeDetector = $this->createMock(IMimeTypeDetector::class);
		$this->validateHelper = $this->createMock(ValidateHelper::class);
	}

	private function getService(): MimeService {
		return new MimeService(
			$this->mimeTypeDetector,
			$this->validateHelper,
		);
	}

	public function testGetMimeTypeDetectsFromContent(): void {
		$content = 'test content';
		$expectedMime = 'application/pdf';

		$this->mimeTypeDetector
			->expects($this->once())
			->method('detectString')
			->with($content)
			->willReturn($expectedMime);

		$this->validateHelper
			->expects($this->once())
			->method('validateMimeTypeAcceptedByMime')
			->with($expectedMime);

		$service = $this->getService();
		$result = $service->getMimeType($content);

		$this->assertEquals($expectedMime, $result);
	}

	public function testGetMimeTypeCachesResult(): void {
		$content = 'test content';
		$expectedMime = 'application/pdf';

		$this->mimeTypeDetector
			->expects($this->once())
			->method('detectString')
			->willReturn($expectedMime);

		$this->validateHelper
			->expects($this->once())
			->method('validateMimeTypeAcceptedByMime');

		$service = $this->getService();

		// First call should detect
		$result1 = $service->getMimeType($content);
		// Second call should use cached value
		$result2 = $service->getMimeType($content);

		$this->assertEquals($expectedMime, $result1);
		$this->assertEquals($expectedMime, $result2);
	}

	public function testGetExtensionReturnsPdfForPdfMime(): void {
		$content = 'pdf content';
		$pdfMime = 'application/pdf';

		$this->mimeTypeDetector
			->method('detectString')
			->willReturn($pdfMime);

		$this->mimeTypeDetector
			->method('getAllMappings')
			->willReturn([
				'pdf' => ['application/pdf'],
				'txt' => ['text/plain'],
			]);

		$this->validateHelper
			->method('validateMimeTypeAcceptedByMime');

		$service = $this->getService();
		$extension = $service->getExtension($content);

		$this->assertEquals('pdf', $extension);
	}

	public function testGetExtensionSkipsInternalMappings(): void {
		$content = 'test content';
		$mime = 'application/pdf';

		$this->mimeTypeDetector
			->method('detectString')
			->willReturn($mime);

		$this->mimeTypeDetector
			->method('getAllMappings')
			->willReturn([
				'_internal' => ['application/pdf'],
				'pdf' => ['application/pdf'],
			]);

		$this->validateHelper
			->method('validateMimeTypeAcceptedByMime');

		$service = $this->getService();
		$extension = $service->getExtension($content);

		$this->assertEquals('pdf', $extension);
	}

	public function testGetExtensionReturnsEmptyForUnknownMime(): void {
		$content = 'unknown content';
		$unknownMime = 'application/unknown';

		$this->mimeTypeDetector
			->method('detectString')
			->willReturn($unknownMime);

		$this->mimeTypeDetector
			->method('getAllMappings')
			->willReturn([
				'pdf' => ['application/pdf'],
				'txt' => ['text/plain'],
			]);

		$this->validateHelper
			->method('validateMimeTypeAcceptedByMime');

		$service = $this->getService();
		$extension = $service->getExtension($content);

		$this->assertEquals('', $extension);
	}

	public function testSetMimeTypeValidatesAndSets(): void {
		$mime = 'application/pdf';

		$this->validateHelper
			->expects($this->once())
			->method('validateMimeTypeAcceptedByMime')
			->with($mime);

		$service = $this->getService();
		$service->setMimeType($mime);

		$this->assertEquals($mime, $service->getCurrentMimeType());
	}

	public function testResetClearsCachedMimeType(): void {
		$content = 'test content';
		$mime = 'application/pdf';

		$this->mimeTypeDetector
			->method('detectString')
			->willReturn($mime);

		$this->validateHelper
			->method('validateMimeTypeAcceptedByMime');

		$service = $this->getService();
		$service->getMimeType($content);

		$this->assertEquals($mime, $service->getCurrentMimeType());

		$service->reset();

		$this->assertNull($service->getCurrentMimeType());
	}
}
