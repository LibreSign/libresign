<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\File\Pdf;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Vendor\Smalot\PdfParser\Document;
use OCP\Files\File;
use OCP\ITempManager;
use Psr\Log\LoggerInterface;

class PdfMetadataExtractor {
	private string $content = '';
	private string $fileName = '';
	private ?Document $document = null;

	public function __construct(
		private PdfParser $pdfParser,
		private ITempManager $tempManager,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @throws LibresignException
	 */
	public function setFile(File $file): self {
		$fileName = $file->getName();
		try {
			$this->content = $file->getContent();
		} catch (\Throwable $e) {
			throw new LibresignException(
				sprintf('Unable to read file "%s": %s', $fileName, $e->getMessage())
			);
		}
		if (!$this->content) {
			throw new LibresignException(sprintf('The file "%s" is empty.', $fileName));
		}
		$this->fileName = $fileName;
		$this->document = null;
		return $this;
	}

	private function getContent(): string {
		if (!$this->content) {
			throw new LibresignException('File not defined to be parsed.');
		}
		return $this->content;
	}

	private function getDocument(): Document {
		if (!$this->document) {
			$content = $this->getContent();
			$this->document = $this->pdfParser->parse($content, $this->fileName);
		}
		return $this->document;
	}

	/**
	 * @return (array[]|int)[]
	 * @throws LibresignException
	 * @psalm-return array{p: int, d?: non-empty-list<array{w: mixed, h: mixed}>}
	 */
	public function getPageDimensions(): array {
		if ($return = $this->getPageDimensionsWithPdfInfo()) {
			return $return;
		}
		return $this->getPageDimensionsWithSmalotPdfParser();
	}

	private function getPageDimensionsWithSmalotPdfParser(): array {
		$document = $this->getDocument();
		$pages = $document->getPages();
		$output = [
			'p' => count($pages),
		];
		foreach ($pages as $page) {
			$details = $page->getDetails();
			if (!isset($details['MediaBox'])) {
				$pages = $document->getObjectsByType('Pages');
				$details = reset($pages)->getHeader()->getDetails();
			}
			if (!isset($details['MediaBox']) || !is_numeric($details['MediaBox'][2]) || !is_numeric($details['MediaBox'][3])) {
				$this->logger->error('Impossible get metadata from this file: Error to get page width and height. If possible, open an issue at github.com/libresign/libresign with the file that you used.');
				throw new LibresignException('Impossible get metadata from this file.');
			}
			$output['d'][] = [
				'w' => $details['MediaBox'][2],
				'h' => $details['MediaBox'][3],
			];
		}
		$pending = $output['p'] - count($output['d']);
		if ($pending) {
			for ($i = 0; $i < $pending; $i++) {
				$output['d'][] = $output['d'][0];
			}
		}
		return $output;
	}

	private function getPageDimensionsWithPdfInfo(): array {
		if (shell_exec('which pdfinfo') === null) {
			return [];
		}
		$content = $this->getContent();
		$filename = $this->tempManager->getTemporaryFile('.pdf');
		file_put_contents($filename, $content);

		// The output of this command go to STDERR and shell_exec get the STDOUT
		// With 2>&1 the STRERR is redirected to STDOUT
		$pdfinfo = shell_exec('pdfinfo ' . $filename . ' -l -1 2>&1');
		if (!$pdfinfo) {
			return [];
		}
		if (!preg_match_all('/Page +\d+ +size: +(\d+\.?\d*) x (\d+\.?\d*)/', (string)$pdfinfo, $pages)) {
			return [];
		}
		$output = [
			'p' => count($pages[1]),
		];
		foreach ($pages[1] as $page => $width) {
			$output['d'][] = [
				'w' => (float)$width,
				'h' => (float)$pages[2][$page],
			];
		}
		return $output;
	}

	public function getPdfVersion(): string {
		preg_match('/^%PDF-(?<version>\d+(\.\d+)?)/', $this->getContent(), $match);
		return $match['version'];
	}
}
