<?php

namespace OCA\Libresign\Handler;

use TCPDI;

class TCPDILibresign extends TCPDI {
	/**
	 * @var bool
	 */
	protected $tcpdflink = false;
	public function __construct($orientation = 'P', $unit = 'px', $format = 'A4', $unicode = true, $encoding = 'UTF-8', $diskcache = false, $pdfa = false) {
		parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache, $pdfa);
		$this->setPrintHeader(false);
		$this->setPrintFooter(false);
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

	/**
	 * @psalm-return array{w?: mixed, h?: mixed}
	 */
	public function getPageTplDimension(int $pageNum): array {
		if (!$this->tpls) {
			return [];
		}
		return [
			'w' => $this->tpls[$pageNum]['w'],
			'h' => $this->tpls[$pageNum]['h']
		];
	}

	public function getPagesMetadata(): array {
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
