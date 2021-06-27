<?php

namespace OCA\Libresign\Controller;

/**
 * @codeCoverageIgnore
 */
class SettingDeprecatedController extends SettingController {
	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @todo remove NoCSRFRequired
	 *
	 * @deprecated 2.4.0 use /setting/has-root-cert
	 * @codeCoverageIgnore
	 */
	public function hasRootCert() {
		return parent::hasRootCert();
	}
}
