<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Fixtures;

use Symfony\Component\Yaml\Yaml;

class PdfFixtureCatalog {
	private array $catalog;
	private string $basePath;

	public function __construct(?string $catalogPath = null) {
		$this->basePath = $catalogPath ?? __DIR__ . '/../fixtures/pdfs';
		$catalogFile = $this->basePath . '/catalog.yaml';

		if (!file_exists($catalogFile)) {
			throw new \RuntimeException("Catalog file not found: $catalogFile");
		}

		$this->catalog = Yaml::parseFile($catalogFile);
	}

	/** @return PdfFixture[] */
	public function getAll(): array {
		$fixtures = [];
		foreach ($this->catalog['pdfs'] as $pdfData) {
			$fixtures[] = new PdfFixture($this->basePath, $pdfData);
		}
		return $fixtures;
	}

	/**
	 * @return PdfFixture[]
	 * @example getBy(['signature_count' => 1, 'tool' => 'libresign'])
	 */
	public function getBy(array $criteria): array {
		return array_filter($this->getAll(), function (PdfFixture $fixture) use ($criteria) {
			$metadata = $fixture->getMetadata();

			foreach ($criteria as $key => $value) {
				// Handle special filter keys
				switch ($key) {
					case 'has_docmdp':
						if ($value !== $this->hasDocMdp($metadata)) {
							return false;
						}
						break;
					case 'has_tsa':
						if ($value !== $this->hasTsa($metadata)) {
							return false;
						}
						break;
					case 'tool':
						if (!$this->usesTool($metadata, $value)) {
							return false;
						}
						break;
					case 'min_signatures':
						if ($metadata['signature_count'] < $value) {
							return false;
						}
						break;
					case 'is_libresign_ca':
						if (!$this->hasLibresignCa($metadata)) {
							return false;
						}
						break;
					default:
						// Direct property match
						if (!isset($metadata[$key]) || $metadata[$key] !== $value) {
							return false;
						}
				}
			}
			return true;
		});
	}

	public function getByFilename(string $filename): ?PdfFixture {
		foreach ($this->getAll() as $fixture) {
			if ($fixture->getFilename() === $filename) {
				return $fixture;
			}
		}
		return null;
	}

	/** @return PdfFixture[] */
	public function getMultiSignature(): array {
		return $this->getBy(['min_signatures' => 2]);
	}

	/** @return PdfFixture[] */
	public function getWithDocMdp(): array {
		return $this->getBy(['has_docmdp' => true]);
	}

	/** @return PdfFixture[] */
	public function getByTool(string $tool): array {
		return $this->getBy(['tool' => $tool]);
	}

	private function hasDocMdp(array $metadata): bool {
		foreach ($metadata['signatures'] as $sig) {
			if (!empty($sig['features']['docmdp'])) {
				return true;
			}
		}
		return false;
	}

	private function hasTsa(array $metadata): bool {
		foreach ($metadata['signatures'] as $sig) {
			if ($sig['features']['tsa'] === true) {
				return true;
			}
		}
		return false;
	}

	private function usesTool(array $metadata, string $tool): bool {
		foreach ($metadata['signatures'] as $sig) {
			if ($sig['tool'] === $tool) {
				return true;
			}
		}
		return false;
	}

	private function hasLibresignCa(array $metadata): bool {
		foreach ($metadata['signatures'] as $sig) {
			if ($sig['certificate']['is_libresign_ca'] === true) {
				return true;
			}
		}
		return false;
	}
}

class PdfFixture {
	private string $basePath;
	private array $metadata;

	public function __construct(string $basePath, array $metadata) {
		$this->basePath = $basePath;
		$this->metadata = $metadata;
	}

	public function getFilename(): string {
		return $this->metadata['filename'];
	}

	public function getFilePath(): string {
		return $this->basePath . '/' . $this->metadata['filename'];
	}

	public function getDescription(): string {
		return $this->metadata['description'];
	}

	public function getSignatureCount(): int {
		return $this->metadata['signature_count'];
	}

	public function getSignatures(): array {
		return $this->metadata['signatures'];
	}

	public function getTestExpectations(): array {
		return $this->metadata['test_expectations'];
	}

	public function getMetadata(): array {
		return $this->metadata;
	}

	public function shouldExtract(): bool {
		return $this->metadata['test_expectations']['should_extract'];
	}

	public function shouldValidate(): bool {
		return $this->metadata['test_expectations']['should_validate'];
	}

	public function getExpectedModificationStatus(): ?int {
		return $this->metadata['test_expectations']['expected_modifications'];
	}

	/** @return resource */
	public function openResource() {
		$path = $this->getFilePath();
		if (!file_exists($path)) {
			throw new \RuntimeException("PDF fixture not found: $path");
		}
		$resource = fopen($path, 'rb');
		if ($resource === false) {
			throw new \RuntimeException("Failed to open PDF fixture: $path");
		}
		return $resource;
	}

	public function hasDocMdp(): bool {
		foreach ($this->metadata['signatures'] as $sig) {
			if (!empty($sig['features']['docmdp'])) {
				return true;
			}
		}
		return false;
	}

	public function getDocMdpLevel(): ?int {
		foreach ($this->metadata['signatures'] as $sig) {
			if (!empty($sig['features']['docmdp'])) {
				return $sig['features']['docmdp'];
			}
		}
		return null;
	}

	public function hasTsa(): bool {
		foreach ($this->metadata['signatures'] as $sig) {
			if ($sig['features']['tsa'] === true) {
				return true;
			}
		}
		return false;
	}

	public function hasLibresignCa(): bool {
		foreach ($this->metadata['signatures'] as $sig) {
			if ($sig['certificate']['is_libresign_ca'] === true) {
				return true;
			}
		}
		return false;
	}
}
