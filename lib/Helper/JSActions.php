<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Libresign\Helper;

final class JSActions {
	public const ACTION_REDIRECT = 1000;
	public const ACTION_CREATE_ACCOUNT = 1500;
	public const ACTION_DO_NOTHING = 2000;
	public const ACTION_SIGN = 2500;
	public const ACTION_SIGN_INTERNAL = 2625;
	public const ACTION_SIGN_ACCOUNT_FILE = 2750;
	public const ACTION_SHOW_ERROR = 3000;
	public const ACTION_SIGNED = 3500;
	public const ACTION_CREATE_SIGNATURE_PASSWORD = 4000;
	public const ACTION_RENEW_EMAIL = 4500;
	public const ACTION_INCOMPLETE_SETUP = 5000;
}
