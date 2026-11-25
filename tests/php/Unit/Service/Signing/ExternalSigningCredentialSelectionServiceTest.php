<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Signing;

use OCA\Libresign\Service\Signing\ExternalSigningCredentialSelectionService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Exercise credential/profile decisions without creating a fake Nextcloud
 * identity, persistence layer or CSC HTTP client.
 */
final class ExternalSigningCredentialSelectionServiceTest extends TestCase {
	/** @return list<array<string, mixed>> */
	private static function credentials(): array {
		return [
			[
				'id' => 'credential-a',
				'certificateSha256' => str_repeat('a', 64),
				'profiles' => ['pdf-basic'],
			],
			[
				'id' => 'credential-b',
				'certificateSha256' => str_repeat('b', 64),
				'profiles' => ['pdf-basic', 'pades-baseline-b'],
			],
		];
	}

	public function testSelectsTheExactEligibleCredentialAndProfile(): void {
		$choice = ExternalSigningCredentialSelectionService::requireEligibleCredential(
			self::credentials(), 'credential-b', 'pades-baseline-b',
		);
		self::assertSame('credential-b', $choice['id']);
		self::assertSame(str_repeat('b', 64), $choice['certificateSha256']);
		self::assertSame(['pdf-basic', 'pades-baseline-b'], $choice['profiles']);
	}

	/** @return array<string, array{list<array<string, mixed>>, string, string}> */
	public static function forbiddenChoices(): array {
		$valid = self::credentials();
		$missingProfiles = [['id' => 'credential-b', 'certificateSha256' => str_repeat('b', 64)]];
		$invalidFingerprint = [['id' => 'credential-b', 'certificateSha256' => 123, 'profiles' => ['pdf-basic']]];
		return [
			'nonexistent credential' => [$valid, 'credential-c', 'pdf-basic'],
			'wrong credential case' => [$valid, 'CREDENTIAL-B', 'pdf-basic'],
			'profile not enabled for credential' => [$valid, 'credential-a', 'pades-baseline-b'],
			'unknown profile' => [$valid, 'credential-b', 'unknown-profile'],
			'missing profile list' => [$missingProfiles, 'credential-b', 'pdf-basic'],
			'missing valid certificate fingerprint' => [$invalidFingerprint, 'credential-b', 'pdf-basic'],
			'empty discovered credentials' => [[], 'credential-b', 'pdf-basic'],
		];
	}

	#[DataProvider('forbiddenChoices')]
	public function testRejectsUnlistedCredentialsOrProfiles(
		array $choices, string $credentialId, string $profile,
	): void {
		$this->expectException(\DomainException::class);
		$this->expectExceptionMessage('Selected CSC credential or PDF profile is not eligible');
		ExternalSigningCredentialSelectionService::requireEligibleCredential($choices, $credentialId, $profile);
	}
}
