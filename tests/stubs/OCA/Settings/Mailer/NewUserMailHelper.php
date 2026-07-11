<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Settings\Mailer {
	abstract class NewUserMailHelper {
		abstract public function generateTemplate(\OCP\IUser $user, bool $generatePasswordResetToken): mixed;

		abstract public function sendMail(\OCP\IUser $user, mixed $emailTemplate): void;
	}
}