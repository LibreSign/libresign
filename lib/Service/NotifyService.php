<?php

namespace OCA\Libresign\Service;

use OCA\Libresign\Helper\ValidateHelper;
use OCP\IUserSession;

class NotifyService {
	/** @var ValidateHelper */
	private $validateHelper;
	/** @var IUserSession */
	private $userSession;

	public function __construct(
		ValidateHelper $validateHelper,
		IUserSession $userSession
	) {
		$this->validateHelper = $validateHelper;
		$this->userSession = $userSession;
	}
	public function signers(int $nodeId, array $signers) {
		$this->validateHelper->canRequestSign($this->userSession->getUser());
		$this->validateHelper->validateFileByNodeId($nodeId);
		$this->validateHelper->iRequestedSignThisFile($this->userSession->getUser(), $nodeId);
		foreach ($signers as $signer) {
			$this->validateHelper->haveValidMail($signer);
		}
	}
}
