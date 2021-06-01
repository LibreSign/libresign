<?php

namespace OCA\Libresign\Tests\Unit;

class TestCase extends \Test\TestCase {
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
}