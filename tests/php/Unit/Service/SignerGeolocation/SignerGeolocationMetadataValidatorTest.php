<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\SignerGeolocation;

use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Enum\SignerGeolocationCollectionStatus;
use OCA\Libresign\Enum\SignerGeolocationMode;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\SignerGeolocation\SignerGeolocationMetadataValidator;
use OCA\Libresign\Service\SignerGeolocation\SignerGeolocationPolicyService;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SignerGeolocationMetadataValidatorTest extends TestCase {
	private SignerGeolocationPolicyService&MockObject $policyService;
	private IL10N&MockObject $l10n;

	protected function setUp(): void {
		parent::setUp();
		$this->policyService = $this->createMock(SignerGeolocationPolicyService::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnArgument(0);
	}

	private function getValidator(): SignerGeolocationMetadataValidator {
		return new SignerGeolocationMetadataValidator($this->policyService, $this->l10n);
	}

	public function testNormalizeCollectedGeolocation(): void {
		$result = $this->getValidator()->normalize([
			'status' => 'collected',
			'latitude' => -23.5,
			'longitude' => -46.6,
			'accuracy' => 12.5,
			'timestamp' => 1_700_000_000_000,
		]);

		$this->assertSame([
			'status' => SignerGeolocationCollectionStatus::COLLECTED->value,
			'latitude' => -23.5,
			'longitude' => -46.6,
			'accuracy' => 12.5,
			'timestamp' => 1_700_000_000_000,
		], $result);
	}

	public function testValidateSubmissionRejectsMissingRequiredGeolocation(): void {
		$signRequest = new SignRequest();
		$signRequest->setMetadata([
			SignerGeolocationPolicyService::METADATA_REQUIREMENT_KEY => SignerGeolocationMode::REQUIRED->value,
		]);

		$this->policyService
			->method('getFrozenRequirement')
			->willReturn(SignerGeolocationMode::REQUIRED);

		$this->expectException(LibresignException::class);
		$this->getValidator()->validateSubmission($signRequest, null);
	}

	public function testValidateSubmissionAllowsDisabledMissingGeolocation(): void {
		$signRequest = new SignRequest();
		$signRequest->setMetadata([
			SignerGeolocationPolicyService::METADATA_REQUIREMENT_KEY => SignerGeolocationMode::DISABLED->value,
		]);

		$this->policyService
			->method('getFrozenRequirement')
			->willReturn(SignerGeolocationMode::DISABLED);

		$this->getValidator()->validateSubmission($signRequest, null);
		$this->addToAssertionCount(1);
	}

	public function testValidateSubmissionRejectsGeolocationWhenDisabled(): void {
		$signRequest = new SignRequest();
		$signRequest->setMetadata([
			SignerGeolocationPolicyService::METADATA_REQUIREMENT_KEY => SignerGeolocationMode::DISABLED->value,
		]);

		$this->policyService
			->method('getFrozenRequirement')
			->willReturn(SignerGeolocationMode::DISABLED);

		$this->expectException(LibresignException::class);
		$this->getValidator()->validateSubmission($signRequest, [
			'status' => SignerGeolocationCollectionStatus::COLLECTED->value,
			'latitude' => 1.0,
			'longitude' => 2.0,
		]);
	}

	public function testValidateSubmissionRejectsNonCollectedWhenRequired(): void {
		$signRequest = new SignRequest();
		$signRequest->setMetadata([
			SignerGeolocationPolicyService::METADATA_REQUIREMENT_KEY => SignerGeolocationMode::REQUIRED->value,
		]);

		$this->policyService
			->method('getFrozenRequirement')
			->willReturn(SignerGeolocationMode::REQUIRED);

		$this->expectException(LibresignException::class);
		$this->getValidator()->validateSubmission($signRequest, [
			'status' => SignerGeolocationCollectionStatus::DENIED->value,
		]);
	}

	#[DataProvider('invalidGeolocationPayloadProvider')]
	public function testNormalizeRejectsInvalidPayload(mixed $payload): void {
		$this->expectException(LibresignException::class);
		$this->getValidator()->normalize($payload);
	}

	/** @return iterable<string, array{0: mixed}> */
	public static function invalidGeolocationPayloadProvider(): iterable {
		yield 'non array payload' => ['invalid'];
		yield 'missing status' => [['latitude' => 1.0, 'longitude' => 2.0]];
		yield 'collected without coordinates' => [['status' => 'collected']];
		yield 'latitude above range' => [['status' => 'collected', 'latitude' => 91.0, 'longitude' => 0.0]];
		yield 'latitude below range' => [['status' => 'collected', 'latitude' => -91.0, 'longitude' => 0.0]];
		yield 'longitude above range' => [['status' => 'collected', 'latitude' => 0.0, 'longitude' => 181.0]];
		yield 'longitude below range' => [['status' => 'collected', 'latitude' => 0.0, 'longitude' => -181.0]];
		yield 'invalid latitude type' => [['status' => 'collected', 'latitude' => 'invalid', 'longitude' => 0.0]];
		yield 'invalid longitude type' => [['status' => 'collected', 'latitude' => 0.0, 'longitude' => 'invalid']];
		yield 'negative accuracy' => [['status' => 'collected', 'latitude' => 0.0, 'longitude' => 0.0, 'accuracy' => -1.0]];
		yield 'invalid accuracy type' => [['status' => 'collected', 'latitude' => 0.0, 'longitude' => 0.0, 'accuracy' => 'invalid']];
		yield 'negative timestamp' => [['status' => 'collected', 'latitude' => 0.0, 'longitude' => 0.0, 'timestamp' => -1]];
		yield 'invalid timestamp type' => [['status' => 'collected', 'latitude' => 0.0, 'longitude' => 0.0, 'timestamp' => 'invalid']];
	}
}
