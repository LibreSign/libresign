<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Exception\LibresignException;
use OCP\Files\File;
use OCP\ITempManager;
use Psr\Log\LoggerInterface;
use Smalot\PdfParser\Document;

class PdfParserService {
	private string $content = '';
	private ?Document $document = null;
	public function __construct(
		private ITempManager $tempManager,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @throws LibresignException
	 */
	public function setFile(File|string $file): self {
		try {
			if ($file instanceof File) {
				$this->content = $file->getContent();
			} else {
				$this->content = $file;
			}
		} catch (\Throwable $th) {
		}
		if (!$this->content) {
			throw new LibresignException('Empty file.');
		}
		return $this;
	}

	private function getDocument(): Document {
		if (!$this->document) {
			if (!$this->content) {
				throw new LibresignException('File not defined to be parsed.');
			}
			try {
				$parser = new \Smalot\PdfParser\Parser();
				$this->document = $parser->parseContent($this->content);
				return $this->document;
			} catch (\Throwable $th) {
				if ($th->getMessage() === 'Secured pdf file are currently not supported.') {
					throw new LibresignException('Secured pdf file are currently not supported.');
				}
				$this->logger->error('Impossible get metadata from this file: ' . $th->getMessage());
				throw new LibresignException('Impossible get metadata from this file.');
			}
		}
		return $this->document;
	}

	/**
	 * @return (array[]|int)[]
	 * @throws LibresignException
	 * @psalm-return array{p: int, d?: non-empty-list<array{w: mixed, h: mixed}>}
	 */
	public function getPageDimensions(): array {
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
			$widthAndHeight = [
				'w' => $details['MediaBox'][2],
				'h' => $details['MediaBox'][3]
			];
			if (!is_numeric($widthAndHeight['w']) || !is_numeric($widthAndHeight['h'])) {
				$this->logger->error('Impossible get metadata from this file: Error to get page width and height. If possible, open an issue at github.com/libresign/libresign with the file that you used.');
				throw new LibresignException('Impossible get metadata from this file.');
			}
			$output['d'][] = $widthAndHeight;
		}
		return $output;
	}

	public function getPdfVersion(): string {
		preg_match('/^%PDF-(?<version>\d+(\.\d+)?)/', $this->content, $match);
		return $match['version'];
	}
}
