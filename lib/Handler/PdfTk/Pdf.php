<?php

declare(strict_types=1);

namespace OCA\Libresign\Handler\PdfTk;

use mikehaertl\pdftk\Pdf as BasePdf;
use mikehaertl\shellcommand\Command;

class Pdf extends BasePdf {

	/**
	 * @inheritDoc
	 */
	public function setCommand(Command $command)
	{
		$this->_command = $command;
	}
}
