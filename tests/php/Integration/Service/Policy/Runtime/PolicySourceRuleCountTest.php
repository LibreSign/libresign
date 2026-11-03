<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service\Policy\Runtime;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\Policy\Provider\LegalInformation\LegalInformationPolicy;
use OCA\Libresign\Service\Policy\Provider\Worker\SigningModePolicy;
use OCA\Libresign\Service\Policy\Runtime\PolicySource;
use OCP\Exceptions\AppConfigUnknownKeyException;
use OCP\IAppConfig;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The rule counts are read straight from the appconfig table, so these cases
 * write real rows instead of going through the in-memory app config used by
 * the other integration tests. The keys they touch are restored afterwards, so
 * running them on a development instance keeps its configuration.
 *
 * @group DB
 */
final class PolicySourceRuleCountTest extends \Test\TestCase {
	private const LEGAL_INFORMATION = LegalInformationPolicy::SYSTEM_APP_CONFIG_KEY;
	private const LEGAL_INFORMATION_OVERRIDE = LegalInformationPolicy::SYSTEM_APP_CONFIG_KEY . '.allow_child_override';
	private const SIGNING_MODE = SigningModePolicy::SYSTEM_APP_CONFIG_KEY_SIGNING_MODE;
	private const SIGNING_MODE_OVERRIDE = SigningModePolicy::SYSTEM_APP_CONFIG_KEY_SIGNING_MODE . '.allow_child_override';

	private const TOUCHED_KEYS = [
		self::LEGAL_INFORMATION,
		self::LEGAL_INFORMATION_OVERRIDE,
		self::SIGNING_MODE,
		self::SIGNING_MODE_OVERRIDE,
	];

	private IAppConfig $appConfig;
	private PolicySource $policySource;
	/** @var array<string, array{value: string, type: int, lazy: bool, sensitive: bool}|null> */
	private array $originalValues = [];

	public function setUp(): void {
		parent::setUp();
		$this->appConfig = \OCP\Server::get(IAppConfig::class);
		$this->policySource = \OCP\Server::get(PolicySource::class);
		$this->originalValues = [];
		foreach (self::TOUCHED_KEYS as $key) {
			$this->originalValues[$key] = $this->readDetails($key);
		}
		$this->deleteSystemRules();
	}

	public function tearDown(): void {
		$this->deleteSystemRules();
		foreach ($this->originalValues as $key => $details) {
			if ($details !== null) {
				$this->restore($key, $details);
			}
		}
		parent::tearDown();
	}

	/**
	 * @param \Closure(PolicySource, IAppConfig): void $arrange
	 */
	#[DataProvider('provideSystemRules')]
	public function testCountsOnlyExplicitSystemRules(string $policyKey, \Closure $arrange, int $expected): void {
		$arrange($this->policySource, $this->appConfig);

		self::assertSame($expected, $this->policySource->loadAllRuleCounts()[$policyKey]['everyoneCount'], 'loadAllRuleCounts()');
		self::assertSame($expected, $this->policySource->loadRuleCounts([], [])[$policyKey]['everyoneCount'], 'loadRuleCounts()');
	}

	/**
	 * Check the actual policy API separately from raw appconfig rule-count
	 * fixtures: an explicitly saved default must remain an explicit rule.
	 */
	public function testSavingExplicitDefaultPersistsAndCountsSystemRule(): void {
		$this->policySource->saveSystemPolicy(SigningModePolicy::KEY_SIGNING_MODE, 'sync', false);

		self::assertTrue($this->appConfig->hasKey(Application::APP_ID, self::SIGNING_MODE));
		self::assertSame('sync', $this->appConfig->getValueString(Application::APP_ID, self::SIGNING_MODE));
		self::assertTrue($this->appConfig->hasKey(Application::APP_ID, self::SIGNING_MODE_OVERRIDE));
		self::assertSame('0', $this->appConfig->getValueString(Application::APP_ID, self::SIGNING_MODE_OVERRIDE));
		self::assertSame(1, $this->policySource->loadAllRuleCounts()[SigningModePolicy::KEY_SIGNING_MODE]['everyoneCount']);
		self::assertSame(1, $this->policySource->loadRuleCounts([], [])[SigningModePolicy::KEY_SIGNING_MODE]['everyoneCount']);
	}

	public static function provideSystemRules(): array {
		$store = static fn (array $rows): \Closure => static function (PolicySource $policySource, IAppConfig $appConfig) use ($rows): void {
			foreach ($rows as $key => $value) {
				$appConfig->setValueString(Application::APP_ID, $key, $value);
			}
		};

		return [
			'no system rule' => [LegalInformationPolicy::KEY, $store([]), 0],
			'empty legacy value without an override flag' => [LegalInformationPolicy::KEY, $store([self::LEGAL_INFORMATION => '']), 0],
			'empty value with override flag 0' => [LegalInformationPolicy::KEY, $store([self::LEGAL_INFORMATION => '', self::LEGAL_INFORMATION_OVERRIDE => '0']), 1],
			'empty value with override flag 1' => [LegalInformationPolicy::KEY, $store([self::LEGAL_INFORMATION => '', self::LEGAL_INFORMATION_OVERRIDE => '1']), 1],
			'override flag without a system value' => [LegalInformationPolicy::KEY, $store([self::LEGAL_INFORMATION_OVERRIDE => '1']), 0],
			'non-empty explicit value' => [LegalInformationPolicy::KEY, $store([self::LEGAL_INFORMATION => 'Legal text', self::LEGAL_INFORMATION_OVERRIDE => '0']), 1],
			'non-empty legacy value' => [LegalInformationPolicy::KEY, $store([self::LEGAL_INFORMATION => 'Legal text']), 1],
			'explicit non-empty default' => [
				SigningModePolicy::KEY_SIGNING_MODE,
				$store([self::SIGNING_MODE => 'sync', self::SIGNING_MODE_OVERRIDE => '0']),
				1,
			],
			'rule removed' => [
				LegalInformationPolicy::KEY,
				static function (PolicySource $policySource): void {
					$policySource->saveSystemPolicy(LegalInformationPolicy::KEY, '', false);
					$policySource->clearSystemPolicy(LegalInformationPolicy::KEY);
				},
				0,
			],
		];
	}

	private function deleteSystemRules(): void {
		foreach (self::TOUCHED_KEYS as $key) {
			$this->appConfig->deleteKey(Application::APP_ID, $key);
		}
	}

	/**
	 * @return array{value: string, type: int, lazy: bool, sensitive: bool}|null
	 */
	private function readDetails(string $key): ?array {
		try {
			$details = $this->appConfig->getDetails(Application::APP_ID, $key);
		} catch (AppConfigUnknownKeyException) {
			return null;
		}

		return [
			'value' => (string)$details['value'],
			'type' => (int)$details['type'] & ~IAppConfig::VALUE_SENSITIVE,
			'lazy' => (bool)$details['lazy'],
			'sensitive' => (bool)$details['sensitive'],
		];
	}

	/**
	 * PolicySource writes these keys as strings, while occ and the legacy API
	 * write them as mixed, which only the server implementation can write back.
	 *
	 * @param array{value: string, type: int, lazy: bool, sensitive: bool} $details
	 */
	private function restore(string $key, array $details): void {
		['value' => $value, 'lazy' => $lazy, 'sensitive' => $sensitive] = $details;
		if ($details['type'] === IAppConfig::VALUE_MIXED) {
			self::assertInstanceOf(\OC\AppConfig::class, $this->appConfig);
			$this->appConfig->setValueMixed(Application::APP_ID, $key, $value, $lazy, $sensitive);
			return;
		}
		$this->appConfig->setValueString(Application::APP_ID, $key, $value, $lazy, $sensitive);
	}
}
