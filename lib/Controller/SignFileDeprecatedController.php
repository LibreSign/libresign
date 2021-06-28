<?php

namespace OCA\Libresign\Controller;

use OCP\AppFramework\Http\JSONResponse;

/**
 * @deprecated 2.4.0
 */
class SignFileDeprecatedController extends SignFileController {
	use HandleParamsTrait;

	/**
	 * Request signature
	 *
	 * Request that a file be signed by a group of people
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @deprecated 2.4.0 Repaced by POST /sign/register
	 * @codeCoverageIgnore
	 * @param array $file
	 * @param array $users
	 * @param string $name
	 * @param string|null $callback
	 * @return JSONResponse
	 */
	public function requestSign(array $file, array $users, string $name, ?string $callback = null) {
		return parent::requestSign($file, $users, $name, $callback);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @codeCoverageIgnore
	 * @deprecated 2.4.0 Repaced by PATCH /sign/register
	 *
	 * @return JSONResponse
	 */
	public function updateSign(string $uuid, array $users) {
		return parent::updateSign($uuid, $users);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @codeCoverageIgnore
	 * @deprecated 2.4.0 Repaced by DELETE /sign/register
	 *
	 * @return JSONResponse
	 */
	public function removeSign(string $uuid, array $users) {
		return parent::removeSign($uuid, $users);
	}
}
