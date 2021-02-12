<?php

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Exception\LibresignException;
use OCP\IL10N;
use Sabre\DAV\UUIDUtil;

class AccountService {
	/** @var IL10N */
	private $l10n;
	/** @var FileUserMapper */
	private $fileUserMapper;

	public function __construct(
		IL10N $l10n,
		FileUserMapper $fileUserMapper
	) {
		$this->l10n = $l10n;
		$this->fileUserMapper = $fileUserMapper;
	}

	public function validateCreateToSign(array $data) {
		if (!UUIDUtil::validateUUID($data['uuid'])) {
			throw new LibresignException($this->l10n->t('Invalid UUID'), 1);
		}
		try {
			$fileUser = $this->fileUserMapper->getByUuid($data['uuid']);
		} catch (\Throwable $th) {
			throw new LibresignException($this->l10n->t('UUID not found'), 1);
		}
		if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
			throw new LibresignException($this->l10n->t('Invalid email'), 1);
		}
		if ($fileUser->getEmail() != $data['email']) {
			throw new LibresignException($this->l10n->t('Dont is your file'), 1);
		}
		if ($data['password'] != $data['confirmPassword']) {
			throw new LibresignException($this->l10n->t('Password and confirmation dont match'), 1);
		}
		if ($data['signPassword'] != $data['signConfirmPassword']) {
			throw new LibresignException($this->l10n->t('Password and confirmation of signature dont match'), 1);
		}
	}
}
