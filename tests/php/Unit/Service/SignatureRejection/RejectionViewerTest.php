<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\SignatureRejection;

use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Enum\ParticipantRole;
use OCA\Libresign\Enum\SignatureRejectionVisibility;
use OCA\Libresign\Service\SignatureRejection\RejectionViewer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RejectionViewerTest extends TestCase {
	private static function signRequest(int $id, ParticipantRole $role = ParticipantRole::SIGNER): SignRequest {
		$signRequest = new SignRequest();
		$signRequest->setId($id);
		$signRequest->setParticipantRole($role->value);
		return $signRequest;
	}

	/**
	 * @param list<array{0: int, 1: ParticipantRole}> $ownSignRequests
	 */
	#[DataProvider('provideAudiences')]
	public function testTheAudienceIsTheNarrowestTheViewerBelongsTo(bool $requester, array $ownSignRequests, SignatureRejectionVisibility $expected): void {
		$viewer = new RejectionViewer(
			$requester,
			array_map(static fn (array $own): SignRequest => self::signRequest(...$own), $ownSignRequests),
		);

		$this->assertSame($expected, $viewer->getAudience());
	}

	/**
	 * @return iterable<string, array{0: bool, 1: list<array{0: int, 1: ParticipantRole}>, 2: SignatureRejectionVisibility}>
	 */
	public static function provideAudiences(): iterable {
		yield 'the requester' => [true, [], SignatureRejectionVisibility::REQUESTER];
		yield 'a requester who also signs' => [true, [[1, ParticipantRole::SIGNER]], SignatureRejectionVisibility::REQUESTER];
		yield 'a signer' => [false, [[1, ParticipantRole::SIGNER]], SignatureRejectionVisibility::PARTICIPANTS];
		yield 'a signer who also observes' => [false, [[1, ParticipantRole::OBSERVER], [2, ParticipantRole::SIGNER]], SignatureRejectionVisibility::PARTICIPANTS];
		yield 'an observer, who takes no part in signing' => [false, [[1, ParticipantRole::OBSERVER]], SignatureRejectionVisibility::PUBLIC];
		yield 'anybody else' => [false, [], SignatureRejectionVisibility::PUBLIC];
	}

	public function testASignerIsPrivilegedOnlyForTheirOwnEntries(): void {
		$viewer = new RejectionViewer(false, [self::signRequest(1)]);

		$this->assertTrue($viewer->isPrivilegedFor(self::signRequest(1)));
		$this->assertFalse($viewer->isPrivilegedFor(self::signRequest(2)));
	}

	public function testTheRequesterIsPrivilegedForEveryEntry(): void {
		$viewer = new RejectionViewer(true, []);

		$this->assertTrue($viewer->isPrivilegedFor(self::signRequest(1)));
		$this->assertTrue($viewer->isPrivilegedFor(self::signRequest(2)));
	}
}
