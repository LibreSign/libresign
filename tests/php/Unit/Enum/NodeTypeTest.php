<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Enum;

use OCA\Libresign\Enum\NodeType;
use OCA\Libresign\Tests\Unit\TestCase;

final class NodeTypeTest extends TestCase {
	public function testIsEnvelopeReturnsFalseForFileType(): void {
		$this->assertFalse(NodeType::FILE->isEnvelope());
	}

	public function testIsEnvelopeReturnsTrueForEnvelopeType(): void {
		$this->assertTrue(NodeType::ENVELOPE->isEnvelope());
	}
}
