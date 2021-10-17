<?php

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCP\IConfig;

class SignatureService {
	/** @var IConfig */
	private $config;

	public function __construct(
		IConfig $config
	) {
		$this->config = $config;
	}

	public function hasRootCert(): bool {
		return !empty($this->config->getAppValue(Application::APP_ID, 'authkey'));
	}
}
