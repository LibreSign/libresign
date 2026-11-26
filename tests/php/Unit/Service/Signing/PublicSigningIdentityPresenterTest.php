<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Signing;

use OCA\Libresign\Service\Signing\PublicSigningIdentityPresenter;
use OCA\Libresign\Tests\Unit\TestCase;

final class PublicSigningIdentityPresenterTest extends TestCase {
	public function testPublicSigningNeverReturnsRecipientEmailInSignerFields(): void {
		$email = 'guest@example.org';
		$signers = [[
			'email' => $email,
			'displayName' => $email,
			'uid' => 'email:' . $email,
			'identifyMethods' => [
				['method' => 'email', 'value' => $email, 'requirement' => 'required'],
				['method' => 'sms', 'value' => '+5521999999000', 'requirement' => 'optional'],
			],
		]];

		$result = PublicSigningIdentityPresenter::redactSigners($signers);

		self::assertSame('', $result[0]['identifyMethods'][0]['value']);
		self::assertSame('required', $result[0]['identifyMethods'][0]['requirement']);
		self::assertSame('+5521999999000', $result[0]['identifyMethods'][1]['value']);
		self::assertStringContainsString('***', $result[0]['identifyMethods'][0]['maskedValue']);
		self::assertStringNotContainsString($email, json_encode($result, JSON_THROW_ON_ERROR));
		self::assertSame($email, $signers[0]['identifyMethods'][0]['value']);
	}

	public function testEnvelopeSignerEmailsAreRedactedWithoutChangingOtherData(): void {
		$files = [[
			'id' => 20,
			'signers' => [[
				'email' => 'recipient@example.org',
				'identifyMethods' => [['method' => 'email', 'value' => 'recipient@example.org']],
			]],
		]];

		$result = PublicSigningIdentityPresenter::redactEnvelopeFiles($files);

		self::assertSame(20, $result[0]['id']);
		self::assertStringNotContainsString('recipient@example.org', json_encode($result, JSON_THROW_ON_ERROR));
		self::assertSame('', $result[0]['signers'][0]['identifyMethods'][0]['value']);
	}

	public function testSignerUuidValidationResponseRedactsMainAndNestedSignerData(): void {
		$signer = [
			'email' => 'guest@example.org',
			'identifyMethods' => [['method' => 'email', 'value' => 'guest@example.org']],
		];
		$response = [
			'signers' => [$signer],
			'files' => [['id' => 20, 'signers' => [$signer]]],
		];

		$result = PublicSigningIdentityPresenter::redactValidationResponse($response);

		self::assertSame('', $result['signers'][0]['identifyMethods'][0]['value']);
		self::assertSame('', $result['files'][0]['signers'][0]['identifyMethods'][0]['value']);
		self::assertStringNotContainsString('guest@example.org', json_encode($result, JSON_THROW_ON_ERROR));
	}

	public function testInternalAccountAndNonEmailIdentifiersStayAvailable(): void {
		$signers = [[
			'email' => '',
			'displayName' => 'Alice',
			'identifyMethods' => [['method' => 'account', 'value' => 'alice', 'requirement' => 'required']],
		]];

		self::assertSame($signers, PublicSigningIdentityPresenter::redactSigners($signers));
	}
}
