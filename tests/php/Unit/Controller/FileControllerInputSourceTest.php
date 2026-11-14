<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Controller;

use OCA\Libresign\Controller\FileController;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\RequestSignatureService;
use OCP\AppFramework\Http;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class FileControllerInputSourceTest extends TestCase {
	private static function upload(string $name): array {
		return [
			'name' => $name,
			'tmp_name' => '/tmp/upload-placeholder',
			'type' => 'application/pdf',
			'size' => 3,
			'error' => UPLOAD_ERR_OK,
		];
	}

	private static function batch(): array {
		return [
			'name' => ['first.pdf', 'second.pdf'],
			'tmp_name' => ['/tmp/first', '/tmp/second'],
			'type' => ['application/pdf', 'application/pdf'],
			'size' => [3, 4],
			'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
		];
	}

	public static function acceptedInputs(): array {
		return [
			'JSON single reference' => [['nodeId' => 1], [], null, null, [['nodeId' => 1]]],
			'JSON reference list' => [[], [['nodeId' => 1], ['nodeId' => 2]], null, null, [['nodeId' => 1], ['nodeId' => 2]]],
			'single multipart file' => [[], [], self::upload('one.pdf'), null, [
				['uploadedFile' => self::upload('one.pdf'), 'name' => 'one'],
			]],
			'multipart list under files' => [[], [], null, self::batch(), [
				['uploadedFile' => self::uploadFromBatch(0), 'name' => 'first'],
				['uploadedFile' => self::uploadFromBatch(1), 'name' => 'second'],
			]],
			'legacy multipart list under file' => [[], [], self::batch(), null, [
				['uploadedFile' => self::uploadFromBatch(0), 'name' => 'first'],
				['uploadedFile' => self::uploadFromBatch(1), 'name' => 'second'],
			]],
		];
	}

	private static function uploadFromBatch(int $index): array {
		$batch = self::batch();
		return array_map(static fn (array $column): mixed => $column[$index], $batch);
	}

	public static function conflictingInputs(): array {
		return [
			'both JSON forms' => [['nodeId' => 1], [['nodeId' => 2]], null, null],
			'both multipart fields' => [[], [], self::upload('one.pdf'), self::batch()],
			'JSON file plus multipart file' => [['nodeId' => 1], [], self::upload('one.pdf'), null],
			'JSON files plus multipart files' => [[], [['nodeId' => 1]], null, self::batch()],
		];
	}

	private function controller(): FileController {
		$reflection = new ReflectionClass(FileController::class);
		/** @var FileController $controller */
		$controller = $reflection->newInstanceWithoutConstructor();

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $message): string => $message);
		$reflection->getProperty('l10n')->setValue($controller, $l10n);

		$fileService = $this->createMock(FileService::class);
		$reflection->getProperty('fileService')->setValue($controller, $fileService);

		$reflection->getProperty('validateHelper')->setValue($controller, $this->createMock(ValidateHelper::class));
		return $controller;
	}

	#[DataProvider('acceptedInputs')]
	public function testAcceptsOneFileInputSource(array $file, array $files, ?array $singleUpload, ?array $batchUpload, array $expected): void {
		$controller = $this->controller();
		$result = (new ReflectionMethod(FileController::class, 'prepareFilesForSaving'))
			->invoke($controller, $file, $files, [], $singleUpload, $batchUpload);
		// PHP file entries are associative maps: key insertion order is not contractual.
		$sortUploadKeys = static function (array $entry): array {
			if (isset($entry['uploadedFile'])) {
				ksort($entry['uploadedFile']);
			}
			return $entry;
		};
		self::assertSame(array_map($sortUploadKeys, $expected), array_map($sortUploadKeys, $result));
	}

	#[DataProvider('conflictingInputs')]
	public function testRejectsAmbiguousFileInputs(array $file, array $files, ?array $singleUpload, ?array $batchUpload): void {
		$controller = $this->controller();
		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Use only one file input format per request');
		(new ReflectionMethod(FileController::class, 'prepareFilesForSaving'))
			->invoke($controller, $file, $files, [], $singleUpload, $batchUpload);
	}

	public function testConflictingMultipartInputReturns422WithoutSaving(): void {
		$controller = $this->controller();
		$reflection = new ReflectionClass(FileController::class);

		$request = $this->createMock(IRequest::class);
		$request->method('getUploadedFile')->willReturnMap([
			['file', self::upload('one.pdf')],
			['files', self::batch()],
		]);
		$reflection->getProperty('request')->setValue($controller, $request);

		$user = $this->createMock(IUser::class);
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);
		$reflection->getProperty('userSession')->setValue($controller, $userSession);

		$signingValidator = $this->createMock(ValidateHelper::class);
		$signingValidator->expects($this->once())->method('canRequestSign')->with($user);
		$reflection->getProperty('validateHelper')->setValue($controller, $signingValidator);

		$requestSignatureService = $this->createMock(RequestSignatureService::class);
		$requestSignatureService->expects($this->never())->method('saveFiles');
		$reflection->getProperty('requestSignatureService')->setValue($controller, $requestSignatureService);

		$response = $controller->save();
		self::assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
		self::assertSame('Use only one file input format per request', $response->getData()['message']);
	}
}
