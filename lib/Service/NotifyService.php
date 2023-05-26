<?php

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCP\IUserSession;

class NotifyService {
	public function __construct(
		private ValidateHelper $validateHelper,
		private IUserSession $userSession,
		private FileUserMapper $fileUserMapper,
		private IdentifyMethodService $identifyMethodService
	) {
	}

	public function signers(int $nodeId, array $signers): void {
		$this->validateHelper->canRequestSign($this->userSession->getUser());
		$this->validateHelper->validateLibreSignNodeId($nodeId);
		$this->validateHelper->iRequestedSignThisFile($this->userSession->getUser(), $nodeId);
		foreach ($signers as $signer) {
			$this->validateHelper->haveValidMail($signer);
			$this->validateHelper->signerWasAssociated($signer);
			$this->validateHelper->notSigned($signer);
		}
		// @todo refactor this code
		// $fileUsers = $this->fileUserMapper->getByNodeId($nodeId);
		// foreach ($fileUsers as $fileUser) {
		// 	$identifyMethods = $this->identifyMethodService->getIdentifyMethodsFromFileUserId($fileUser->getId());
		// 	$identifyMethod = array_reduce($identifyMethods, function (?IIdentifyMethod $carry, IIdentifyMethod $identifyMethod) use ($signers): ?IIdentifyMethod {
		// 		foreach ($signers as $signer) {
		// 			$key = key($signer);
		// 			$value = current($signer);
		// 			$entity = $identifyMethod->getEntity();
		// 			if ($entity->getIdentifierKey() === $key
		// 				&& $entity->getIdentifierValue() === $value
		// 			) {
		// 				return $identifyMethod;
		// 			}
		// 		}
		// 		return $carry;
		// 	});
		// 	if ($identifyMethod instanceof IIdentifyMethod) {
		// 		$identifyMethod->notify(false, $fileUser);
		// 	}
		// }
	}
}
