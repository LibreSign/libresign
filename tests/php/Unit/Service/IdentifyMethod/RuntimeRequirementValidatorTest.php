<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\IdentifyMethod;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethod\RuntimeRequirementValidator;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Policy\Provider\IdentifyMethods\IdentifyMethodsPolicy;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class RuntimeRequirementValidatorTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IdentifyMethodService&MockObject $identifyMethodService;
	private FileMapper&MockObject $fileMapper;
	private IL10N&MockObject $l10n;

	public function setUp(): void {
		parent::setUp();
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnArgument(0);
	}

	private function getValidator(): RuntimeRequirementValidator {
		return new RuntimeRequirementValidator(
			$this->identifyMethodService,
			$this->fileMapper,
			$this->l10n,
		);
	}

	#[DataProvider('provideRequiredFactorsScenarios')]
	public function testValidateRequiredFactorsCompletedWithDataProvider(
		int $requiredFactors,
		int $identifiedRequiredFactors,
		bool $shouldThrow,
	): void {
		$summary = [
			'requiredFactors' => $requiredFactors,
			'identifiedRequiredFactors' => $identifiedRequiredFactors,
		];

		if ($shouldThrow) {
			$this->expectException(LibresignException::class);
		}

		$this->getValidator()->validateRequiredFactorsCompleted($summary);
		$this->assertTrue(true);
	}

	public static function provideRequiredFactorsScenarios(): array {
		return [
			'all required identified' => [1, 1, false],
			'all 2 required identified' => [2, 2, false],
			'no required factors' => [0, 0, false],
			'one missing from two' => [2, 1, true],
			'none identified from two' => [2, 0, true],
		];
	}

	#[DataProvider('provideMinimumFactorsScenarios')]
	public function testValidateMinimumFactorsCompletedWithDataProvider(
		int $requiredFactors,
		int $identifiedFactors,
		int $minimum,
		bool $shouldThrow,
	): void {
		$summary = [
			'requiredFactors' => $requiredFactors,
			'identifiedFactors' => $identifiedFactors,
		];

		if ($shouldThrow) {
			$this->expectException(LibresignException::class);
		}

		$this->getValidator()->validateMinimumFactorsCompleted($summary, $minimum);
		$this->assertTrue(true);
	}

	public static function provideMinimumFactorsScenarios(): array {
		return [
			'required only, no minimum' => [1, 1, 1, false],
			'required + minimum met' => [1, 2, 2, false],
			'high minimum not met' => [0, 1, 3, true],
			'minimum equals required' => [2, 2, 2, false],
			'minimum exceeds identified' => [1, 2, 3, true],
		];
	}

	#[DataProvider('provideMinimumResolutionScenarios')]
	public function testResolveMinimumFromSettingsListWithDataProvider(
		array $settings,
		array $methodSet,
		?int $expectedMinimum,
	): void {
		$result = $this->getValidator()->resolveMinimumFromSettingsList($settings, $methodSet);
		$this->assertSame($expectedMinimum, $result);
	}

	public static function provideMinimumResolutionScenarios(): array {
		return [
			'empty settings' => [[], [], null],
			'single valid minimum' => [
				[
					['name' => 'email', 'minimumTotalVerifiedFactors' => 2],
				],
				['email' => true],
				2,
			],
			'multiple settings, max wins' => [
				[
					['name' => 'email', 'minimumTotalVerifiedFactors' => 2],
					['name' => 'sms', 'minimumTotalVerifiedFactors' => 3],
				],
				['email' => true, 'sms' => true],
				3,
			],
			'method not in set ignored' => [
				[
					['name' => 'phone', 'minimumTotalVerifiedFactors' => 5],
				],
				['email' => true],
				null,
			],
			'invalid minimum value ignored' => [
				[
					['name' => 'email', 'minimumTotalVerifiedFactors' => 0],
					['name' => 'sms', 'minimumTotalVerifiedFactors' => 2],
				],
				['email' => true, 'sms' => true],
				2,
			],
			'non-numeric minimum ignored' => [
				[
					['name' => 'email', 'minimumTotalVerifiedFactors' => 'invalid'],
				],
				['email' => true],
				null,
			],
		];
	}

	private function createIdentifyMethodEntity(
		string $key,
		string $value,
		bool $mandatory = true,
		bool $identified = false,
	): IdentifyMethod {
		$entity = new IdentifyMethod();
		$entity->setIdentifierKey($key);
		$entity->setIdentifierValue($value);
		$entity->setMandatory($mandatory ? 1 : 0);
		if ($identified) {
			$entity->setIdentifiedAtDate(new \DateTime('now', new \DateTimeZone('UTC')));
		}
		return $entity;
	}

	private function createSignRequest(int $id, ?int $fileId = null): SignRequest {
		$request = new SignRequest();
		$request->setId($id);
		if ($fileId !== null) {
			$request->setFileId($fileId);
		}
		return $request;
	}

	private function mockIdentifyMethods(int $signRequestId, array $methodsByName): void {
		$this->identifyMethodService
			->method('getIdentifyMethodsFromSignRequestId')
			->with($signRequestId)
			->willReturn($methodsByName);
	}

	private function mockIdentifyMethodsSettings(array $settings): void {
		$this->identifyMethodService
			->method('getIdentifyMethodsSettings')
			->willReturn($settings);
	}

	private function mockFileWithPolicySnapshot(int $fileId, array $snapshotEffectiveValue): void {
		$file = new File();
		$file->setMetadata([
			'policy_snapshot' => [
				IdentifyMethodsPolicy::KEY => [
					'effectiveValue' => $snapshotEffectiveValue,
				],
			],
		]);
		$this->fileMapper->method('getById')->with($fileId)->willReturn($file);
	}

	public function testReturnsEarlyWhenSignRequestHasNoId(): void {
		$signRequest = new SignRequest();

		$this->identifyMethodService
			->expects($this->never())
			->method('getIdentifyMethodsFromSignRequestId');

		$this->getValidator()->validate($signRequest);
		$this->assertTrue(true);
	}

	public function testReturnsEarlyWhenSignRequestHasNoMethods(): void {
		$this->mockIdentifyMethods(9, []);

		$this->getValidator()->validate($this->createSignRequest(9));
		$this->assertTrue(true);
	}

	public function testReturnsEarlyWhenOnlyRequiredFactorsExistAndNoOptional(): void {
		$emailEntity = $this->createIdentifyMethodEntity(
			IdentifyMethodService::IDENTIFY_EMAIL,
			'signer@example.com',
			mandatory: true,
			identified: true,
		);

		$this->mockIdentifyMethods(14, [
			IdentifyMethodService::IDENTIFY_EMAIL => [$this->methodMock($emailEntity)],
		]);

		$this->identifyMethodService
			->expects($this->never())
			->method('getIdentifyMethodsSettings');

		$this->getValidator()->validate($this->createSignRequest(14));
		$this->assertTrue(true);
	}

	public function testFailsWhenRequiredFactorsAreNotAllIdentified(): void {
		$emailEntity = $this->createIdentifyMethodEntity(
			IdentifyMethodService::IDENTIFY_EMAIL,
			'signer@example.com',
			mandatory: true,
			identified: true,
		);

		$accountEntity = $this->createIdentifyMethodEntity(
			IdentifyMethodService::IDENTIFY_ACCOUNT,
			'signer',
			mandatory: true,
			identified: false,
		);

		$this->mockIdentifyMethods(10, [
			IdentifyMethodService::IDENTIFY_EMAIL => [$this->methodMock($emailEntity)],
			IdentifyMethodService::IDENTIFY_ACCOUNT => [$this->methodMock($accountEntity)],
		]);

		$this->expectException(LibresignException::class);

		$this->getValidator()->validate($this->createSignRequest(10));
	}

	public function testFailsWhenOptionalFactorsExistAndMinimumIsNotMet(): void {
		$emailEntity = $this->createIdentifyMethodEntity(
			IdentifyMethodService::IDENTIFY_EMAIL,
			'signer@example.com',
			mandatory: true,
			identified: true,
		);

		$smsEntity = $this->createIdentifyMethodEntity(
			IdentifyMethodService::IDENTIFY_SMS,
			'+5511999999999',
			mandatory: false,
			identified: false,
		);

		$this->mockIdentifyMethods(11, [
			IdentifyMethodService::IDENTIFY_EMAIL => [$this->methodMock($emailEntity)],
			IdentifyMethodService::IDENTIFY_SMS => [$this->methodMock($smsEntity)],
		]);
		$this->mockIdentifyMethodsSettings([
			['name' => IdentifyMethodService::IDENTIFY_SMS, 'minimumTotalVerifiedFactors' => 2],
		]);

		$this->expectException(LibresignException::class);
		$this->getValidator()->validate($this->createSignRequest(11));
	}

	public function testPassesWhenMinimumIsMet(): void {
		$emailEntity = $this->createIdentifyMethodEntity(
			IdentifyMethodService::IDENTIFY_EMAIL,
			'signer@example.com',
			mandatory: true,
			identified: true,
		);

		$smsEntity = $this->createIdentifyMethodEntity(
			IdentifyMethodService::IDENTIFY_SMS,
			'+5511999999999',
			mandatory: false,
			identified: true,
		);

		$this->mockIdentifyMethods(12, [
			IdentifyMethodService::IDENTIFY_EMAIL => [$this->methodMock($emailEntity)],
			IdentifyMethodService::IDENTIFY_SMS => [$this->methodMock($smsEntity)],
		]);
		$this->mockIdentifyMethodsSettings([
			['name' => IdentifyMethodService::IDENTIFY_SMS, 'minimumTotalVerifiedFactors' => 2],
		]);

		$this->getValidator()->validate($this->createSignRequest(12));
		$this->assertTrue(true);
	}

	public function testSnapshotTakesPrecedenceOverLiveSettingsForMinimum(): void {
		$this->mockFileWithPolicySnapshot(501, [
			[
				'name' => IdentifyMethodService::IDENTIFY_SMS,
				'minimumTotalVerifiedFactors' => 2,
			],
		]);

		$emailEntity = $this->createIdentifyMethodEntity(
			IdentifyMethodService::IDENTIFY_EMAIL,
			'signer@example.com',
			mandatory: true,
			identified: true,
		);

		$smsEntity = $this->createIdentifyMethodEntity(
			IdentifyMethodService::IDENTIFY_SMS,
			'+5511999999999',
			mandatory: false,
			identified: true,
		);

		$this->mockIdentifyMethods(13, [
			IdentifyMethodService::IDENTIFY_EMAIL => [$this->methodMock($emailEntity)],
			IdentifyMethodService::IDENTIFY_SMS => [$this->methodMock($smsEntity)],
		]);

		$this->mockIdentifyMethodsSettings([
			['name' => IdentifyMethodService::IDENTIFY_SMS, 'minimumTotalVerifiedFactors' => 3],
		]);

		$this->getValidator()->validate($this->createSignRequest(13, 501));
		$this->assertTrue(true);
	}

	public function testFallsBackToLiveSettingsWhenSnapshotCannotBeLoaded(): void {
		$emailEntity = $this->createIdentifyMethodEntity(
			IdentifyMethodService::IDENTIFY_EMAIL,
			'signer@example.com',
			mandatory: true,
			identified: true,
		);

		$smsEntity = $this->createIdentifyMethodEntity(
			IdentifyMethodService::IDENTIFY_SMS,
			'+5511999999999',
			mandatory: false,
			identified: true,
		);

		$this->mockIdentifyMethods(15, [
			IdentifyMethodService::IDENTIFY_EMAIL => [$this->methodMock($emailEntity)],
			IdentifyMethodService::IDENTIFY_SMS => [$this->methodMock($smsEntity)],
		]);

		$this->fileMapper
			->method('getById')
			->with(999)
			->willThrowException(new \RuntimeException('file not found'));

		$this->mockIdentifyMethodsSettings([
			['name' => IdentifyMethodService::IDENTIFY_SMS, 'minimumTotalVerifiedFactors' => 2],
		]);

		$this->getValidator()->validate($this->createSignRequest(15, 999));
		$this->assertTrue(true);
	}

	private function methodMock(IdentifyMethod $entity): IIdentifyMethod {
		$identifyMethod = $this->createMock(IIdentifyMethod::class);
		$identifyMethod->method('getEntity')->willReturn($entity);
		return $identifyMethod;
	}
}

