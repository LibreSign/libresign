<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\PdfTk;

use mikehaertl\pdftk\Pdf as BasePdf;
use mikehaertl\shellcommand\Command;

class Pdf extends BasePdf {
	/**
	 * @inheritDoc
	 */
	public function setCommand(Command $command): void {
		$this->_command = $command;
	}
}
