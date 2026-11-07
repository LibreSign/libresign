<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use Behat\Config\Config;
use Behat\Config\Extension;
use Behat\Config\Formatter\JUnitFormatter;
use Behat\Config\Formatter\PrettyFormatter;
use Behat\Config\Profile;
use Behat\Config\Suite;
use LibreSign\Behat\Sharding\FeatureShardSelector;
use LibreSign\Behat\MailpitExtension\Context\MailpitContext;
use LibreSign\Behat\MailpitExtension\ServiceContainer\MailpitExtension;
use LibreSign\Behat\TsaExtension\ServiceContainer\TsaExtension;
use PhpBuiltin\Server;

// When the CI matrix defines a shard, select whole features using Behat's own suite API.
// Local runs without SHARD_* continue to execute the complete feature suite.
$featurePaths = ['%paths.base%/tests/integration/features'];
$index = getenv('SHARD_INDEX');
$total = getenv('SHARD_TOTAL');
if ($index !== false || $total !== false) {
	require_once __DIR__ . '/tests/integration/sharding/FeatureShardSelector.php';
	$selector = FeatureShardSelector::fromEnvironment([
		'SHARD_INDEX' => $index,
		'SHARD_TOTAL' => $total,
	]);
	$features = FeatureShardSelector::discover(__DIR__ . '/tests/integration/features');
	$selected = $selector->select($features);
	if ($selected === []) {
		throw new RuntimeException('Behat shard is empty; refusing to run the full suite.');
	}
	$featurePaths = array_map(
		static fn (string $path): string => '%paths.base%/tests/integration/features/' . $path,
		$selected,
	);
}

return (new Config())
	->withProfile((new Profile('default', [
		'autoload' => [
			'' => '%paths.base%/tests/integration/features/bootstrap',
		],
	]))
		->withSuite((new Suite('default'))
			->withContexts(MailpitContext::class, FeatureContext::class)
			->withPaths(...$featurePaths))
		->withExtension(new Extension(MailpitExtension::class, [
			'base_url' => 'http://mailpit:8025',
		]))
		->withExtension(new Extension(Server::class, [
			'rootDir' => dirname(__DIR__, 2),
			'runAs' => 'www-data',
			'workers' => 2,
		]))
		->withExtension(new Extension(TsaExtension::class)))
	->withProfile((new Profile('ci'))
		->withFormatter(new PrettyFormatter())
		->withFormatter((new JUnitFormatter())->withOutputPath(
			getenv('BEHAT_JUNIT_OUTPUT_DIR') ?: 'tests/integration/output',
		)))
;
