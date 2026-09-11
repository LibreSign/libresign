<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Enum;

use OCA\Libresign\Enum\ParticipantRole;
use OCA\Libresign\Tests\Unit\TestCase;

final class ParticipantRoleTest extends TestCase {
	public function testFromNullableDefaultsToSigner(): void {
		$this->assertSame(ParticipantRole::SIGNER, ParticipantRole::fromNullable(null));
		$this->assertSame(ParticipantRole::SIGNER, ParticipantRole::fromNullable(''));
	}

	public function testFromNullableParsesKnownRoles(): void {
		$this->assertSame(ParticipantRole::SIGNER, ParticipantRole::fromNullable('signer'));
		$this->assertSame(ParticipantRole::OBSERVER, ParticipantRole::fromNullable('observer'));
	}

	public function testFromNullableThrowsForInvalidValue(): void {
		$this->expectException(\ValueError::class);
		ParticipantRole::fromNullable('invalid-role');
	}

	public function testCanSign(): void {
		$this->assertTrue(ParticipantRole::SIGNER->canSign());
		$this->assertFalse(ParticipantRole::OBSERVER->canSign());
	}
}
