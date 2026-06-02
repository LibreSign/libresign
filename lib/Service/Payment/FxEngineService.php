<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Payment;

use DateTimeImmutable;
use DateTimeZone;
use OCA\Libresign\Service\Payment\DTO\FxEngineResultDTO;
use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * FX Engine (USD Base Strategy)
 *
 * Converts:
 *   KES → USD → TARGET CURRENCY
 *
 * WHY:
 * - Free FX APIs only support USD base
 * - We compute cross-rate instead of direct KES pairs
 *
 * FORMULA:
 *   rate(KES → TARGET) = (USD → TARGET) / (USD → KES)
 *
 * DESIGN PRINCIPLES:
 * - KES remains source of truth (amount column)
 * - FX is for display + provider currency
 * - Rates are LOCKED at payment initiation
 * - fxRate is stored as STRING (DECIMAL safe)
 * - Fallback chain ensures resilience
 *
 * PROVIDERS:
 * 1. ExchangeRate-API (primary)
 * 2. OpenExchangeRates (secondary)
 * 3. Emergency fixed rates (last resort)
 */
class FxEngineService
{
	private const BASE_CURRENCY = 'KES';

	/**
	 * External providers (USD base)
	 */
	private const EXCHANGE_RATE_API_KEY = '5b9902a12b225b7e15ce1f65';
	private const EXCHANGE_RATE_API_URL = 'https://v6.exchangerate-api.com/v6/';

	private const OPEN_EXCHANGE_RATES_APP_ID = 'e56d9bf7a1bf4ca1aff1dc61d9ead1d2';
	private const OPEN_EXCHANGE_RATES_API_URL = 'https://openexchangerates.org/api/latest.json';

	/**
	 * Max age before rate considered stale
	 */
	private const MAX_RATE_AGE_SECONDS = 4 * 60 * 60;

	/**
	 * Emergency fallback rates (KES → TARGET)
	 * Conservative to avoid undercharging
	 */
	private const EMERGENCY_RATES = [
		'TZS' => '23.50',
		'UGX' => '28.80',
		'RWF' => '9.20',
		'MWK' => '6.80',
		'ZMW' => '0.17',
		'ZWL' => '0.12',
	];

	private array $rateCache = [];

	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly IClientService $clientService,
	) {}

	// -------------------------------------------------------------------------
	// PUBLIC API
	// -------------------------------------------------------------------------

	public function convert(int $kesAmount, string $targetCurrency): FxEngineResultDTO
	{
		$targetCurrency = strtoupper($targetCurrency);

		if ($targetCurrency === self::BASE_CURRENCY) {
			return $this->identityResult($kesAmount);
		}

		if (!$this->supports($targetCurrency)) {
			throw new RuntimeException("Unsupported currency: {$targetCurrency}");
		}

		['rate' => $rate, 'source' => $source, 'fetchedAt' => $fetchedAt]
			= $this->resolveRate($targetCurrency);

		$displayAmount = $this->applyRate($kesAmount, $rate, $targetCurrency);

		return new FxEngineResultDTO(
			displayAmount: $displayAmount,
			displayCurrency: $targetCurrency,
			fxRate: $rate,
			fxRateSource: $source,
			fxRateLockedAt: $fetchedAt,
		);
	}

	public function currencyForRegion(string $region): ?string
	{
		foreach (CurrencyConfig::SUPPORTED_CURRENCIES as $currency => $config) {
			if (in_array(strtoupper($region), $config['countries'], true)) {
				return $currency;
			}
		}
		return null;
	}

	public function supports(string $currency): bool
	{
		return isset(CurrencyConfig::SUPPORTED_CURRENCIES[$currency]);
	}

	// -------------------------------------------------------------------------
	// RATE RESOLUTION (USD BASE)
	// -------------------------------------------------------------------------

	private function resolveRate(string $currency): array
	{
		if ($this->isCacheHit($currency)) {
			return $this->rateCache[$currency];
		}

		$rate = $this->fetchFromExchangeRateApi($currency);
		if ($rate !== null) {
			return $this->cache($currency, $rate, 'exchangerate-api');
		}

		$rate = $this->fetchFromOpenExchangeRates($currency);
		if ($rate !== null) {
			return $this->cache($currency, $rate, 'open-exchange-rates');
		}

		$rate = self::EMERGENCY_RATES[$currency] ?? null;

		if ($rate !== null) {
			$this->logger->error('FX emergency rate used', [
				'currency' => $currency,
				'rate' => $rate,
			]);

			return $this->cache($currency, $rate, 'emergency-fixed');
		}

		throw new RuntimeException("No FX rate available for {$currency}");
	}

	/**
	 * PRIMARY: ExchangeRate-API
	 *
	 * Returns USD-based rates → compute cross-rate
	 */
	private function fetchFromExchangeRateApi(string $targetCurrency): ?string
	{
		try {
			if (!self::EXCHANGE_RATE_API_KEY) {
				return null;
			}

			$client = $this->clientService->newClient();

			$url = self::EXCHANGE_RATE_API_URL
				. self::EXCHANGE_RATE_API_KEY
				. '/latest/USD';

			$response = $client->get($url);
			$data = json_decode($response->getBody(), true);

			$rates = $data['conversion_rates'] ?? null;

			if (!$rates) {
				return null;
			}

			$usdToKes = $rates['KES'] ?? null;
			$usdToTarget = $rates[$targetCurrency] ?? null;

			if (!$usdToKes || !$usdToTarget || $usdToKes <= 0 || $usdToTarget <= 0) {
				return null;
			}

			// CROSS RATE: (USD → TARGET) / (USD → KES)
			$rate = $usdToTarget / $usdToKes;

			return number_format($rate, 6, '.', '');
		} catch (\Throwable $e) {
			$this->logger->warning('FX primary provider failed', [
				'currency' => $targetCurrency,
				'error' => $e->getMessage(),
			]);
			return null;
		}
	}

	/**
	 * SECONDARY: OpenExchangeRates
	 */
	private function fetchFromOpenExchangeRates(string $targetCurrency): ?string
	{
		try {
			if (!self::OPEN_EXCHANGE_RATES_APP_ID) {
				return null;
			}

			$client = $this->clientService->newClient();

			$url = self::OPEN_EXCHANGE_RATES_API_URL
				. '?app_id=' . self::OPEN_EXCHANGE_RATES_APP_ID
				. '&symbols=KES,' . $targetCurrency;

			$response = $client->get($url);
			$data = json_decode($response->getBody(), true);

			$usdToKes = $data['rates']['KES'] ?? null;
			$usdToTarget = $data['rates'][$targetCurrency] ?? null;

			if (!$usdToKes || !$usdToTarget || $usdToKes <= 0 || $usdToTarget <= 0) {
				return null;
			}

			$rate = $usdToTarget / $usdToKes;

			return number_format($rate, 6, '.', '');
		} catch (\Throwable $e) {
			$this->logger->warning('FX secondary provider failed', [
				'currency' => $targetCurrency,
				'error' => $e->getMessage(),
			]);
			return null;
		}
	}

	// -------------------------------------------------------------------------
	// CALCULATION
	// -------------------------------------------------------------------------

	private function applyRate(int $baseAmountMinor, string $rate, string $targetCurrency): int
	{
		// Base currency decimals (e.g. KES = 2)
		$baseDecimals = CurrencyConfig::SUPPORTED_CURRENCIES[self::BASE_CURRENCY]['decimals'];

		// Target currency decimals (e.g. TZS = 0, GHS = 2)
		$targetDecimals = CurrencyConfig::SUPPORTED_CURRENCIES[$targetCurrency]['decimals'];

		// Convert rate safely
		$rateFloat = (float) $rate;

		// Convert base minor → major (e.g. cents → shillings)
		$baseMajor = $baseAmountMinor / (10 ** $baseDecimals);

		// Apply FX rate in major units
		$targetMajor = $baseMajor * $rateFloat;

		// Convert back to target minor units with correct rounding
		if ($targetDecimals === 0) {
			// No decimals (UGX, TZS, RWF, etc.)
			return (int) round($targetMajor, 0, PHP_ROUND_HALF_UP);
		}

		return (int) round(
			$targetMajor * (10 ** $targetDecimals),
			0,
			PHP_ROUND_HALF_UP
		);
	}

	public function identityResult(int $kesAmount): FxEngineResultDTO
	{
		$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

		return new FxEngineResultDTO(
			displayAmount: $kesAmount,
			displayCurrency: self::BASE_CURRENCY,
			fxRate: 1.000000,
			fxRateSource: 'identity',
			fxRateLockedAt: $now,
		);
	}

	// -------------------------------------------------------------------------
	// CACHE
	// -------------------------------------------------------------------------

	private function isCacheHit(string $currency): bool
	{
		if (!isset($this->rateCache[$currency])) {
			return false;
		}

		$age = time() - $this->rateCache[$currency]['fetchedAt']->getTimestamp();

		return $age < self::MAX_RATE_AGE_SECONDS;
	}

	private function cache(string $currency, string $rate, string $source): array
	{
		$entry = [
			'rate'      => $rate,
			'source'    => $source,
			'fetchedAt' => new DateTimeImmutable('now', new DateTimeZone('UTC')),
		];

		$this->rateCache[$currency] = $entry;

		return $entry;
	}
}
