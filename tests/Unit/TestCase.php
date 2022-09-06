<?php

namespace OCA\Libresign\Tests\Unit;

use OCA\Libresign\Tests\lib\AppConfigOverwrite;

class TestCase extends \Test\TestCase {
	use UserTrait;
	protected function IsDatabaseAccessAllowed() {
		$annotations = \PHPUnit\Util\Test::parseTestMethodAnnotations(get_class($this), $this->getName());
		if (isset($annotations['class']['group'])) {
			if (in_array('DB', $annotations['class']['group']) || in_array('SLOWDB', $annotations['class']['group'])) {
				return true;
			}
		}

		return false;
	}

	public function mockConfig($config) {
		$service = \OC::$server->get(\OC\AppConfig::class);
		if (is_subclass_of($service, \OC\AppConfig::class)) {
			foreach ($config as $app => $keys) {
				foreach ($keys as $key => $value) {
					$service->setValue($app, $key, $value);
				}
			}
			return;
		}
		\OC::$server->registerService(\OC\AppConfig::class, function () use ($config) {
			return new AppConfigOverwrite(\OC::$server->get(\OC\DB\Connection::class), $config);
		});
	}
}
