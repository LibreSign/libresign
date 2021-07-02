<?php

namespace OCA\Libresign\Service;

use OCA\Libresign\Helper\ValidateHelper;
use OCP\IUserSession;

class NotifyService {
	/** @var ValidateHelper */
	private $validateHelper;
	/** @var IUserSession */
	private $userSession;
	/** @var MailService */
	private $mailService;

	public function __construct(
		ValidateHelper $validateHelper,
		IUserSession $userSession,
		MailService $mailService
	) {
		$this->validateHelper = $validateHelper;
		$this->userSession = $userSession;
		$this->mailService = $mailService;
	}

	public function signers(int $nodeId, array $signers) {
		$this->validateHelper->canRequestSign($this->userSession->getUser());
		$this->validateHelper->validateLibreSignNodeId($nodeId);
		$this->validateHelper->iRequestedSignThisFile($this->userSession->getUser(), $nodeId);
		foreach ($signers as $signer) {
			$this->validateHelper->haveValidMail($signer);
			$this->validateHelper->signerWasAssociated($signer);
			$this->validateHelper->notSigned($signer);
		}
		foreach ($signers as $signer) {
			$this->mailService->notifyUnsignedUser();
		}
	}
}
