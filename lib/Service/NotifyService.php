<?php

namespace OCA\Libresign\Service;

use OCA\Libresign\Helper\ValidateHelper;

class NotifyService {
	/** @var ValidateHelper */
	private $validateHelper;

	public function __construct(
		ValidateHelper $validateHelper
	) {
		$this->validateHelper = $validateHelper;
	}
	public function signers(int $fileId, array $signers) {
		$this->validateHelper->validateFileByNodeId($fileId);
	}
}
