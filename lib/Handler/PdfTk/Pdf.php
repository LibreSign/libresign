<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\PdfTk;

use mikehaertl\pdftk\Pdf as BasePdf;
use mikehaertl\shellcommand\Command;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\JavaHelper;
use OCP\IAppConfig;
use OCP\IL10N;
use RuntimeException;

class Pdf extends BasePdf {
	private string $javaPath = '';
	private string $pdftkPath = '';

	public function __construct(
		private JavaHelper $javaHelper,
		private IAppConfig $appConfig,
		private IL10N $l10n,
	) {
	}

	public function applyStamp(string $input, string $stamp): string {
		$this->configureCommand();

		$this->addFile($input);

		$buffer = $this->multiStamp($stamp)->toString();

		if (!is_string($buffer)) {
			throw new RuntimeException('Failed to merge the PDF with the footer.');
		}

		return $buffer;
	}

	protected function configureCommand(): void {
		$this->javaPath = $this->javaHelper->getJavaPath();
		if ($this->javaPath === '') {
			throw new RuntimeException('Java path not set.');
		}

		$this->pdftkPath = $this->appConfig->getValueString(Application::APP_ID, 'pdftk_path');
		if ($this->pdftkPath === '') {
			throw new RuntimeException('PDFtk path not set.');
		}


		if (!file_exists($this->javaPath) || !file_exists($this->pdftkPath)) {
			throw new LibresignException($this->l10n->t('The admin hasn\'t set up LibreSign yet, please wait.'));
		}

		$cmd = sprintf('%s -jar %s', escapeshellcmd($this->javaPath), escapeshellarg($this->pdftkPath));

		$this->_command = new Command();
		$this->_command->setCommand($cmd);
	}
}
