<?php

declare(strict_types=1);

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class DpoMobileOptionMapper extends QBMapper
{
	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, 'gopaperless_dpo_mobile_options', DpoMobileOption::class);
	}

	/**
	 * Fetch all options for a given country
	 *
	 * @return DpoMobileOption[]
	 */
	public function findByCountry(string $country): array
	{
		$qb = $this->db->getQueryBuilder();

		$qb->select('o.*')
			->from($this->getTableName(), 'o')
			->where(
				$qb->expr()->eq(
					'o.country',
					$qb->createNamedParameter(strtolower($country))
				)
			);

		return $this->findEntities($qb);
	}

	/**
	 * Fetch a specific option by unique key (provider + country + prefix)
	 */
	public function findByUniqueKey(
		string $country,
		?string $prefix
	): ?DpoMobileOption {
		$qb = $this->db->getQueryBuilder();

		$qb->select('o.*')
			->from($this->getTableName(), 'o')
			->where(
				$qb->expr()->eq(
					'o.country',
					$qb->createNamedParameter(strtolower($country))
				)
			)
			->andWhere(
				$qb->expr()->eq(
					'o.prefix',
					$qb->createNamedParameter($prefix)
				)
			)
			->setMaxResults(1);

		return $this->findEntity($qb);
	}

	/**
	 * Upsert options using DB unique constraint
	 *
	 * RULES:
	 * - Try insert
	 * - On duplicate → update existing row
	 *
	 * @param DpoMobileOption[] $options
	 */
	public function upsertMany(array $options): void
	{
		foreach ($options as $option) {

			try {
				$this->insert($option);
				continue;
			} catch (\Throwable $e) {

				// Likely unique constraint → resolve existing row
				$existing = $this->findByUniqueKey(
					$option->getCountry(),
					$option->getPrefix()
				);

				if ($existing === null) {
					// Not a duplicate → rethrow
					throw $e;
				}

				// update label (provider can change)
				$existing->setProvider($option->getProvider());
				$existing->setCurrency($option->getCurrency());
				$existing->setInstructions($option->getInstructions());
				$existing->setLogo($option->getLogo());
				$existing->setRawPayload($option->getRawPayload());

				$existing->setUpdatedAt(
					(new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
						->format('Y-m-d H:i:s')
				);

				$this->update($existing);
				continue;
			}
		}
	}
}
