<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FileService;

final class FileServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private function createFileService(array $overrides = []): FileService {
		$mocks = [
			\OCA\Libresign\Db\FileMapper::class,
			\OCA\Libresign\Db\SignRequestMapper::class,
			\OCA\Libresign\Db\FileElementMapper::class,
			\OCA\Libresign\Service\FileElementService::class,
			\OCA\Libresign\Service\FolderService::class,
			\OCA\Libresign\Db\IdDocsMapper::class,
			\OCA\Libresign\Service\IdentifyMethodService::class,
			\OCP\IUserManager::class,
			\OCP\IURLGenerator::class,
			\OCP\Files\IMimeTypeDetector::class,
			\OCA\Libresign\Handler\SignEngine\Pkcs12Handler::class,
			\OCA\Libresign\Handler\DocMdpHandler::class,
			\OCA\Libresign\Service\File\PdfValidator::class,
			\OCP\Files\IRootFolder::class,
			\Psr\Log\LoggerInterface::class,
			\OCP\IL10N::class,
			\OCA\Libresign\Service\EnvelopeService::class,
			\OCA\Libresign\Service\File\SignersLoader::class,
			\OCA\Libresign\Helper\FileUploadHelper::class,
			\OCA\Libresign\Service\File\EnvelopeAssembler::class,
			\OCA\Libresign\Service\File\EnvelopeProgressService::class,
			\OCA\Libresign\Service\File\CertificateChainService::class,
			\OCA\Libresign\Service\File\MimeService::class,
			\OCA\Libresign\Service\File\FileContentProvider::class,
			\OCA\Libresign\Service\File\UploadProcessor::class,
			\OCA\Libresign\Service\File\MetadataLoader::class,
			\OCA\Libresign\Service\File\SettingsLoader::class,
			\OCA\Libresign\Service\File\MessagesLoader::class,
		];

		$args = array_map(function ($c) use ($overrides) {
			return $overrides[$c] ?? $this->createMock($c);
		}, $mocks);

		return new FileService(...$args);
	}

	public function testValidateFileContentSkipsNonPdfFiles(): void {
		$docMdpHandler = $this->createMock(\OCA\Libresign\Handler\DocMdpHandler::class);
		$service = $this->createFileService([
			\OCA\Libresign\Handler\DocMdpHandler::class => $docMdpHandler,
		]);

		$this->expectNotToPerformAssertions();
		$service->validateFileContent('any content', 'txt');
		$service->validateFileContent('{"json": true}', 'json');
	}

	public function testSetFileByTypeThrowsOnInvalid(): void {
		$fileMapper = $this->createMock(\OCA\Libresign\Db\FileMapper::class);
		$fileMapper->method('getByFileId')->willThrowException(new \Exception('not found'));

		$service = $this->createFileService([
			\OCA\Libresign\Db\FileMapper::class => $fileMapper,
		]);

		$this->expectException(LibresignException::class);
		$service->setFileByType('FileId', 123);
	}

	public function testSetFileByTypeSetsFile(): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setStatus(1);

		$fileMapper = $this->createMock(\OCA\Libresign\Db\FileMapper::class);
		$fileMapper->method('getByFileId')->willReturn($file);

		$service = $this->createFileService([
			\OCA\Libresign\Db\FileMapper::class => $fileMapper,
		]);

		$returned = $service->setFileByType('FileId', 123);
		$this->assertInstanceOf(FileService::class, $returned);
		$this->assertSame(1, $service->getStatus());
	}
}
