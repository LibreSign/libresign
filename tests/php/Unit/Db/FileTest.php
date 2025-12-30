<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Db;

use OCA\Libresign\Db\File;
use OCA\Libresign\Enum\NodeType;
use OCA\Libresign\Enum\SignatureFlow;
use OCA\Libresign\Tests\Unit\TestCase;

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
}
