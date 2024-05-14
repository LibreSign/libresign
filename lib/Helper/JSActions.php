<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
