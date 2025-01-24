<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
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

	private function getContent(): string {
		if (!$this->content) {
			throw new LibresignException('File not defined to be parsed.');
		}
		return $this->content;
	}

	private function getDocument(): Document {
		if (!$this->document) {
			$content = $this->getContent();
			try {
				$parser = new \Smalot\PdfParser\Parser();
				$this->document = $parser->parseContent($content);
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
		if (!preg_match_all('/Page +\d+ +size: +(\d+\.?\d*) x (\d+\.?\d*)/', $pdfinfo, $pages)) {
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
		preg_match('/^%PDF-(?<version>\d+(\.\d+)?)/', $this->content, $match);
		return $match['version'];
	}
}
