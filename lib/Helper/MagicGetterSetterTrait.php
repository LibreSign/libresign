<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Helper;

trait MagicGetterSetterTrait {
	public function __call($name, $arguments) {
		if (!preg_match('/^(?<type>get|set)(?<property>.+)/', (string)$name, $matches)) {
			throw new \LogicException(sprintf('Cannot set non existing property %s->%s = %s.', $this::class, $name, var_export($arguments, true)));
		}
		$property = lcfirst($matches['property']);
		if (!property_exists($this, $property)) {
			$property = $matches['property'];
			if (!property_exists($this, $property)) {
				throw new \LogicException(sprintf('Cannot set non existing property %s->%s = %s.', $this::class, $name, var_export($arguments, true)));
			}
		}
		if ($matches['type'] === 'get') {
			return $this->$property;
		}
		$this->$property = $arguments[0] ?? null;
		return $this;
	}
}
