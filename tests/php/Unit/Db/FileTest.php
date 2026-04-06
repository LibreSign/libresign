<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Db;

use OCA\Libresign\Db\File;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\NodeType;
use OCA\Libresign\Enum\SignatureFlow;
use OCA\Libresign\Tests\Unit\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class FileTest extends TestCase {
	private File $file;

	public function setUp(): void {
		parent::setUp();
		$this->file = new File();
	}

	public function testGetSignatureFlowEnumConvertsFromInt(): void {
		$this->file->setSignatureFlow(1);
		$this->assertEquals(SignatureFlow::PARALLEL, $this->file->getSignatureFlowEnum());

		$this->file->setSignatureFlow(2);
		$this->assertEquals(SignatureFlow::ORDERED_NUMERIC, $this->file->getSignatureFlowEnum());
	}

	public function testSetSignatureFlowEnumConvertsToInt(): void {
		$this->file->setSignatureFlowEnum(SignatureFlow::PARALLEL);
		$this->assertEquals(1, $this->file->getSignatureFlow());

		$this->file->setSignatureFlowEnum(SignatureFlow::ORDERED_NUMERIC);
		$this->assertEquals(2, $this->file->getSignatureFlow());
	}

	public function testIsEnvelopeReturnsFalseByDefault(): void {
		$this->assertFalse($this->file->isEnvelope());
	}

	public function testIsEnvelopeReturnsTrueWhenNodeTypeIsEnvelope(): void {
		$this->file->setNodeTypeEnum(NodeType::ENVELOPE);
		$this->assertTrue($this->file->isEnvelope());
	}

	public function testHasParentReturnsFalseByDefault(): void {
		$this->assertFalse($this->file->hasParent());
	}

	public function testHasParentReturnsTrueWhenParentFileIdIsSet(): void {
		$this->file->setParentFileId(123);
		$this->assertTrue($this->file->hasParent());
	}

	public function testGetStatusReturnsDraftWhenInternalStatusIsNull(): void {
		$reflectionProperty = new \ReflectionProperty($this->file, 'status');
		$reflectionProperty->setAccessible(true);
		$reflectionProperty->setValue($this->file, null);

		$this->assertSame(FileStatus::DRAFT->value, $this->file->getStatus());
	}

	public function testSetStatusRejectsInvalidStatusCode(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid file status code: 999');

		$this->file->setStatus(999);
	}

	#[DataProvider('provideKnownFileStatuses')]
	public function testSetStatusAcceptsKnownFileStatusCodes(FileStatus $status): void {
		$this->file->setStatus($status->value);

		$this->assertSame($status->value, $this->file->getStatus());
	}

	public static function provideKnownFileStatuses(): array {
		return array_map(
			static fn (FileStatus $status): array => [$status],
			FileStatus::cases()
		);
	}
}
