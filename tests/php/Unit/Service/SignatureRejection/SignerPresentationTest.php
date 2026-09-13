<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\SignatureRejection;

use OCA\Libresign\Enum\SignerDisplayStatus;
use OCA\Libresign\Service\SignatureRejection\SignerPresentation;
use PHPUnit\Framework\TestCase;

final class SignerPresentationTest extends TestCase {
	public function testAVisiblePresentationWritesEveryField(): void {
		$presentation = new SignerPresentation(SignerDisplayStatus::REJECTED, 3, 'Rejected', ['rejectedAt' => '2026-09-13T12:00:00+00:00']);

		$result = $presentation->applyTo(['signRequestId' => 1, 'status' => 1, 'statusText' => 'stale']);

		$this->assertEquals([
			'signRequestId' => 1,
			'displayStatus' => 'rejected',
			'statusText' => 'Rejected',
			'status' => 3,
			'rejection' => ['rejectedAt' => '2026-09-13T12:00:00+00:00'],
		], $result);
	}

	public function testARedactedPresentationRemovesWhatMustNotBeDisclosed(): void {
		$presentation = new SignerPresentation(SignerDisplayStatus::NOT_SIGNED, null, 'Not signed', null);

		$result = $presentation->applyTo(['signRequestId' => 1, 'status' => 3, 'statusText' => 'Rejected', 'rejection' => ['rejectedAt' => 'x']]);

		$this->assertEquals(['signRequestId' => 1, 'displayStatus' => 'not_signed', 'statusText' => 'Not signed'], $result);
	}

	public function testTheObjectFormIsEquivalent(): void {
		$signer = new \stdClass();
		$signer->signRequestId = 1;
		$signer->status = 3;
		$signer->rejection = ['rejectedAt' => 'x'];

		(new SignerPresentation(SignerDisplayStatus::NOT_SIGNED, null, 'Not signed', null))->applyToObject($signer);

		$this->assertObjectNotHasProperty('status', $signer);
		$this->assertObjectNotHasProperty('rejection', $signer);
		$this->assertSame('not_signed', $signer->displayStatus);
		$this->assertSame('Not signed', $signer->statusText);

		(new SignerPresentation(SignerDisplayStatus::SIGNED, 2, 'Signed', null))->applyToObject($signer);

		$this->assertSame(2, $signer->status);
		$this->assertSame('signed', $signer->displayStatus);
	}
}
