<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Payment\Interfaces;

interface IVerifiableProvider extends IProvider
{
	public function verifyStatus(string $reference): string;

	public function query(string $reference): array;
}
