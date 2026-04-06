<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Controller;

use OCA\Libresign\Controller\RequestSignatureController;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\File\FileListService;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\RequestSignatureService;
use OCP\AppFramework\Http;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RequestSignatureControllerTest extends TestCase {
	private RequestSignatureController $controller;
	private IRequest&MockObject $request;
	private IL10N&MockObject $l10n;
	private IUserSession&MockObject $userSession;
	private FileService&MockObject $fileService;
	private FileListService&MockObject $fileListService;
	private ValidateHelper&MockObject $validateHelper;
	private RequestSignatureService&MockObject $requestSignatureService;
	private FileMapper&MockObject $fileMapper;
	private IUser&MockObject $user;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->fileService = $this->createMock(FileService::class);
		$this->fileListService = $this->createMock(FileListService::class);
		$this->validateHelper = $this->createMock(ValidateHelper::class);
		$this->requestSignatureService = $this->createMock(RequestSignatureService::class);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->user = $this->createMock(IUser::class);

		$this->userSession->method('getUser')->willReturn($this->user);

		$this->controller = new RequestSignatureController(
			$this->request,
			$this->l10n,
			$this->userSession,
			$this->fileService,
			$this->fileListService,
			$this->validateHelper,
			$this->requestSignatureService,
			$this->fileMapper,
		);
	}

	#[DataProvider('statusPayloadScenarios')]
	public function testRequestStatusPropagation(?int $status, bool $expectStatusKey): void {
		$file = new FileEntity();
		$file->setId(10);
		$file->setParentFileId(99);

		$this->requestSignatureService
			->expects($this->once())
			->method('validateNewRequestToFile')
			->with($this->callback(static function (array $payload) use ($expectStatusKey, $status): bool {
				$hasStatus = array_key_exists('status', $payload);
				if ($expectStatusKey !== $hasStatus) {
					return false;
				}
				if ($expectStatusKey) {
					return $payload['status'] === $status;
				}
				return true;
			}));

		$this->requestSignatureService
			->expects($this->once())
			->method('save')
			->willReturn($file);

		$this->fileListService
			->expects($this->once())
			->method('formatFileWithChildren')
			->with($file, [], $this->user)
			->willReturn(['ok' => true]);

		$response = $this->controller->request(
			signers: [[
				'identifyMethods' => [[
					'method' => 'email',
					'value' => 'user@test.coop',
					'mandatory' => 0,
				]],
			]],
			name: 'contract.pdf',
			settings: [],
			file: ['nodeId' => 12],
			files: [],
			callback: null,
			status: $status,
			signatureFlow: null,
		);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	#[DataProvider('statusPayloadScenarios')]
	public function testUpdateSignStatusPropagation(?int $status, bool $expectStatusKey): void {
		$file = new FileEntity();
		$file->setId(20);
		$file->setParentFileId(88);

		$this->validateHelper
			->expects($this->once())
			->method('validateExistingFile');

		$this->validateHelper
			->expects($this->once())
			->method('validateFileStatus')
			->with($this->callback(static function (array $payload) use ($expectStatusKey, $status): bool {
				$hasStatus = array_key_exists('status', $payload);
				if ($expectStatusKey !== $hasStatus) {
					return false;
				}
				if ($expectStatusKey) {
					return $payload['status'] === $status;
				}
				return true;
			}));

		$this->validateHelper
			->expects($this->once())
			->method('validateIdentifySigners');

		$this->requestSignatureService
			->expects($this->once())
			->method('save')
			->willReturn($file);

		$this->fileListService
			->expects($this->once())
			->method('formatFileWithChildren')
			->with($file, [], $this->user)
			->willReturn(['ok' => true]);

		$response = $this->controller->updateSign(
			signers: [[
				'identifyMethods' => [[
					'method' => 'email',
					'value' => 'user@test.coop',
					'mandatory' => 0,
				]],
			]],
			uuid: '550e8400-e29b-41d4-a716-446655440000',
			visibleElements: null,
			file: [],
			status: $status,
			signatureFlow: null,
			name: null,
			settings: [],
			files: [],
		);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public static function statusPayloadScenarios(): array {
		return [
			'null status is omitted' => [
				'status' => null,
				'expectStatusKey' => false,
			],
			'draft status is preserved' => [
				'status' => 0,
				'expectStatusKey' => true,
			],
			'able to sign status is preserved' => [
				'status' => 1,
				'expectStatusKey' => true,
			],
			'explicit status is preserved' => [
				'status' => 4,
				'expectStatusKey' => true,
			],
		];
	}
}
