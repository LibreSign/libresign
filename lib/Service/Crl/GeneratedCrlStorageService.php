<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Crl;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Enum\CertificateEngineType;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFolder;

class GeneratedCrlStorageService {
	private const STORAGE_ROOT = 'generated_crl';
	private const GENERATED_CRL_FILE = 'crl.der';
	private const METADATA_FILE = 'meta.json';

	public function __construct(
		private IAppDataFactory $appDataFactory,
		private IRootFolder $rootFolder,
	) {
	}

	public function getScopeKey(string $instanceId, int $generation, string $engineType): string {
		$normalizedEngineType = $this->normalizeEngineType($engineType)->value;

		return implode('/', [
			$instanceId,
			(string)$generation,
			$normalizedEngineType,
		]);
	}

	public function read(string $instanceId, int $generation, string $engineType): ?string {
		try {
			$scopeFolder = $this->getScopeFolder($this->getScopeRelativePath($instanceId, $generation, $engineType));
		} catch (NotFoundException) {
			return null;
		}

		if ($scopeFolder === null) {
			return null;
		}

		if (!$scopeFolder->fileExists(self::GENERATED_CRL_FILE)) {
			return null;
		}

		return $scopeFolder->getFile(self::GENERATED_CRL_FILE)->getContent();
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function readMetadata(string $instanceId, int $generation, string $engineType): ?array {
		try {
			$scopeFolder = $this->getScopeFolder($this->getScopeRelativePath($instanceId, $generation, $engineType));
		} catch (NotFoundException) {
			return null;
		}

		if ($scopeFolder === null) {
			return null;
		}

		if (!$scopeFolder->fileExists(self::METADATA_FILE)) {
			return null;
		}

		$rawMetadata = $scopeFolder->getFile(self::METADATA_FILE)->getContent();
		$decoded = json_decode($rawMetadata, true);
		if (!is_array($decoded)) {
			return null;
		}

		return $decoded;
	}

	public function getMTime(string $instanceId, int $generation, string $engineType): ?int {
		try {
			$scopeFolder = $this->getScopeFolder($this->getScopeRelativePath($instanceId, $generation, $engineType));
		} catch (NotFoundException) {
			return null;
		}

		if ($scopeFolder === null) {
			return null;
		}

		if (!$scopeFolder->fileExists(self::GENERATED_CRL_FILE)) {
			return null;
		}

		return $scopeFolder->getFile(self::GENERATED_CRL_FILE)->getMTime();
	}

	/**
	 * @param array<string, mixed> $metadata
	 */
	public function write(string $instanceId, int $generation, string $engineType, string $crlDer, array $metadata = []): void {
		$relativePath = $this->getScopeRelativePath($instanceId, $generation, $engineType);
		$scopeFolder = $this->ensureFolderExists($relativePath);
		if ($scopeFolder === null) {
			return;
		}

		$this->writeScopeFile($relativePath, $scopeFolder, self::GENERATED_CRL_FILE, $crlDer);

		if ($metadata !== []) {
			$this->writeScopeFile(
				$relativePath,
				$scopeFolder,
				self::METADATA_FILE,
				json_encode($metadata, JSON_THROW_ON_ERROR)
			);
		}
	}

	public function delete(string $instanceId, int $generation, string $engineType): void {
		try {
			$scopeFolder = $this->getScopeFolder($this->getScopeRelativePath($instanceId, $generation, $engineType));
		} catch (NotFoundException) {
			// Nothing persisted for this scope yet.
			return;
		}

		if ($scopeFolder === null) {
			return;
		}

		$scopeFolder->delete();
	}

	private function getScopeRelativePath(string $instanceId, int $generation, string $engineType): string {
		return self::STORAGE_ROOT . '/' . $this->getScopeKey($instanceId, $generation, $engineType);
	}

	private function getScopeAbsolutePath(string $relativePath): string {
		return '/' . $this->rootFolder->getAppDataDirectoryName() . '/' . Application::APP_ID . '/' . ltrim($relativePath, '/');
	}

	private function ensureFolderExists(string $relativePath): ?ISimpleFolder {
		$folder = $this->getAppDataRootFolder();
		if ($folder === null) {
			return null;
		}

		foreach (explode('/', trim($relativePath, '/')) as $segment) {
			if ($segment === '') {
				continue;
			}

			try {
				$folder = $folder->getFolder($segment);
			} catch (NotFoundException) {
				$folder = $folder->newFolder($segment);
			}
		}

		return $folder;
	}

	private function getScopeFolder(string $relativePath): ?ISimpleFolder {
		$folder = $this->getAppDataRootFolder();
		if ($folder === null) {
			return null;
		}

		foreach (explode('/', trim($relativePath, '/')) as $segment) {
			if ($segment === '') {
				continue;
			}

			$folder = $folder->getFolder($segment);
		}

		return $folder;
	}

	private function getAppDataRootFolder(): ?ISimpleFolder {
		try {
			return $this->appDataFactory->get(Application::APP_ID)->getFolder('/');
		} catch (\TypeError $exception) {
			if (!$this->isKnownAppDataBootstrapFailure($exception)) {
				throw $exception;
			}

			return null;
		}
	}

	private function getScopeFolderNode(string $relativePath): Folder {
		$scopeFolder = $this->rootFolder->get($this->getScopeAbsolutePath($relativePath));
		if (!$scopeFolder instanceof Folder) {
			throw new \RuntimeException('Generated CRL storage scope is not a folder');
		}

		return $scopeFolder;
	}

	private function getFileNode(Folder $folder, string $fileName): ?File {
		if (!$folder->nodeExists($fileName)) {
			return null;
		}

		$file = $folder->get($fileName);
		if (!$file instanceof File) {
			return null;
		}

		return $file;
	}

	private function writeScopeFile(string $relativePath, ISimpleFolder $simpleFolder, string $fileName, string $content): void {
		try {
			$this->writeFileAtomically($this->getScopeFolderNode($relativePath), $fileName, $content);
		} catch (NotFoundException $exception) {
			// Some unit-test/bootstrap environments cannot resolve AppData root nodes,
			// so fall back to the SimpleFS handle we already created for this scope.
			$this->writeFileWithSimpleFolder($simpleFolder, $fileName, $content);
		} catch (\TypeError $exception) {
			if (!$this->isKnownAppDataBootstrapFailure($exception)) {
				throw $exception;
			}

			// Some unit-test/bootstrap environments cannot resolve AppData root nodes,
			// so fall back to the SimpleFS handle we already created for this scope.
			$this->writeFileWithSimpleFolder($simpleFolder, $fileName, $content);
		}
	}

	private function writeFileWithSimpleFolder(ISimpleFolder $folder, string $fileName, string $content): void {
		if ($folder->fileExists($fileName)) {
			$folder->getFile($fileName)->putContent($content);
			return;
		}

		$folder->newFile($fileName, $content);
	}

	private function writeFileAtomically(Folder $folder, string $fileName, string $content): void {
		$tmpName = sprintf('.%s.tmp.%s', $fileName, bin2hex(random_bytes(8)));
		$tmpFile = $folder->newFile($tmpName, $content);

		try {
			if ($this->replaceFileAtomicallyOnLocalStorage($folder, $tmpFile, $fileName)) {
				return;
			}

			$tmpFile->move($folder->getFullPath($fileName));
		} finally {
			if ($folder->nodeExists($tmpName)) {
				$folder->get($tmpName)->delete();
			}
		}
	}

	private function replaceFileAtomicallyOnLocalStorage(Folder $folder, File $tmpFile, string $fileName): bool {
		$storage = $folder->getStorage();
		if (!$storage->isLocal()) {
			return false;
		}

		$tmpInternalPath = $tmpFile->getInternalPath();
		$targetInternalPath = $this->getChildInternalPath($folder, $fileName);

		$tmpLocalPath = $storage->getLocalFile($tmpInternalPath);
		$targetLocalPath = $storage->getLocalFile($targetInternalPath);
		if (!is_string($tmpLocalPath) || $tmpLocalPath === '' || !is_string($targetLocalPath) || $targetLocalPath === '') {
			return false;
		}

		if (!@rename($tmpLocalPath, $targetLocalPath)) {
			return false;
		}

		$storage->getUpdater()->renameFromStorage($storage, $tmpInternalPath, $targetInternalPath);
		return true;
	}

	private function getChildInternalPath(Folder $folder, string $childName): string {
		$folderInternalPath = trim($folder->getInternalPath(), '/');
		if ($folderInternalPath === '') {
			return $childName;
		}

		return $folderInternalPath . '/' . $childName;
	}

	private function isKnownAppDataBootstrapFailure(\TypeError $exception): bool {
		return str_contains($exception->getMessage(), 'OC\\Files\\Cache\\Scanner::$connection');
	}

	private function normalizeEngineType(string $engineType): CertificateEngineType {
		$normalizedEngineType = CertificateEngineType::tryFromValue($engineType);
		if ($normalizedEngineType === null) {
			throw new \InvalidArgumentException("Invalid engine type: $engineType");
		}

		return $normalizedEngineType;
	}
}
