<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OC\Hooks {
	class Emitter {
		public function emit(string $class, string $value, array $option):void {
		}

		public function listen(string $class, string $value, $closure):void {
		}
	}
}
