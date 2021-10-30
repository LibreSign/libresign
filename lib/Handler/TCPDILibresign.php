<?php

namespace OCA\Libresign\Handler;

use OCP\Files\File;
use TCPDI;
use tcpdi_parser;

class TCPDILibresign extends TCPDI {
	protected $tcpdflink = false;
	public function __construct($orientation = 'P', $unit = 'mm', $format = 'A4', $unicode = true, $encoding = 'UTF-8', $diskcache = false, $pdfa = false) {
		parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache, $pdfa);
		$this->setPrintHeader(false);
		$this->setPrintFooter(false);
	}

	public function setNextcloudSourceFile(File $inputFile): int {
		$filename = $inputFile->getName();
		$this->current_filename = $filename;

		if (!isset($this->parsers[$filename])) {
			$this->parsers[$filename] = new tcpdi_parser($inputFile->getContent(), $filename);
		}
		$this->current_parser = & $this->parsers[$filename];
		$this->setPDFVersion(max($this->getPDFVersion(), $this->current_parser->getPDFVersion()));

		return $this->parsers[$filename]->getPageCount();
	}

	/**
	 * @inheritDoc
	 */
	protected function _textstring($s, $n = 0) {
		if (preg_match('/TCPDF.*\(http.*\)/', $s)) {
			$s = 'LibreSign (https://libresign.coop)';
		}
		return parent::_textstring($s, $n);
	}

	public function getPageTplDimension($pageNum): array {
		if (!$this->tpls) {
			return [];
		}
		return [
			'w' => $this->tpls[$pageNum]['w'],
			'h' => $this->tpls[$pageNum]['h']
		];
	}

	public function getPagesMetadata() {
		$pageCount = current($this->parsers)->getPageCount();
		$data = [
			'p' => $pageCount
		];
		for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
			$dimensions = $this->getPageTplDimension($pageNo);
			if (empty($dimensions['w'])) {
				$this->importPage($pageNo);
				$dimensions = $this->getPageTplDimension($pageNo);
			}
			$data['d'][] = $dimensions;
		}
		return $data;
	}
}
