<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethod as IdentifyMethodEntity;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequest as SignRequestEntity;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Service\ActiveSigningsService;
use OCA\Libresign\Service\IdentifyMethodService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ActiveSigningsServiceTest extends TestCase {
	private FileMapper&MockObject $fileMapper;
	private SignRequestMapper&MockObject $signRequestMapper;
	private IdentifyMethodMapper&MockObject $identifyMethodMapper;
	private ActiveSigningsService $service;
	/** @var array<int, list<FileEntity>> */
	private array $filesByStatus = [];
	/** @var array<int, list<SignRequestEntity>> */
	private array $signRequestsByFileId = [];
	/** @var array<int, list<IdentifyMethodEntity>> */
	private array $identifyMethodsBySignRequestId = [];

	protected function setUp(): void {
		$this->fileMapper = $this->createStub(FileMapper::class);
		$this->fileMapper
			->method('findByStatus')
			->willReturnCallback(fn (int $status): array => $this->filesByStatus[$status] ?? []);

		$this->signRequestMapper = $this->createStub(SignRequestMapper::class);
		$this->signRequestMapper
			->method('getByFileId')
			->willReturnCallback(fn (int $fileId): array => $this->signRequestsByFileId[$fileId] ?? []);

		$this->identifyMethodMapper = $this->createStub(IdentifyMethodMapper::class);
		$this->identifyMethodMapper
			->method('getIdentifyMethodsFromSignRequestId')
			->willReturnCallback(fn (int $signRequestId): array => $this->identifyMethodsBySignRequestId[$signRequestId] ?? []);

		$this->service = new ActiveSigningsService(
			$this->fileMapper,
			$this->signRequestMapper,
			$this->identifyMethodMapper,
		);
	}

	#[DataProvider('provideActiveSigningScenarios')]
	public function testGetActiveSignings(
		array $files,
		array $signRequestsByFileId,
		array $identifyMethodsBySignRequestId,
		array $expected,
	): void {
		$this->filesByStatus[FileStatus::SIGNING_IN_PROGRESS->value] = array_map(
			fn (array $definition): FileEntity => $this->buildFile($definition),
			$files,
		);
		$this->signRequestsByFileId = $this->buildSignRequestsByFileId($signRequestsByFileId);
		$this->identifyMethodsBySignRequestId = $this->buildIdentifyMethodsBySignRequestId($identifyMethodsBySignRequestId);

		$this->assertSame($expected, $this->service->getActiveSignings());
	}

	public static function provideActiveSigningScenarios(): array {
		return [
			'resolves active signer and status-changed timestamp' => [
				'files' => [[
					'id' => 7,
					'uuid' => 'uuid-7',
					'name' => 'Contract.pdf',
					'status' => FileStatus::SIGNING_IN_PROGRESS->value,
					'createdAt' => '2026-07-07T10:00:00+00:00',
					'metadata' => [
						'status_changed_at' => '2025-07-07T12:34:56+00:00',
					],
				]],
				'signRequestsByFileId' => [
					7 => [
						[
							'id' => 10,
							'fileId' => 7,
							'displayName' => 'Completed signer',
							'status' => SignRequestStatus::SIGNED,
							'signed' => '2026-07-07 10:10:00',
						],
						[
							'id' => 11,
							'fileId' => 7,
							'displayName' => 'Active signer',
							'status' => SignRequestStatus::ABLE_TO_SIGN,
						],
					],
				],
				'identifyMethodsBySignRequestId' => [
					11 => [[
						'identifierKey' => IdentifyMethodService::IDENTIFY_EMAIL,
						'identifierValue' => 'signer@example.com',
						'identifiedAtDate' => '2025-07-07T12:00:00+00:00',
					]],
				],
				'expected' => [[
					'id' => 7,
					'uuid' => 'uuid-7',
					'name' => 'Contract.pdf',
					'signerEmail' => 'signer@example.com',
					'signerDisplayName' => 'Active signer',
					'updatedAt' => 1751891696,
				]],
			],
			'falls back to created-at and unsigned signer' => [
				'files' => [[
					'id' => 9,
					'uuid' => 'uuid-9',
					'name' => 'Fallback.pdf',
					'status' => FileStatus::SIGNING_IN_PROGRESS->value,
					'createdAt' => '2026-07-07T09:30:00+00:00',
					'metadata' => [
						'status_changed_at' => 'not-a-date',
					],
				]],
				'signRequestsByFileId' => [
					9 => [[
						'id' => 19,
						'fileId' => 9,
						'displayName' => 'Pending signer',
						'status' => SignRequestStatus::DRAFT,
					]],
				],
				'identifyMethodsBySignRequestId' => [
					19 => [],
				],
				'expected' => [[
					'id' => 9,
					'uuid' => 'uuid-9',
					'name' => 'Fallback.pdf',
					'signerEmail' => '',
					'signerDisplayName' => 'Pending signer',
					'updatedAt' => 1783416600,
				]],
			],
			'returns empty signer data when no sign request exists' => [
				'files' => [[
					'id' => 22,
					'uuid' => 'uuid-22',
					'name' => 'NoSigner.pdf',
					'status' => FileStatus::SIGNING_IN_PROGRESS->value,
					'createdAt' => '2026-07-07T08:00:00+00:00',
					'metadata' => [],
				]],
				'signRequestsByFileId' => [
					22 => [],
				],
				'identifyMethodsBySignRequestId' => [],
				'expected' => [[
					'id' => 22,
					'uuid' => 'uuid-22',
					'name' => 'NoSigner.pdf',
					'signerEmail' => '',
					'signerDisplayName' => '',
					'updatedAt' => 1783411200,
				]],
			],
		];
	}

	/**
	 * @param array{
	 *     id: int,
	 *     uuid: string,
	 *     name: string,
	 *     status: int,
	 *     createdAt: string,
	 *     metadata: array<string, mixed>
	 * } $definition
	 */
	private function buildFile(array $definition): FileEntity {
		$file = new FileEntity();
		$file->setId($definition['id']);
		$file->setUuid($definition['uuid']);
		$file->setName($definition['name']);
		$file->setStatus($definition['status']);
		$file->setCreatedAt(new \DateTime($definition['createdAt']));
		$file->setMetadata($definition['metadata']);
		return $file;
	}

	/**
	 * @param array<int, list<array{id: int, fileId: int, displayName: string, status: SignRequestStatus, signed?: string}>> $definitionsByFileId
	 * @return array<int, list<SignRequestEntity>>
	 */
	private function buildSignRequestsByFileId(array $definitionsByFileId): array {
		$signRequestsByFileId = [];
		foreach ($definitionsByFileId as $fileId => $definitions) {
			foreach ($definitions as $definition) {
				$signRequestsByFileId[$fileId][] = $this->buildSignRequest($definition);
			}
		}
		return $signRequestsByFileId;
	}

	/**
	 * @param array{id: int, fileId: int, displayName: string, status: SignRequestStatus, signed?: string} $definition
	 */
	private function buildSignRequest(array $definition): SignRequestEntity {
		$signRequest = new SignRequestEntity();
		$signRequest->setId($definition['id']);
		$signRequest->setFileId($definition['fileId']);
		$signRequest->setDisplayName($definition['displayName']);
		$signRequest->setStatusEnum($definition['status']);
		if (isset($definition['signed'])) {
			$signRequest->setSigned($definition['signed']);
		}
		return $signRequest;
	}

	/**
	 * @param array<int, list<array{identifierKey: string, identifierValue: string, identifiedAtDate?: ?string}>> $definitionsBySignRequestId
	 * @return array<int, list<IdentifyMethodEntity>>
	 */
	private function buildIdentifyMethodsBySignRequestId(array $definitionsBySignRequestId): array {
		$identifyMethodsBySignRequestId = [];
		foreach ($definitionsBySignRequestId as $signRequestId => $definitions) {
			foreach ($definitions as $definition) {
				$identifyMethodsBySignRequestId[$signRequestId][] = $this->buildIdentifyMethod($signRequestId, $definition);
			}
		}
		return $identifyMethodsBySignRequestId;
	}

	/**
	 * @param array{identifierKey: string, identifierValue: string, identifiedAtDate?: ?string} $definition
	 */
	private function buildIdentifyMethod(int $signRequestId, array $definition): IdentifyMethodEntity {
		$identifyMethod = new IdentifyMethodEntity();
		$identifyMethod->setSignRequestId($signRequestId);
		$identifyMethod->setIdentifierKey($definition['identifierKey']);
		$identifyMethod->setIdentifierValue($definition['identifierValue']);
		$identifyMethod->setIdentifiedAtDate($definition['identifiedAtDate'] ?? null);
		return $identifyMethod;
	}
}
