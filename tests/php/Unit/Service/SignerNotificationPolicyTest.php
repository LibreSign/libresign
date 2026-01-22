<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Service\SignerNotificationPolicy;

final class SignerNotificationPolicyTest extends \OCA\Libresign\Tests\Unit\TestCase {
	public function testReturnsErrorWhenSignerWasNotAssociated(): void {
		$policy = new SignerNotificationPolicy();

		$error = $policy->getValidationError(['email' => 'person@example.com'], []);

		$this->assertSame('not_requested', $error['code']);
		$this->assertSame(['person@example.com'], $error['params']);
	}

	public function testReturnsNullWhenSignerIsAssociatedAndNotSigned(): void {
		$policy = new SignerNotificationPolicy();
		$signRequest = $this->makeSignRequest('Jane Doe', null);
		$index = ['email:person@example.com' => [$signRequest]];

		$error = $policy->getValidationError(['email' => 'person@example.com'], $index);

		$this->assertNull($error);
	}

	public function testReturnsErrorWhenSignerAlreadySigned(): void {
		$policy = new SignerNotificationPolicy();
		$signRequest = $this->makeSignRequest('Jane Doe', new \DateTime('now'));
		$index = ['uid:jane' => [$signRequest]];

		$error = $policy->getValidationError(['uid' => 'jane'], $index);

		$this->assertSame('already_signed', $error['code']);
		$this->assertSame(['Jane Doe'], $error['params']);
	}

	private function makeSignRequest(string $displayName, ?\DateTime $signed): SignRequest {
		$signRequest = new SignRequest();
		$signRequest->setDisplayName($displayName);
		if ($signed !== null) {
			$signRequest->setSigned($signed);
		}
		return $signRequest;
	}
}
