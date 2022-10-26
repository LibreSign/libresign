<?php

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCP\IConfig;

class AdminSignatureService {
	/** @var IConfig */
	private $config;

	public function __construct(
		IConfig $config
	) {
		$this->config = $config;
	}

	/**
	 * @return string[]
	 */
	public function loadKeys(): array {
		$return = [
			'cfsslUri' => $this->config->getAppValue(Application::APP_ID, 'cfsslUri'),
			'configPath' => $this->config->getAppValue(Application::APP_ID, 'configPath'),
			'rootCert' => [
				'names' => [],
			],
		];
		$rootCert = $this->config->getAppValue(Application::APP_ID, 'rootCert');
		$rootCert = json_decode($rootCert, true);
		if (is_array($rootCert)) {
			$return['rootCert'] = array_merge($return['rootCert'], $rootCert);
		}
		return $return;
	}
}
