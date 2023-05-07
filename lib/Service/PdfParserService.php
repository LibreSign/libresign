<?php

namespace OCA\Libresign\Service;

use OCA\Libresign\Exception\LibresignException;
use OCP\Files\File;
use OCP\ITempManager;
use Psr\Log\LoggerInterface;

class PdfParserService {
	public function __construct(
		private ITempManager $tempManager,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @param \OCP\Files\File $node
	 *
	 * @return (array[]|int)[]
	 *
	 * @throws LibresignException
	 *
	 * @psalm-return array{p: int, d?: non-empty-list<array{w: mixed, h: mixed}>}
	 */
	public function getMetadata(File $node): array {
		$content = $node->getContent();
		if (!$content) {
			throw new LibresignException('Empty file.');
		}

		/**
		 * Generate temporary file to prevent error when get path of
		 * shared file
		 */
		$tempFile = $this->tempManager->getTemporaryFile('.pdf');
		file_put_contents($tempFile, $content);
		try {
			$output = $this->parsePdfOnlyWithPhp($tempFile);
		} catch (\Throwable $th) {
			$this->logger->error('Impossible get metadata from this file: ' . $th->getMessage());
			throw new LibresignException('Impossible get metadata from this file.');
		}
		return $output;
	}

	private function parsePdfOnlyWithPhp(string $filename): array {
		$parser = new \Smalot\PdfParser\Parser();
		$pdf = $parser->parseFile($filename);

		$pages = $pdf->getPages();
		$output = [
			'p' => count($pages),
		];
		foreach ($pages as $page) {
			$details = $page->getDetails();
			$output['d'][] = [
				'w' => $details['MediaBox'][2],
				'h' => $details['MediaBox'][3]
			];
		}
		return $output;
	}
}
