<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\CaIdentifierService;
use OCP\DB\ISchemaWrapper;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;
use Psr\Log\LoggerInterface;

/**
 * Repair migration for CA PKI directory structure
 *
 * This migration fixes issues from Version13000Date20251031165700 where:
 * - CA files may not have been copied to the new PKI structure
 * - config_path points to an empty directory
 * - Metadata (ca_id, config_path) is inconsistent
 *
 * It attempts to:
 * 1. Find CA files in old locations (openssl_config/, cfssl_config/)
 * 2. Find CA files in any pki/ subdirectories
 * 3. Move files to the correct location based on ca_id
 * 4. Synchronize metadata (ca_id, config_path, ca_generation_counter)
 */
class Version17002Date20260221000000 extends SimpleMigrationStep {
	public function __construct(
		private IConfig $config,
		private IAppConfig $appConfig,
		private CaIdentifierService $caIdentifierService,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	#[Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$this->repairCaPkiStructure($output);
	}

	private function repairCaPkiStructure(IOutput $output): void {
		$engineName = $this->appConfig->getValueString(Application::APP_ID, 'certificate_engine', 'openssl');
		if (empty($engineName) || $engineName === 'none') {
			return;
		}

		$configPath = $this->appConfig->getValueString(Application::APP_ID, 'config_path');

		// Check if current config_path has CA files - if yes, nothing to repair
		if ($configPath && is_dir($configPath)) {
			$hasCaPem = file_exists($configPath . '/ca.pem');
			$hasCaKey = file_exists($configPath . '/ca-key.pem');

			if ($hasCaPem && $hasCaKey) {
				$output->info('CA files already exist in configured path. No repair needed.');
				return;
			}
		}

		$dataDir = $this->config->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data/');
		$systemInstanceId = $this->config->getSystemValue('instanceid');
		$rootPath = $dataDir . '/appdata_' . $systemInstanceId . '/libresign/';

		if (!is_dir($rootPath)) {
			return;
		}

		$output->info('Config path is empty or invalid. Attempting repair...');

		// Phase 1: Find CA files in old locations ONLY
		$sourceInfo = $this->findCaFilesInOldLocations($rootPath);

		if (!$sourceInfo) {
			$output->warning('No CA files found in old structure. Manual intervention may be required.');
			$this->logger->warning('Repair migration: No CA files found in old locations', [
				'engine' => $engineName,
				'config_path' => $configPath,
			]);
			return;
		}

		$output->info('Found CA files in old location: ' . $sourceInfo['path']);

		// Phase 2: Determine correct destination based on ca_id or create new one
		$targetInfo = $this->determineTargetLocation($sourceInfo, $engineName, $rootPath);

		if (!$targetInfo) {
			$output->warning('Could not determine target location for CA files');
			return;
		}

		$output->info('Will move CA files to: ' . $targetInfo['path']);

		// Phase 3: Move files to correct location
		$moved = $this->moveCaFiles($sourceInfo, $targetInfo, $output);

		if (!$moved) {
			$output->warning('Failed to move CA files. Check logs for details.');
			return;
		}

		// Phase 4: Update metadata
		$this->updateMetadata($targetInfo, $engineName, $output);

		// Phase 5: Clean up ONLY old structure directories
		$this->cleanupOldStructureOnly($rootPath);

		$output->info('CA PKI structure repair completed successfully');
		$this->logger->info('Repair migration completed', [
			'source' => $sourceInfo['path'],
			'destination' => $targetInfo['path'],
			'generation' => $targetInfo['generation'],
		]);
	}

	/**
	 * Find CA files ONLY in old structure (openssl_config, cfssl_config).
	 * Does NOT look in pki/ to avoid confusion with already-migrated files.
	 *
	 * @return array{path: string, generation: int}|null
	 */
	private function findCaFilesInOldLocations(string $rootPath): ?array {
		$oldFolders = ['openssl_config', 'cfssl_config'];

		foreach ($oldFolders as $folder) {
			$oldPath = $rootPath . $folder;
			if (is_dir($oldPath)) {
				if (file_exists($oldPath . '/ca.pem') && file_exists($oldPath . '/ca-key.pem')) {
					$this->logger->info('Found CA files in old structure', [
						'path' => $oldPath,
					]);
					return [
						'path' => $oldPath,
						'generation' => 0, // Old structure has no generation concept
					];
				}
			}
		}

		return null;
	}

	/**
	 * Determine where CA files should be located
	 *
	 * @return array{path: string, generation: int, instanceId: string}
	 */
	private function determineTargetLocation(array $sourceInfo, string $engineName, string $rootPath): array {
		$currentCaId = $this->appConfig->getValueString(Application::APP_ID, 'ca_id');

		// If we have a valid ca_id, use it to determine generation
		if (!empty($currentCaId)) {
			try {
				$parsed = $this->caIdentifierService->getCaIdParsed();
				$instanceId = $parsed['instanceId'];
				$generation = $parsed['generation'];

				// If generation from source is 0 (old structure), use ca_id generation
				$targetGeneration = ($sourceInfo['generation'] === 0) ? $generation : $sourceInfo['generation'];

				$pkiDirName = $this->caIdentifierService->generatePkiDirectoryNameFromParams($instanceId, $targetGeneration, $engineName);
				$targetPath = $rootPath . ltrim($pkiDirName, '/');

				return [
					'path' => $targetPath,
					'generation' => $targetGeneration,
					'instanceId' => $instanceId,
				];
			} catch (\Exception $e) {
				$this->logger->error('Failed to parse ca_id', ['exception' => $e]);
			}
		}

		// No valid ca_id, create new structure starting with generation 1
		$instanceId = $this->caIdentifierService->getInstanceId();
		$generation = 1;

		$pkiDirName = $this->caIdentifierService->generatePkiDirectoryNameFromParams($instanceId, $generation, $engineName);
		$targetPath = $rootPath . ltrim($pkiDirName, '/');

		return [
			'path' => $targetPath,
			'generation' => $generation,
			'instanceId' => $instanceId,
		];
	}

	private function moveCaFiles(array $sourceInfo, array $targetInfo, IOutput $output): bool {
		$sourcePath = $sourceInfo['path'];
		$targetPath = $targetInfo['path'];

		// If source and target are the same, nothing to do
		if (realpath($sourcePath) === realpath($targetPath)) {
			$output->info('CA files already in correct location');
			return true;
		}

		// Create target directory if it doesn't exist
		if (!is_dir($targetPath)) {
			if (!mkdir($targetPath, 0770, true)) {
				$this->logger->error('Failed to create target directory', ['path' => $targetPath]);
				return false;
			}
		}

		// Check if target already has CA files (don't overwrite)
		$targetHasCaPem = file_exists($targetPath . '/ca.pem');
		$targetHasCaKey = file_exists($targetPath . '/ca-key.pem');

		if ($targetHasCaPem && $targetHasCaKey) {
			$output->info('Target directory already has CA files. Skipping move.');
			$this->logger->info('Target already has CA files', [
				'source' => $sourcePath,
				'target' => $targetPath,
			]);
			return true;
		}

		// Move all files from source to target
		$files = glob($sourcePath . '/*');
		foreach ($files as $file) {
			if (!is_file($file)) {
				continue;
			}

			$filename = basename($file);
			$targetFile = $targetPath . '/' . $filename;

			// Don't overwrite existing files
			if (file_exists($targetFile)) {
				continue;
			}

			if (!copy($file, $targetFile)) {
				$this->logger->error('Failed to copy file', [
					'source' => $file,
					'target' => $targetFile,
				]);
				return false;
			}

			$output->info('Moved: ' . $filename);
		}

		// Verify essential files are in place
		$targetHasCaPem = file_exists($targetPath . '/ca.pem');
		$targetHasCaKey = file_exists($targetPath . '/ca-key.pem');

		if (!$targetHasCaPem || !$targetHasCaKey) {
			$this->logger->error('Essential CA files missing after move', [
				'target' => $targetPath,
				'hasCaPem' => $targetHasCaPem,
				'hasCaKey' => $targetHasCaKey,
			]);
			return false;
		}

		// Delete source files only if everything was copied successfully
		foreach ($files as $file) {
			if (is_file($file)) {
				@unlink($file);
			}
		}

		return true;
	}

	private function updateMetadata(array $targetInfo, string $engineName, IOutput $output): void {
		$instanceId = $targetInfo['instanceId'];
		$generation = $targetInfo['generation'];

		// Generate correct ca_id
		$engineType = $engineName === 'openssl' ? 'o' : 'c';
		$newCaId = sprintf(
			'libresign-ca-id:%s_g:%d_e:%s',
			$instanceId,
			$generation,
			$engineType
		);

		$this->appConfig->setValueString(Application::APP_ID, 'ca_id', $newCaId);
		$this->appConfig->setValueString(Application::APP_ID, 'config_path', $targetInfo['path']);

		// Update ca_generation_counter to ensure next generation is correct
		$currentCounter = $this->appConfig->getValueInt(Application::APP_ID, 'ca_generation_counter', 0);
		if ($generation >= $currentCounter) {
			$this->appConfig->setValueInt(Application::APP_ID, 'ca_generation_counter', $generation);
		}

		$output->info('Updated metadata: ca_id, config_path, ca_generation_counter');
	}

	private function cleanupOldStructureOnly(string $rootPath): void {
		// Only clean up old structure directories (openssl_config, cfssl_config)
		// DO NOT touch pki/ directory or its contents
		$oldFolders = ['openssl_config', 'cfssl_config'];

		foreach ($oldFolders as $folder) {
			$oldPath = $rootPath . $folder;
			if (is_dir($oldPath)) {
				// Only delete if truly empty (no real files)
				$files = scandir($oldPath);
				$hasRealFiles = false;

				foreach ($files as $file) {
					if ($file !== '.' && $file !== '..') {
						$hasRealFiles = true;
						break;
					}
				}

				if (!$hasRealFiles) {
					@rmdir($oldPath);
					$this->logger->info('Removed empty old structure directory', ['path' => $oldPath]);
				} else {
					$this->logger->warning('Old structure directory still has files', [
						'path' => $oldPath,
						'files' => array_diff($files, ['.', '..']),
					]);
				}
			}
		}
	}
}
