<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\Policy\Contract\IFilePolicyApplier;
use OCA\Libresign\Service\Policy\Provider\PolicyProviders;
use OCP\IL10N;

class FilePolicyApplier {
	/** @var list<IFilePolicyApplier> */
	private readonly array $appliers;

	public function __construct(
		private readonly PolicyService $policyService,
		private readonly FileService $fileService,
		private readonly IL10N $l10n,
	) {
		$this->appliers = $this->discoverAppliers();
	}

	/**
	 * Apply all policies to a freshly built FileEntity before the first insert.
	 */
	public function applyAll(FileEntity $file, array $data): void {
		foreach ($this->appliers as $applier) {
			$applier->apply($file, $data);
		}
	}

	/**
	 * Re-evaluate and persist signature_flow + docmdp on an existing file.
	 * Use this when updating a file located by UUID.
	 */
	public function syncCoreFlowPolicies(FileEntity $file, array $data): void {
		foreach ($this->appliers as $applier) {
			if ($applier->supportsCoreFlowSync()) {
				$applier->sync($file, $data);
			}
		}
	}

	/**
	 * Re-evaluate and persist all three policies on an existing file.
	 * Use this when updating a file located by node ID.
	 */
	public function syncAllPolicies(FileEntity $file, array $data): void {
		foreach ($this->appliers as $applier) {
			$applier->sync($file, $data);
		}
	}

	/** @return list<IFilePolicyApplier> */
	private function discoverAppliers(): array {
		$appliers = [];

		foreach (PolicyProviders::BY_KEY as $providerClass) {
			$applierClass = $this->buildFileApplierClassFromProvider($providerClass);
			if ($applierClass === null || !class_exists($applierClass)) {
				continue;
			}

			$instance = new $applierClass($this->policyService, $this->fileService, $this->l10n);
			if (!$instance instanceof IFilePolicyApplier) {
				continue;
			}

			$appliers[] = $instance;
		}

		return $appliers;
	}

	/** @param class-string $providerClass */
	private function buildFileApplierClassFromProvider(string $providerClass): ?string {
		$lastSeparator = strrpos($providerClass, '\\');
		if ($lastSeparator === false) {
			return null;
		}

		$namespace = substr($providerClass, 0, $lastSeparator);
		$shortName = substr($providerClass, $lastSeparator + 1);
		$baseName = str_ends_with($shortName, 'Policy')
			? substr($shortName, 0, -strlen('Policy'))
			: $shortName;

		return $namespace . '\\FilePolicy\\' . $baseName . 'FilePolicyApplier';
	}
}
