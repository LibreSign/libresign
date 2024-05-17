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

use OCP\AppFramework\Services\IAppConfig;
use Psr\Log\LoggerInterface;

class PhpNativeHandler extends SignEngineHandler {
	public function __construct(
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @psalm-suppress MixedReturnStatement
	 */
	public function sign(): string {
		$param = $this->getJSignParam()
			->setCertificate($this->getCertificate())
			->setPdf($this->getInputFile()->getContent())
			->setPassword($this->getPassword());

		$signed = $this->signUsingVisibleElements();
		if ($signed) {
			return $signed;
		}
		$jSignPdf = $this->getJSignPdf();
		$jSignPdf->setParam($param);
		return $this->signWrapper($jSignPdf);
	}

	private function signUsingVisibleElements(): string {
		$visibleElements = $this->getvisibleElements();
		if ($visibleElements) {
			$jSignPdf = $this->getJSignPdf();
			$param = $this->getJSignParam();
			foreach ($visibleElements as $element) {
				$param
					->setJSignParameters(
						$param->getJSignParameters() .
						' -pg ' . $element->getFileElement()->getPage() .
						' -llx ' . $element->getFileElement()->getLlx() .
						' -lly ' . $element->getFileElement()->getLly() .
						' -urx ' . $element->getFileElement()->getUrx() .
						' -ury ' . $element->getFileElement()->getUry() .
						' --l2-text ""' .
						' -V' .
						' --bg-path ' . $element->getTempFile()
					);
				$jSignPdf->setParam($param);
				$signed = $this->signWrapper($jSignPdf);
			}
			return $signed;
		}
		return '';
	}

	private function signWrapper(JSignPDF $jSignPDF): string {
		try {
			return $jSignPDF->sign();
		} catch (\Throwable $th) {
			$rows = str_getcsv($th->getMessage());
			$hashAlgorithm = array_filter($rows, fn ($r) => str_contains($r, 'The chosen hash algorithm'));
			if (!empty($hashAlgorithm)) {
				$hashAlgorithm = current($hashAlgorithm);
				$hashAlgorithm = trim($hashAlgorithm, 'INFO ');
				$hashAlgorithm = str_replace('\"', '"', $hashAlgorithm);
				$hashAlgorithm = preg_replace('/\.( )/', ".\n", $hashAlgorithm);
				throw new LibresignException($hashAlgorithm);
			}
			$this->logger->error('Error at JSignPdf side. LibreSign can not do nothing. Follow the error message: ' . $th->getMessage());
			throw new \Exception($th->getMessage());
		}
	}
}
