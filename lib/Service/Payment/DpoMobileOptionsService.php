<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Payment;

use OCA\Libresign\Db\DpoMobileOption;
use OCA\Libresign\Db\DpoMobileOptionMapper;
use RuntimeException;

final class DpoMobileOptionsService
{
	public function __construct(
		private DpoProvider $dpo,
		private DpoMobileOptionMapper $mapper,
	) {}

	/**
	 * Get mobile payment options for a transaction
	 *
	 * FLOW:
	 * 1. Try DB (fast path)
	 * 2. If empty → fetch from DPO
	 * 3. Persist (upsert)
	 * 4. Return normalized options
	 */
	public function getOptions(string $providerReference, string $country): array
	{
		// 1. DB first
		$options = $this->mapper->findByCountry($country);

		if (!empty($options)) {
			return $this->toArray($options);
		}

		// 2. Fetch from DPO
		$fetched = $this->dpo->getMobileOptions($providerReference);

		if (empty($fetched)) {
			throw new RuntimeException('No mobile payment options returned from DPO');
		}

		// 3. Normalize + persist
		$entities = [];

		foreach ($fetched as $opt) {

			$entity = new DpoMobileOption();

			$entity->setProvider($this->normalizeProvider($opt['provider'] ?? null));
			$entity->setCountry(strtolower($opt['country'] ?? $country));
			$entity->setCountryCode($opt['countryCode'] ?? null);
			$entity->setPrefix($opt['prefix'] ?? null);
			$entity->setCurrency($opt['currency'] ?? null);
			$entity->setInstructions($opt['instructions'] ?? null);
			$entity->setLogo($opt['logo'] ?? null);

			// store raw payload (string)
			$entity->setRawPayload(
				json_encode($opt, JSON_THROW_ON_ERROR)
			);

			// timestamps
			$entity->setCreatedAt($this->now());
			$entity->setUpdatedAt($this->now());

			$entities[] = $entity;
		}

		$this->mapper->upsertMany($entities);

		// 4. Return fresh DB state (ensures consistency)
		return $this->toArray(
			$this->mapper->findByCountry($country)
		);
	}

	// -----------------------------
	// Helpers
	// -----------------------------

	private function normalizeProvider(?string $provider): string
	{
		return strtolower(trim((string) $provider));
	}

	private function now(): string
	{
		return (new \DateTimeImmutable(
			'now',
			new \DateTimeZone('UTC'),
		))->format(DATE_ATOM);
	}

	/**
	 * Convert entities → API-safe array
	 */
	private function toArray(array $entities): array
	{
		return array_map(function (DpoMobileOption $option) {

			return [
				'provider'     => $option->getProvider(),
				'country'      => $option->getCountry(),
				'countryCode'  => $option->getCountryCode(),
				'prefix'       => $option->getPrefix(),
				'currency'     => $option->getCurrency(),
				'instructions' => $option->getInstructions(),
				'logo'         => $option->getLogo(),
			];
		}, $entities);
	}
}
