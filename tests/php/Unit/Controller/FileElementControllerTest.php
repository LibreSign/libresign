<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Controller;

use OCA\Libresign\Controller\FileElementController;
use OCA\Libresign\Db\FileElement;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FileElementService;
use OCA\Libresign\Helper\ValidateHelper;
use PHPUnit\Framework\TestCase;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;

final class FileElementControllerTest extends TestCase {
	private FileElementService $service;
	private ValidateHelper $validator;
	private FileElementController $controller;

	#[\Override]
	protected function setUp(): void {
		parent::setUp();
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('owner');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$this->service = $this->createMock(FileElementService::class);
		$this->validator = $this->createMock(ValidateHelper::class);
		$logger = $this->createMock(LoggerInterface::class);
		$this->controller = new FileElementController(
			$this->createMock(IRequest::class),
			$this->service,
			$session,
			$this->validator,
			$logger,
		);
	}

	#[DataProvider('writeOperations')]
	public function testCannotModifyElementWithoutOwnership(string $method): void {
		$this->validator->expects($this->once())->method('validateAuthenticatedUserIsOwnerOfPdfVisibleElement')
			->with(101, 'owner')
			->willThrowException(new LibresignException('Not authorized'));
		$this->service->expects($this->never())->method('saveVisibleElement');

		$response = $this->controller->{$method}('own-uuid', 20, 101, null, 'signature', [], []);
		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	#[DataProvider('writeOperations')]
	public function testCannotMoveElementBetweenFiles(string $method): void {
		$this->validator->expects($this->once())->method('validateAuthenticatedUserIsOwnerOfPdfVisibleElement')
			->with(101, 'owner');
		$this->validator->expects($this->once())->method('validateVisibleElementFile')
			->with(101, 'own-uuid')
			->willThrowException(new LibresignException('Wrong file'));
		$this->service->expects($this->never())->method('saveVisibleElement');

		$response = $this->controller->{$method}('own-uuid', 20, 101, null, 'signature', [], []);
		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testCannotCreateElementForUnrelatedSignRequest(): void {
		$this->validator->expects($this->once())->method('validateVisibleElementSignRequestFile')
			->with(20, 'own-uuid')
			->willThrowException(new LibresignException('Wrong sign request'));
		$this->service->expects($this->never())->method('saveVisibleElement');

		$response = $this->controller->createVisibleElement('own-uuid', 20, null, null, 'signature', [], []);
		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	#[DataProvider('writeOperations')]
	public function testAuthorizedWritePreservesFunctionality(string $method): void {
		$this->validator->expects($this->once())->method('validateVisibleElementSignRequestFile')
			->with(20, 'own-uuid');
		$this->validator->expects($this->once())->method('validateAuthenticatedUserIsOwnerOfPdfVisibleElement')
			->with(101, 'owner');
		$this->validator->expects($this->once())->method('validateVisibleElementFile')
			->with(101, 'own-uuid');
		$element = new FileElement();
		$element->setId(101);
		$this->service->expects($this->once())->method('saveVisibleElement')
			->willReturn($element);

		$response = $this->controller->{$method}('own-uuid', 20, 101, null, 'signature', [], []);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['fileElementId' => 101], $response->getData());
	}

	public function testAuthorizedNewElementDoesNotRequireExistingElementOwnership(): void {
		$this->validator->expects($this->never())->method('validateAuthenticatedUserIsOwnerOfPdfVisibleElement');
		$this->validator->expects($this->never())->method('validateVisibleElementFile');
		$this->validator->expects($this->once())->method('validateVisibleElementSignRequestFile')
			->with(20, 'own-uuid');
		$element = new FileElement();
		$element->setId(102);
		$this->service->expects($this->once())->method('saveVisibleElement')->willReturn($element);

		$response = $this->controller->createVisibleElement('own-uuid', 20, null, null, 'signature', [], []);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['fileElementId' => 102], $response->getData());
	}

	public function testCannotDeleteElementFromUnrelatedFile(): void {
		$this->validator->expects($this->once())->method('validateAuthenticatedUserIsOwnerOfPdfVisibleElement')
			->with(101, 'owner');
		$this->validator->expects($this->once())->method('validateVisibleElementFile')
			->with(101, 'own-uuid')
			->willThrowException(new LibresignException('Wrong file'));
		$this->service->expects($this->never())->method('deleteVisibleElement');

		$response = $this->controller->deleteVisibleElement('own-uuid', 101);
		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public static function writeOperations(): array {
		return [
			'POST with element id' => ['createVisibleElement'],
			'PATCH with element id' => ['updateVisibleElement'],
		];
	}
}
