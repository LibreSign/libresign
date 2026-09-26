<?php

declare(strict_types=1);

namespace OCA\Libresign\Tests\Unit\Service;

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Libresign\Db\UserElement;
use OCA\Libresign\Db\UserElementMapper;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\SessionService;
use OCA\Libresign\Service\SignatureBackgroundService;
use OCA\Libresign\Service\SignatureTextService;
use OCA\Libresign\Service\SignerElementsService;
use OCA\Libresign\Service\Validation\FileInputValidator;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class SignerElementsServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private SignerElementsService $service;
	private FolderService&MockObject $folderService;
	private SessionService&MockObject $sessionService;
	private IURLGenerator&MockObject $urlGenerator;
	private UserElementMapper&MockObject $userElementMapper;
	private SignatureBackgroundService&MockObject $signatureBackgroundService;
	private SignatureTextService&MockObject $signatureTextService;
	private FileInputValidator&MockObject $fileInputValidator;
	private IClientService&MockObject $clientService;
	private ITimeFactory&MockObject $timeFactory;
	private IL10N&MockObject $l10n;

	#[\Override]
	public function setUp(): void {
		$this->folderService = $this->createMock(FolderService::class);
		$this->sessionService = $this->createMock(SessionService::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->userElementMapper = $this->createMock(UserElementMapper::class);
		$this->signatureBackgroundService = $this->createMock(SignatureBackgroundService::class);
		$this->signatureTextService = $this->createMock(SignatureTextService::class);
		$this->fileInputValidator = $this->createMock(FileInputValidator::class);
		$this->clientService = $this->createMock(IClientService::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnArgument(0);
	}

	private function getService(): SignerElementsService {
		$this->service = new SignerElementsService(
			$this->folderService,
			$this->sessionService,
			$this->urlGenerator,
			$this->userElementMapper,
			$this->signatureBackgroundService,
			$this->signatureTextService,
			$this->fileInputValidator,
			$this->clientService,
			$this->timeFactory,
			$this->l10n,
		);
		return $this->service;
	}

	public function testDeleteSignatureElementWithUserDeletesFromDB(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('testuser');

		// Use real UserElement instead of mock since it uses magic methods
		$element = new UserElement();
		$element->setId(42);
		$element->setNodeId(123);

		$this->userElementMapper
			->expects($this->once())
			->method('findOne')
			->with([
				'node_id' => 123,
				'user_id' => 'testuser',
			])
			->willReturn($element);

		$this->userElementMapper
			->expects($this->once())
			->method('delete')
			->with($element);

		$file = $this->createMock(File::class);
		$file->expects($this->once())
			->method('delete');

		$this->folderService
			->expects($this->once())
			->method('getFileByNodeId')
			->with(123)
			->willReturn($file);

		$this->getService()->deleteSignatureElement($user, 'session123', 123);
	}

	public function testDeleteSignatureElementWithUserWhenFileNotFound(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('testuser');

		// Use real UserElement
		$element = new UserElement();
		$element->setNodeId(123);

		$this->userElementMapper
			->expects($this->once())
			->method('findOne')
			->willReturn($element);

		$this->userElementMapper
			->expects($this->once())
			->method('delete')
			->with($element);

		$this->folderService
			->expects($this->once())
			->method('getFileByNodeId')
			->willThrowException(new NotFoundException());

		// Should not throw, just skip file deletion
		$this->getService()->deleteSignatureElement($user, 'session123', 123);
	}

	public function testDeleteSignatureElementWithUserWhenFileDeleteFails(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('testuser');

		$element = new UserElement();
		$element->setNodeId(123);

		$this->userElementMapper
			->expects($this->once())
			->method('findOne')
			->willReturn($element);

		$this->userElementMapper
			->expects($this->once())
			->method('delete')
			->with($element);

		$file = $this->createMock(File::class);
		$file->expects($this->once())
			->method('delete')
			->willThrowException(new \Exception('storage error'));

		$this->folderService
			->expects($this->once())
			->method('getFileByNodeId')
			->with(123)
			->willReturn($file);

		// Should not throw, element deletion in DB must be enough
		$this->getService()->deleteSignatureElement($user, 'session123', 123);
	}

	public function testDeleteSignatureElementWithoutUserDeletesFromSession(): void {
		$sessionFolder = $this->createMock(Folder::class);
		$element = $this->createMock(File::class);

		$element->expects($this->once())
			->method('delete');

		$sessionFolder
			->expects($this->once())
			->method('getFirstNodeById')
			->with(456)
			->willReturn($element);

		// Session folder becomes empty after deletion
		$sessionFolder
			->expects($this->once())
			->method('getDirectoryListing')
			->willReturn([]);

		// Empty folder should be deleted too
		$sessionFolder
			->expects($this->once())
			->method('delete');

		$rootFolder = $this->createMock(Folder::class);
		$rootFolder
			->expects($this->once())
			->method('get')
			->with('session789')
			->willReturn($sessionFolder);

		$this->folderService
			->expects($this->once())
			->method('getFolder')
			->willReturn($rootFolder);

		$this->getService()->deleteSignatureElement(null, 'session789', 456);
	}

	public function testDeleteSignatureElementWithoutUserThrowsWhenSessionFolderNotFound(): void {
		$this->expectException(DoesNotExistException::class);
		$this->expectExceptionMessage('Element not found');

		$rootFolder = $this->createMock(Folder::class);
		$rootFolder
			->expects($this->once())
			->method('get')
			->with('nonexistent')
			->willThrowException(new NotFoundException());

		$this->folderService
			->expects($this->once())
			->method('getFolder')
			->willReturn($rootFolder);

		$this->getService()->deleteSignatureElement(null, 'nonexistent', 999);
	}

	public function testDeleteSignatureElementWithoutUserThrowsWhenNodeNotInSession(): void {
		$this->expectException(DoesNotExistException::class);
		$this->expectExceptionMessage('Element not found');

		$sessionFolder = $this->createMock(Folder::class);
		$sessionFolder
			->expects($this->once())
			->method('getFirstNodeById')
			->with(999)
			->willReturn(null);

		$rootFolder = $this->createMock(Folder::class);
		$rootFolder
			->expects($this->once())
			->method('get')
			->with('session123')
			->willReturn($sessionFolder);

		$this->folderService
			->expects($this->once())
			->method('getFolder')
			->willReturn($rootFolder);

		$this->getService()->deleteSignatureElement(null, 'session123', 999);
	}

	public function testDeleteSignatureElementWithoutUserThrowsWhenNodeIsNotFile(): void {
		$this->expectException(DoesNotExistException::class);
		$this->expectExceptionMessage('Element not found');

		$sessionFolder = $this->createMock(Folder::class);
		$folderNode = $this->createMock(Folder::class); // Not a File!

		$sessionFolder
			->expects($this->once())
			->method('getFirstNodeById')
			->with(777)
			->willReturn($folderNode);

		$rootFolder = $this->createMock(Folder::class);
		$rootFolder
			->expects($this->once())
			->method('get')
			->with('session456')
			->willReturn($sessionFolder);

		$this->folderService
			->expects($this->once())
			->method('getFolder')
			->willReturn($rootFolder);

		$this->getService()->deleteSignatureElement(null, 'session456', 777);
	}

	public function testDeleteSignatureElementOnlyDeletesSpecificFileNotWholeFolder(): void {
		// This test validates the critical security fix:
		// Previously: deleted entire session folder immediately (losing all files)
		// Now: deletes only specific file by nodeId, keeps other files intact

		$sessionFolder = $this->createMock(Folder::class);
		$targetFile = $this->createMock(File::class);
		$otherFile = $this->createMock(File::class);

		// Should call delete on the specific FILE, not on the FOLDER
		$targetFile->expects($this->once())
			->method('delete');

		$sessionFolder
			->expects($this->once())
			->method('getFirstNodeById')
			->with(100)
			->willReturn($targetFile);

		// After deleting target file, folder still has other files
		$sessionFolder
			->expects($this->once())
			->method('getDirectoryListing')
			->willReturn([$otherFile]);

		// Folder should NOT be deleted because it still has files
		$sessionFolder->expects($this->never())
			->method('delete');

		$rootFolder = $this->createMock(Folder::class);
		$rootFolder
			->expects($this->once())
			->method('get')
			->with('mysession')
			->willReturn($sessionFolder);

		$this->folderService
			->expects($this->once())
			->method('getFolder')
			->willReturn($rootFolder);

		$this->getService()->deleteSignatureElement(null, 'mysession', 100);
	}

	public function testDeleteSignatureElementDeletesEmptySessionFolder(): void {
		// When the last element is deleted, the empty session folder should be cleaned up

		$sessionFolder = $this->createMock(Folder::class);
		$lastFile = $this->createMock(File::class);

		$lastFile->expects($this->once())
			->method('delete');

		$sessionFolder
			->expects($this->once())
			->method('getFirstNodeById')
			->with(200)
			->willReturn($lastFile);

		// After deleting last file, folder is empty
		$sessionFolder
			->expects($this->once())
			->method('getDirectoryListing')
			->willReturn([]);

		// Empty folder SHOULD be deleted
		$sessionFolder->expects($this->once())
			->method('delete');

		$rootFolder = $this->createMock(Folder::class);
		$rootFolder
			->expects($this->once())
			->method('get')
			->with('session999')
			->willReturn($sessionFolder);

		$this->folderService
			->expects($this->once())
			->method('getFolder')
			->willReturn($rootFolder);

		$this->getService()->deleteSignatureElement(null, 'session999', 200);
	}

	public function testSaveVisibleElementsUpdatesEveryElement(): void {
		$first = new UserElement();
		$first->setId(10);
		$first->setStarred(0);
		$second = new UserElement();
		$second->setId(20);
		$second->setStarred(1);
		$this->userElementMapper->method('findOne')->willReturnMap([
			[['id' => 10], $first],
			[['id' => 20], $second],
		]);
		$updated = [];
		$this->userElementMapper->expects($this->exactly(2))->method('update')
			->willReturnCallback(static function (UserElement $element) use (&$updated): UserElement {
				$updated[] = $element;
				return $element;
			});

		$this->getService()->saveVisibleElements([
			['elementId' => 10, 'starred' => true],
			['elementId' => 20, 'starred' => false],
		], 'session-id', null);

		$this->assertSame([$first, $second], $updated);
		$this->assertTrue($first->getStarred());
		$this->assertFalse($second->getStarred());
	}

	public function testSaveVisibleElementsWithNoElementsDoesNotWrite(): void {
		$this->userElementMapper->expects($this->never())->method('insert');
		$this->userElementMapper->expects($this->never())->method('update');
		$this->folderService->expects($this->never())->method('getFolder');
		$this->getService()->saveVisibleElements([], 'session-id', null);
	}

	public function testSaveVisibleElementWithoutChangesDoesNotWrite(): void {
		$this->userElementMapper->expects($this->never())->method('findOne');
		$this->userElementMapper->expects($this->never())->method('update');
		$this->folderService->expects($this->never())->method('getFolder');
		$this->getService()->saveVisibleElement(['elementId' => 10], 'session-id', null);
	}

	#[DataProvider('provideBase64Images')]
	public function testSaveVisibleElementUpdatesImage(string $base64, string $content): void {
		$element = new UserElement();
		$element->setNodeId(42);
		$this->userElementMapper->method('findOne')->with(['id' => 10])->willReturn($element);
		$this->userElementMapper->expects($this->never())->method('update');
		$file = $this->createMock(File::class);
		$file->expects($this->once())->method('putContent')->with($content);
		$this->folderService->method('getFileByNodeId')->with(42)->willReturn($file);
		$this->fileInputValidator->expects($this->once())->method('validateBase64')
			->with($base64, FileInputValidator::TYPE_VISIBLE_ELEMENT_USER);

		$this->getService()->saveVisibleElement([
			'elementId' => 10,
			'file' => ['base64' => $base64],
		], 'session-id', null);
	}

	public static function provideBase64Images(): array {
		return [
			'raw base64' => ['aW1hZ2U=', 'image'],
			'data URI' => ['data:image/png;base64,aW1hZ2U=', 'image'],
			'empty decoded content' => ['', ''],
			'falsey decoded content' => ['MA==', ''],
		];
	}

	public function testSaveVisibleElementUpdatesImageAndStarredFlag(): void {
		$element = new UserElement();
		$element->setNodeId(42);
		$element->setStarred(0);
		$this->userElementMapper->method('findOne')->with(['id' => 10])->willReturn($element);
		$this->userElementMapper->expects($this->once())->method('update')->with($element);
		$file = $this->createMock(File::class);
		$file->expects($this->once())->method('putContent')->with('image');
		$this->folderService->method('getFileByNodeId')->with(42)->willReturn($file);

		$this->getService()->saveVisibleElement([
			'elementId' => 10, 'starred' => true, 'file' => ['base64' => 'aW1hZ2U='],
		], 'session-id', null);
		$this->assertTrue($element->getStarred());
	}

	#[DataProvider('provideStarredCreationValues')]
	public function testSaveVisibleElementCreatesUserElement(array $starred, bool $expected): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('signer');
		$data = ['type' => 'signature', 'file' => ['base64' => 'aW1hZ2U=']] + $starred;
		$createdAt = new \DateTime('2026-01-01 12:00:00', new \DateTimeZone('UTC'));
		$this->timeFactory->method('getDateTime')->willReturn($createdAt);
		$root = $this->createMock(Folder::class);
		$folder = $this->createMock(Folder::class);
		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(42);
		$this->folderService->method('getFolder')->willReturn($root);
		$this->folderService->method('getFolderName')->with($data, $user)->willReturn('signer-folder');
		$root->method('newFolder')->with('signer-folder')->willReturn($folder);
		$folder->expects($this->once())->method('newFile')->with(
			$this->callback(static fn (string $name): bool => str_ends_with($name, '.png')
				&& \Sabre\DAV\UUIDUtil::validateUUID(substr($name, 0, -4))),
			'image',
		)->willReturn($file);
		$this->userElementMapper->expects($this->once())->method('insert')->with(
			$this->callback(static fn (UserElement $element): bool => $element->getType() === 'signature'
				&& $element->getNodeId() === 42
				&& $element->getUserId() === 'signer'
				&& $element->getStarred() === $expected
				&& $element->getCreatedAt() === $createdAt),
		);

		$this->getService()->saveVisibleElement($data, 'session-id', $user);
	}

	public static function provideStarredCreationValues(): array {
		return [
			'not provided' => [[], false],
			'false' => [['starred' => false], false],
			'true' => [['starred' => true], true],
		];
	}

	public function testSaveVisibleElementCreatesSessionImage(): void {
		$createdAt = new \DateTime('2026-01-01 12:00:00', new \DateTimeZone('UTC'));
		$this->timeFactory->method('getDateTime')->willReturn($createdAt);
		$root = $this->createMock(Folder::class);
		$folder = $this->createMock(Folder::class);
		$file = $this->createMock(File::class);
		$this->folderService->method('getFolder')->willReturn($root);
		$root->method('newFolder')->with('session-id')->willReturn($folder);
		$folder->expects($this->once())->method('newFile')
			->with('initial_' . $createdAt->getTimestamp() . '.png', 'image')->willReturn($file);
		$this->userElementMapper->expects($this->never())->method('insert');

		$this->getService()->saveVisibleElement([
			'type' => 'initial', 'file' => ['base64' => 'aW1hZ2U='],
		], 'session-id', null);
	}

	#[DataProvider('provideSessionUpdateCases')]
	public function testSaveVisibleElementUpdatesOnlyMatchingSessionImage(bool $exists): void {
		$root = $this->createMock(Folder::class);
		$folder = $this->createMock(Folder::class);
		$target = $this->createMock(File::class);
		$target->method('getId')->willReturn(42);
		$other = $this->createMock(File::class);
		$other->method('getId')->willReturn(43);
		$other->expects($this->never())->method('putContent');
		$this->folderService->method('getFolder')->willReturn($root);
		$this->sessionService->method('getSessionId')->willReturn('session-id');
		$root->method('get')->with('session-id')->willReturn($folder);
		$root->expects($this->never())->method('newFolder');
		$folder->method('getDirectoryListing')->willReturn($exists ? [$other, $target] : [$other]);
		$this->userElementMapper->expects($this->never())->method('insert');

		if ($exists) {
			$target->expects($this->once())->method('putContent')->with('image');
		} else {
			$this->expectException(\Exception::class);
			$this->expectExceptionMessage('File not found');
		}
		$this->getService()->saveVisibleElement([
			'nodeId' => 42, 'file' => ['base64' => 'aW1hZ2U='],
		], 'session-id', null);
	}

	public static function provideSessionUpdateCases(): array {
		return ['matching image' => [true], 'image outside session' => [false]];
	}

	#[DataProvider('provideSessionUpdateCases')]
	public function testGetElementsFromSession(bool $exists): void {
		$root = $this->createMock(Folder::class);
		$this->folderService->method('getFolder')->willReturn($root);
		$this->sessionService->method('getSessionId')->willReturn('session-id');
		$expected = [];
		if ($exists) {
			$folder = $this->createMock(Folder::class);
			$expected = [$this->createMock(File::class)];
			$folder->method('getDirectoryListing')->willReturn($expected);
			$root->method('get')->with('session-id')->willReturn($folder);
		} else {
			$root->method('get')->with('session-id')->willThrowException(new NotFoundException());
		}
		$this->assertSame($expected, $this->getService()->getElementsFromSession());
	}

	#[DataProvider('provideInvalidUrlImageCases')]
	public function testSaveVisibleElementRejectsInvalidUrlImageInput(string $url, string $mime, string $body, string $error): void {
		$element = new UserElement();
		$element->setNodeId(42);
		$this->userElementMapper->method('findOne')->with(['id' => 10])->willReturn($element);
		$file = $this->createMock(File::class);
		$this->folderService->method('getFileByNodeId')->with(42)->willReturn($file);
		$response = $this->createMock(IResponse::class);
		$response->method('getHeader')->with('Content-Type')->willReturn($mime);
		$response->method('getBody')->willReturn($body);
		$client = $this->createMock(IClient::class);
		$client->method('get')->with($url)->willReturn($response);
		$this->clientService->method('newClient')->willReturn($client);
		$file->expects($this->never())->method('putContent');
		$this->fileInputValidator->expects($this->never())->method('validateBase64');
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage($error);

		$this->getService()->saveVisibleElement([
			'elementId' => 10, 'file' => ['url' => $url],
		], 'session-id', null);
	}

	public static function provideInvalidUrlImageCases(): array {
		return [
			'invalid URL' => ['not a URL', 'image/png', 'image', 'Invalid URL file'],
			'wrong content type' => ['https://example.com/image.jpg', 'image/jpeg', 'image', 'Visible element file must be png.'],
			'empty body' => ['https://example.com/image.png', 'image/png', '', 'Empty file'],
		];
	}

	public function testSaveVisibleElementPropagatesImageValidationErrors(): void {
		$element = new UserElement();
		$element->setNodeId(42);
		$this->userElementMapper->method('findOne')->willReturn($element);
		$file = $this->createMock(File::class);
		$file->expects($this->never())->method('putContent');
		$this->folderService->method('getFileByNodeId')->willReturn($file);
		$error = new \InvalidArgumentException('Invalid image');
		$this->fileInputValidator->method('validateBase64')->willThrowException($error);
		$this->expectExceptionObject($error);

		$this->getService()->saveVisibleElement([
			'elementId' => 10, 'file' => ['base64' => 'invalid'],
		], 'session-id', null);
	}

	#[DataProvider('providerIsSignElementsAvailable')]
	public function testIsSignElementsAvailable(bool $background, bool $text, string $renderMode, bool $expected): void {
		$this->signatureBackgroundService->method('isEnabled')->willReturn($background);
		$this->signatureTextService->method('isEnabled')->willReturn($text);
		$this->signatureTextService->method('getRenderMode')->willReturn($renderMode);
		$available = $this->getService()->isSignElementsAvailable();
		$this->assertEquals($expected, $available);
	}

	/**
	 * @return array<string, array{bool, bool, string, bool}>
	 */
	public static function providerIsSignElementsAvailable(): array {
		return [
			'background only' => [true, false, SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY, true],
			'text only' => [false, true, SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY, true],
			'background and text' => [true, true, SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY, true],
			'drawn signature and description' => [false, false, SignerElementsService::RENDER_MODE_GRAPHIC_AND_DESCRIPTION, true],
			'drawn signature only' => [false, false, SignerElementsService::RENDER_MODE_GRAPHIC_ONLY, true],
			'signer name rendered as image' => [false, false, SignerElementsService::RENDER_MODE_SIGNAME_AND_DESCRIPTION, true],
			'nothing to render' => [false, false, SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY, false],
		];
	}
}
