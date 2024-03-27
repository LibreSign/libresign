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

namespace OCA\Libresign\Handler;

use TCPDF;

class TCPDFLibresign extends TCPDF {
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
