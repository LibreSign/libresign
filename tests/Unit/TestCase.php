<?php

namespace OCA\Libresign\Tests\Unit;

use OCA\Libresign\Tests\lib\AppConfigOverwrite;
use OCA\Libresign\Tests\lib\AppConfigOverwrite20;

class TestCase extends \Test\TestCase {
	use UserTrait;
	protected function IsDatabaseAccessAllowed() {
		// on travis-ci.org we allow database access in any case - otherwise
		// this will break all apps right away
		if (true == getenv('TRAVIS')) {
			return true;
		}
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
			if (\OCP\Util::getVersion()[0] <= '20') {
				return new AppConfigOverwrite20(\OC::$server->get(\OCP\IDBConnection::class), $config);
			} else {
				return new AppConfigOverwrite(\OC::$server->get(\OC\DB\Connection::class), $config);
			}
		});
	}
}
