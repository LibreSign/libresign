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
	public const ACTION_REDIRECT = 100;
	public const ACTION_CREATE_USER = 150;
	public const ACTION_DO_NOTHING = 200;
	public const ACTION_SIGN = 250;
	public const ACTION_SIGN_ACCOUNT_FILE = 275;
	public const ACTION_SHOW_ERROR = 300;
	public const ACTION_SIGNED = 350;
	public const ACTION_CREATE_SIGNATURE_PASSWORD = 400;
	public const ACTION_RENEW_EMAIL = 450;
	public const ACTION_INCOMPLETE_SETUP = 500;
}
