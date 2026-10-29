<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Signing;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\Signing\SignedPdfProvider;
use OCP\AppFramework\Http;
use OCP\Files\File as FileNode;
use OCP\IUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SignedPdfProviderTest extends TestCase {
	public function testReturnsThePersistedSignedPdfToItsOwner(): void {
		$file = $this->signedDocument();
		$mapper = $this->createMock(FileMapper::class);
		$mapper->method('getById')->with(25)->willReturn($file);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');
		$node = $this->createMock(FileNode::class);
		$node->method('getMimeType')->willReturn('application/pdf');
		$node->method('getContent')->willReturn('%PDF-1.7 signed document');
		$node->method('getName')->willReturn('signed.pdf');
		$folder = $this->createMock(FolderService::class);
		$folder->expects(self::once())->method('getReadableNodeById')->with('admin', 50)->willReturn($node);

		$provider = new SignedPdfProvider($mapper, $folder);

		self::assertSame([
			'content' => '%PDF-1.7 signed document',
			'filename' => 'signed.pdf',
			'uuid' => 'file-uuid',
		], $provider->getSignedPdf(25, $user));
	}

	#[DataProvider('unavailableDocumentScenarios')]
	public function testRejectsDocumentsWithoutCompletedOwnerSignature(?string $owner, int $status, ?string $hash, ?int $nodeId): void {
		$file = $this->signedDocument();
		$file->setUserId($owner);
		$file->setStatus($status);
		$file->setSignedHash($hash);
		$file->setSignedNodeId($nodeId);
		$mapper = $this->createMock(FileMapper::class);
		$mapper->method('getById')->willReturn($file);
		$folder = $this->createMock(FolderService::class);
		$folder->expects(self::never())->method('getReadableNodeById');
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(Http::STATUS_CONFLICT);
		(new SignedPdfProvider($mapper, $folder))->getSignedPdf(25, $user);
	}

	public static function unavailableDocumentScenarios(): iterable {
		yield 'not owner' => ['another-account', FileStatus::SIGNED->value, 'hash', 50];
		yield 'still pending' => ['admin', FileStatus::ABLE_TO_SIGN->value, 'hash', 50];
		yield 'missing hash' => ['admin', FileStatus::SIGNED->value, null, 50];
		yield 'missing signed node' => ['admin', FileStatus::SIGNED->value, 'hash', null];
	}

	#[DataProvider('invalidPdfScenarios')]
	public function testDoesNotReturnUnreadableOrNonPdfContent(string $mime, string $content, int $status): void {
		$mapper = $this->createMock(FileMapper::class);
		$mapper->method('getById')->willReturn($this->signedDocument());
		$node = $this->createMock(FileNode::class);
		$node->method('getMimeType')->willReturn($mime);
		$node->method('getContent')->willReturn($content);
		$folder = $this->createMock(FolderService::class);
		$folder->method('getReadableNodeById')->willReturn($node);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode($status);
		(new SignedPdfProvider($mapper, $folder))->getSignedPdf(25, $user);
	}

	public static function invalidPdfScenarios(): iterable {
		yield 'wrong MIME' => ['text/plain', '%PDF-1.7', Http::STATUS_FORBIDDEN];
		yield 'invalid bytes' => ['application/pdf', 'not a PDF', Http::STATUS_INTERNAL_SERVER_ERROR];
	}

	private function signedDocument(): File {
		$file = new File();
		$file->setId(25);
		$file->setUuid('file-uuid');
		$file->setUserId('admin');
		$file->setStatus(FileStatus::SIGNED->value);
		$file->setSignedHash('hash');
		$file->setSignedNodeId(50);
		return $file;
	}
}
