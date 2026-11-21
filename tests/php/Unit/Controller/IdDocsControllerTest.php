<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Controller;

use OCA\Libresign\Controller\IdDocsController;
use OCA\Libresign\Exception\IdDocsUploadValidationException;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\IdDocsService;
use OCA\Libresign\Service\SignFileService;
use OCA\Libresign\Service\Validation\IdentityDocumentValidator;
use OCP\AppFramework\Http;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class IdDocsControllerTest extends TestCase {
	public static function uploadErrors(): array {
		$genericError = ['file' => null, 'type' => 'danger', 'message' => 'Unable to upload identification document.'];
		return [
			'known file validation error' => [
				new IdDocsUploadValidationException(json_encode(['file' => 0, 'type' => 'danger', 'message' => 'Invalid file type.'])),
				['file' => 0, 'type' => 'danger', 'message' => 'Invalid file type.'],
				false,
			],
			'generic exception' => [
				new \RuntimeException('internal-canary-123: storage failed'),
				$genericError,
				true,
			],
			'file error without classification' => [
				new IdDocsUploadValidationException(json_encode(['file' => 2, 'message' => 'Invalid file'])),
				['file' => 2, 'type' => 'danger', 'message' => 'Invalid file'],
				false,
			],
			'info classification' => [
				new IdDocsUploadValidationException(json_encode(['file' => 1, 'type' => 'info', 'message' => 'Check file'])),
				['file' => 1, 'type' => 'info', 'message' => 'Check file'],
				false,
			],
			'warning classification' => [
				new IdDocsUploadValidationException(json_encode(['file' => 1, 'type' => 'warning', 'message' => 'Review file'])),
				['file' => 1, 'type' => 'warning', 'message' => 'Review file'],
				false,
			],
			'null classification' => [
				new IdDocsUploadValidationException(json_encode(['file' => 2, 'type' => null, 'message' => 'Invalid file'])),
				['file' => 2, 'type' => 'danger', 'message' => 'Invalid file'],
				false,
			],
			'generic exception containing file shaped JSON' => [
				new \RuntimeException(json_encode(['file' => 0, 'type' => 'danger', 'message' => 'internal-canary-123'])),
				$genericError,
				true,
			],
			'generic LibresignException containing file shaped JSON' => [
				new LibresignException(json_encode(['file' => 0, 'type' => 'danger', 'message' => 'internal-canary-123'])),
				$genericError,
				true,
			],
			'malformed classified validation' => [
				new IdDocsUploadValidationException(json_encode(['file' => 0, 'type' => 'not-valid', 'message' => 'internal-canary-123'])),
				$genericError,
				true,
			],
			'non-integer file index' => [
				new IdDocsUploadValidationException(json_encode(['file' => '0', 'message' => 'internal-canary-123'])),
				$genericError,
				true,
			],
		];
	}

	#[DataProvider('uploadErrors')]
	public function testUploadErrorKeepsStatusAndResponseShape(\Exception $error, array $expected, bool $shouldLog): void {
		$user = $this->createMock(IUser::class);
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);

		$service = $this->createMock(IdDocsService::class);
		$service->expects($this->once())
			->method('addIdDocs')
			->with([], $user)
			->willThrowException($error);
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($shouldLog ? $this->once() : $this->never())
			->method('error')
			->with('Failed to upload identity document', ['exception' => $error]);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		$controller = new IdDocsController(
			$this->createMock(IRequest::class),
			$this->createMock(SignFileService::class),
			$l10n,
			$service,
			$session,
			$this->createMock(IdentityDocumentValidator::class),
			$logger,
		);

		$response = $controller->addFiles([]);
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertSame($expected, $response->getData());
		$this->assertStringNotContainsString('internal-canary-123', json_encode($response->getData()));
	}
}
