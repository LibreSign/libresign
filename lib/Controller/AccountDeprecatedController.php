<?php

namespace OCA\Libresign\Controller;

use OCP\AppFramework\Http\JSONResponse;

/**
 * @deprecated 2.4.0 Use AccountController
 * @codeCoverageIgnore
 */
class AccountDeprecatedController extends AccountController {

	/**
	 * Who am I.
	 *
	 * Validates API access data and returns the authenticated user's data.
	 *
	 * @deprecated 2.4.0 Use /account/me
	 * @codeCoverageIgnore
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 * @PublicPage
	 * @return JSONResponse
	 */
	public function me() {
		return parent::me();
	}
}
