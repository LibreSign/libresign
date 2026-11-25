<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Controller;

use OCA\Libresign\Controller\FileController;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\Validation\SigningRequestValidator;
use OCP\AppFramework\Http;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class FileControllerDeleteTest extends TestCase {
	private SigningRequestValidator&MockObject $validator;
	private FileService&MockObject $fileService;
	private FileController $controller;

	protected function setUp(): void {
		$this->validator = $this->createMock(SigningRequestValidator::class);
		$this->fileService = $this->createMock(FileService::class);
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($this->createMock(IUser::class));
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $message): string => $message);

		$reflection = new ReflectionClass(FileController::class);
		$this->controller = $reflection->newInstanceWithoutConstructor();
		foreach ([
			'signingRequestValidator' => $this->validator,
			'fileService' => $this->fileService,
			'userSession' => $userSession,
			'l10n' => $l10n,
		] as $property => $value) {
			$reflection->getProperty($property)->setValue($this->controller, $value);
		}
	}

	public static function failures(): array {
		return [
			'business failure' => [new LibresignException('Invalid fileID'), Http::STATUS_UNPROCESSABLE_ENTITY],
			'unexpected failure' => [new \RuntimeException('database is gone'), Http::STATUS_INTERNAL_SERVER_ERROR],
		];
	}

	#[DataProvider('failures')]
	public function testDeleteFileMapsTheFailureToItsStatus(\Throwable $failure, int $status): void {
		$this->validator->method('validateExistingFile')->willThrowException($failure);

		$response = $this->controller->deleteFile(10);

		$this->assertSame($status, $response->getStatus());
		$this->assertSame(['message' => $failure->getMessage()], $response->getData());
	}

	public function testDeleteFileDoesNotReportTheFailureOfTheServiceAsUnauthorized(): void {
		$this->fileService->method('delete')->willThrowException(new \RuntimeException('database is gone'));

		$response = $this->controller->deleteFile(10);

		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
	}
}
