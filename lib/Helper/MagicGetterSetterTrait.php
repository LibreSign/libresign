<?php

namespace OCA\Libresign\Helper;

trait MagicGetterSetterTrait {
	public function __call($name, $arguments) {
		if (!preg_match('/^(?<type>get|set)(?<property>.+)/', $name, $matches)) {
			throw new \LogicException(sprintf('Cannot set non existing property %s->%s = %s.', \get_class($this), $name, var_export($arguments, true)));
		}
		$property = lcfirst($matches['property']);
		if (!property_exists($this, $property)) {
			throw new \LogicException(sprintf('Cannot set non existing property %s->%s = %s.', \get_class($this), $name, var_export($arguments, true)));
		}
		if ($matches['type'] === 'get') {
			return $this->$property;
		}
		$this->$property = $arguments[0] ?? null;
		return $this;
	}
}
