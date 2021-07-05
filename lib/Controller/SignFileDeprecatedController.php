<?php

namespace OCA\Libresign\Controller;

use OCP\AppFramework\Http\JSONResponse;

/**
 * @deprecated 2.4.0
 */
class SignFileDeprecatedController extends SignFileController {
	use HandleParamsTrait;

	/**
	 * @inheritDoc
	 */
	public function requestSign(array $file, array $users, string $name, ?string $callback = null) {
		return parent::requestSign($file, $users, $name, $callback);
	}

	/**
	 * @inheritDoc
	 */
	public function updateSign(array $users, ?string $uuid = null, ?array $file = []) {
		return parent::updateSign($users, $uuid, $file);
	}

	/**
	 * @inheritDoc
	 */
	public function removeSign(string $uuid, array $users) {
		return parent::removeSign($uuid, $users);
	}
}
