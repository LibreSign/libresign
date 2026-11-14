<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Controller;

use OCA\Libresign\Controller\IdDocsController;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\IdDocsService;
use OCA\Libresign\Service\SignFileService;
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
		return [
			'known file validation error' => [
				json_encode(['file' => 0, 'type' => 'danger', 'message' => 'Invalid file type.']),
				['file' => 0, 'type' => 'danger', 'message' => 'Invalid file type.'],
			],
			'generic exception' => [
				'Unexpected storage failure',
				['file' => null, 'type' => 'danger', 'message' => 'Unexpected storage failure'],
			],
			'file error without classification' => [
				json_encode(['file' => 2, 'message' => 'Invalid file']),
				['file' => 2, 'type' => 'danger', 'message' => 'Invalid file'],
			],
		];
	}

	#[DataProvider('uploadErrors')]
	public function testUploadErrorKeepsStatusAndResponseShape(string $error, array $expected): void {
		$user = $this->createMock(IUser::class);
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);

		$service = $this->createMock(IdDocsService::class);
		$service->expects($this->once())
			->method('addIdDocs')
			->with([], $user)
			->willThrowException(new \Exception($error));

		$controller = new IdDocsController(
			$this->createMock(IRequest::class),
			$this->createMock(SignFileService::class),
			$this->createMock(IL10N::class),
			$service,
			$session,
			$this->createMock(ValidateHelper::class),
			$this->createMock(LoggerInterface::class),
		);

		$response = $controller->addFiles([]);
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertSame($expected, $response->getData());
	}
}
