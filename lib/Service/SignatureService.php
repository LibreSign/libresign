<?php

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCP\IConfig;

class SignatureService {
	public function __construct(
		private IConfig $config
	) {
	}

	public function hasRootCert(): bool {
		return !empty($this->config->getAppValue(Application::APP_ID, 'authkey'));
	}
}
