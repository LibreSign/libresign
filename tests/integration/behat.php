<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use Behat\Config\Config;
use Behat\Config\Extension;
use Behat\Config\Profile;
use Behat\Config\Suite;
use LibreSign\Behat\MailpitExtension\Context\MailpitContext;
use LibreSign\Behat\MailpitExtension\ServiceContainer\MailpitExtension;
use LibreSign\Behat\TsaExtension\ServiceContainer\TsaExtension;
use PhpBuiltin\Server;

return (new Config())
	->withProfile((new Profile('default', [
		'autoload' => [
			'' => '%paths.base%/features/bootstrap',
		],
	]))
		->withSuite((new Suite('default'))
			->withContexts(MailpitContext::class, FeatureContext::class)
			->withPaths('%paths.base%/features'))
		->withExtension(new Extension(MailpitExtension::class, [
			'base_url' => 'http://mailpit:8025',
		]))
		->withExtension(new Extension(Server::class, [
			'rootDir' => dirname(__DIR__, 4),
			'runAs' => 'www-data',
			'workers' => 2,
		]))
		->withExtension(new Extension(TsaExtension::class)))
;
