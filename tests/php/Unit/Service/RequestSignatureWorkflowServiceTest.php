<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\RequestSignatureService;
use OCA\Libresign\Service\RequestSignatureWorkflowService;
use OCP\IL10N;
use OCP\IUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RequestSignatureWorkflowServiceTest extends TestCase {
	private IL10N&MockObject $l10n;
	private RequestSignatureService&MockObject $requestSignatureService;
	private ValidateHelper&MockObject $validateHelper;
	private FileMapper&MockObject $fileMapper;
	private RequestSignatureWorkflowService $service;
	private IUser&MockObject $user;

	protected function setUp(): void {
		parent::setUp();

		$this->l10n = $this->createMock(IL10N::class);
		$this->requestSignatureService = $this->createMock(RequestSignatureService::class);
		$this->validateHelper = $this->createMock(ValidateHelper::class);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->user = $this->createMock(IUser::class);
		$this->l10n->method('t')->willReturnCallback(static fn (string $message): string => $message);

		$this->service = new RequestSignatureWorkflowService(
			$this->l10n,
			$this->requestSignatureService,
			$this->validateHelper,
			$this->fileMapper,
		);
	}

	#[DataProvider('policyPayloadScenarios')]
	public function testResolvePolicyPayloadNormalizesOverridesAndActiveContext(?array $policy, array $expectedOverrides, ?array $expectedActiveContext): void {
		$result = $this->service->resolvePolicyPayload($policy);

		$this->assertSame($expectedOverrides, $result['policyOverrides']);
		$this->assertSame($expectedActiveContext, $result['policyActiveContext']);
	}

	public function testCreateRequestThrowsWhenFileAndFilesAreMissing(): void {
		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('File or files parameter is required');

		$this->service->createRequest(
			$this->user,
			[],
			[],
			'contract.pdf',
			[],
			[],
			1,
			null,
		);
	}

	public function testCreateRequestMapsPolicyAndFallsBackToFileSettings(): void {
		$fileEntity = new FileEntity();
		$fileEntity->setId(9);
		$fileEntity->setParentFileId(99);

		$this->requestSignatureService->expects($this->once())
			->method('validateNewRequestToFile')
			->with($this->callback(static function (array $payload): bool {
				return $payload['settings'] === ['path' => '/contracts']
					&& ($payload['policyOverrides']['signature_flow'] ?? null) === 'parallel'
					&& ($payload['policyActiveContext'] ?? null) === ['type' => 'group', 'id' => 'board'];
			}));

		$this->requestSignatureService->expects($this->once())
			->method('save')
			->willReturn($fileEntity);

		$result = $this->service->createRequest(
			$this->user,
			['nodeId' => 11, 'settings' => ['path' => '/contracts']],
			[],
			'contract.pdf',
			[],
			[['identifyMethods' => [['method' => 'email', 'value' => 'user@example.test']]]],
			1,
			null,
			[
				'overrides' => ['signature_flow' => 'parallel'],
				'activeContext' => ['type' => 'group', 'id' => 'board'],
			],
		);

		$this->assertSame($fileEntity, $result['file']);
		$this->assertSame([], $result['children']);
	}

	public function testCreateRequestUsesEnvelopeSaveFilesAndReturnsProvidedChildren(): void {
		$envelope = new FileEntity();
		$envelope->setId(30);
		$envelope->setNodeType('envelope');

		$child = new FileEntity();
		$child->setId(31);

		$this->requestSignatureService->expects($this->once())
			->method('validateNewRequestToFile')
			->with($this->callback(static function (array $payload): bool {
				return isset($payload['files'], $payload['visibleElements'])
					&& $payload['settings'] === ['path' => '/bundle']
					&& $payload['visibleElements'] === [['fileId' => 31]];
			}));

		$this->requestSignatureService->expects($this->once())
			->method('saveFiles')
			->willReturn([
				'file' => $envelope,
				'children' => [$child],
			]);

		$result = $this->service->createRequest(
			$this->user,
			[],
			[['nodeId' => 11, 'name' => 'part-a.pdf']],
			'Envelope',
			['path' => '/bundle'],
			[['identifyMethods' => [['method' => 'email', 'value' => 'user@example.test']]]],
			0,
			null,
			null,
			[['fileId' => 31]],
		);

		$this->assertSame($envelope, $result['file']);
		$this->assertSame([$child], $result['children']);
	}

	public function testUpdateExistingRequestValidatesAndLoadsEnvelopeChildren(): void {
		$fileEntity = new FileEntity();
		$fileEntity->setId(21);
		$fileEntity->setNodeType('envelope');

		$child = new FileEntity();
		$child->setId(22);

		$this->validateHelper->expects($this->once())->method('validateExistingFile');
		$this->validateHelper->expects($this->once())->method('validateFileStatus');
		$this->validateHelper->expects($this->once())->method('validateIdentifySigners');
		$this->validateHelper->expects($this->once())
			->method('validateVisibleElements')
			->with([['fileId' => 22]], ValidateHelper::TYPE_VISIBLE_ELEMENT_PDF);

		$this->requestSignatureService->expects($this->once())
			->method('save')
			->with($this->callback(static function (array $payload): bool {
				return $payload['uuid'] === 'uuid-1'
					&& $payload['status'] === 2
					&& ($payload['policyOverrides']['docmdp'] ?? null) === 2;
			}))
			->willReturn($fileEntity);

		$this->fileMapper->expects($this->once())
			->method('getChildrenFiles')
			->with(21)
			->willReturn([$child]);

		$result = $this->service->updateExistingRequest(
			$this->user,
			[['identifyMethods' => [['method' => 'email', 'value' => 'user@example.test']]]],
			'uuid-1',
			[['fileId' => 22]],
			['nodeId' => 11],
			2,
			['overrides' => ['docmdp' => 2]],
			'Contract',
			['path' => '/contracts'],
		);

		$this->assertSame($fileEntity, $result['file']);
		$this->assertSame([$child], $result['children']);
	}

	public function testUpdateExistingRequestSkipsVisibleElementValidationWhenElementsAreEmpty(): void {
		$fileEntity = new FileEntity();
		$fileEntity->setId(40);
		$fileEntity->setParentFileId(99);

		$this->validateHelper->expects($this->once())->method('validateExistingFile');
		$this->validateHelper->expects($this->once())->method('validateFileStatus');
		$this->validateHelper->expects($this->once())->method('validateIdentifySigners');
		$this->validateHelper->expects($this->never())->method('validateVisibleElements');

		$this->requestSignatureService->expects($this->once())
			->method('save')
			->with($this->callback(static function (array $payload): bool {
				return $payload['uuid'] === 'uuid-2' && !array_key_exists('status', $payload);
			}))
			->willReturn($fileEntity);

		$this->fileMapper->expects($this->never())->method('getChildrenFiles');

		$result = $this->service->updateExistingRequest(
			$this->user,
			[['identifyMethods' => [['method' => 'email', 'value' => 'user@example.test']]]],
			'uuid-2',
			[],
			['nodeId' => 11],
			null,
			null,
			'Contract',
			['path' => '/contracts'],
		);

		$this->assertSame($fileEntity, $result['file']);
		$this->assertSame([], $result['children']);
	}

	public static function policyPayloadScenarios(): array {
		return [
			'null payload' => [
				'policy' => null,
				'expectedOverrides' => [],
				'expectedActiveContext' => null,
			],
			'invalid nested values' => [
				'policy' => [
					'overrides' => 'parallel',
					'activeContext' => 'group-a',
				],
				'expectedOverrides' => [],
				'expectedActiveContext' => null,
			],
			'valid payload' => [
				'policy' => [
					'overrides' => ['signature_flow' => 'parallel'],
					'activeContext' => ['type' => 'group', 'id' => 'board'],
				],
				'expectedOverrides' => ['signature_flow' => 'parallel'],
				'expectedActiveContext' => ['type' => 'group', 'id' => 'board'],
			],
		];
	}
}
