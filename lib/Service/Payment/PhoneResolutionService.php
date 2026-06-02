<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Payment;

use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberToCarrierMapper;
use OCA\Libresign\Service\Payment\DTO\PaymentPhoneResolutionDTO;
use Psr\Log\LoggerInterface;

class PhoneResolutionService
{
	private PhoneNumberUtil $phoneUtil;
	private PhoneNumberToCarrierMapper $carrierMapper;
	private LoggerInterface $logger;

	public function __construct(LoggerInterface $logger)
	{
		$this->phoneUtil = PhoneNumberUtil::getInstance();
		$this->carrierMapper = PhoneNumberToCarrierMapper::getInstance();
		$this->logger = $logger;
	}

	/**
	 * Resolve phone into structured, normalized data.
	 *
	 * Responsibilities:
	 * - Validate phone number
	 * - Normalise to E.164 format
	 * - Extract region (ISO code)
	 * - Attempt carrier detection (best effort only)
	 *
	 * IMPORTANT:
	 * - This is the ONLY place libphonenumber is used
	 * - Carrier is NOT guaranteed (number portability)
	 * - Does NOT throw for invalid input
	 */
	public function resolve(string $rawPhone): PaymentPhoneResolutionDTO
	{
		$rawPhone = trim($rawPhone);

		if ($rawPhone === '') {
			return $this->invalid();
		}

		try {
			/**
			 * Enforce international format
			 */
			if (!str_starts_with($rawPhone, '+')) {
				return $this->invalid();
			}

			$parsed = $this->phoneUtil->parse($rawPhone, null);

			if (!$this->phoneUtil->isValidNumber($parsed)) {
				return $this->invalid();
			}

			/**
			 * Formats
			 */
			$e164 = $this->phoneUtil->format($parsed, PhoneNumberFormat::E164); // +254...
			$nationalNumber = (string) $parsed->getNationalNumber();           // 712345678

			/**
			 * Region (ISO)
			 */
			$region = $this->phoneUtil->getRegionCodeForNumber($parsed);

			/**
			 * Country Calling Code (ISO)
			 */
			$countryCallingCode = (string) $parsed->getCountryCode();

			/**
			 * Carrier (best effort)
			 */
			$carrier = $this->carrierMapper->getNameForNumber($parsed, 'en');
			$carrier = $carrier !== '' ? $carrier : null;

			return new PaymentPhoneResolutionDTO(
				valid: true,
				e164: $e164,
				e164Digits: preg_replace('/^\+/', '', $e164),
				national: $nationalNumber,
				region: $region,
				carrierHint: $carrier,
				countryCallingCode: $countryCallingCode
			);
		} catch (NumberParseException $e) {

			$this->logger->warning('[PhoneResolution] Parse failed', [
				'input' => $rawPhone,
				'error' => $e->getMessage(),
			]);

			return $this->invalid();
		}
	}

	private function invalid(): PaymentPhoneResolutionDTO
	{
		return new PaymentPhoneResolutionDTO(
			valid: false,
			e164: null,
			e164Digits: null,
			national: null,
			region: null,
			carrierHint: null,
			countryCallingCode: null
		);
	}
}
