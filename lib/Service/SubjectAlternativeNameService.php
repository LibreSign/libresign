<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCP\IUserManager;

/**
 * Service to handle Subject Alternative Name (SAN) according to RFC 5280 Section 4.2.1.6
 */
class SubjectAlternativeNameService {
	public function __construct(
		private IUserManager $userManager,
	) {
	}

	public function parseFromCertificate(array $certificateData): ?array {
		if (empty($certificateData['extensions']['subjectAltName'])) {
			return null;
		}

		$subjectAltName = $certificateData['extensions']['subjectAltName'];
		if (is_array($subjectAltName)) {
			$subjectAltName = $subjectAltName[0];
		}

		return $this->parse((string)$subjectAltName);
	}

	protected function parse(string $subjectAltName): ?array {
		$pattern = '/^(?<method>' . implode('|', IdentifyMethodService::IDENTIFY_METHODS) . '):(?<value>.+)$/';

		if (preg_match($pattern, $subjectAltName, $matches)) {
			return [
				'method' => $matches['method'],
				'value' => $matches['value'],
			];
		}

		if (filter_var($subjectAltName, FILTER_VALIDATE_EMAIL)) {
			return [
				'method' => IdentifyMethodService::IDENTIFY_EMAIL,
				'value' => $subjectAltName,
			];
		}

		return null;
	}

	public function build(string $method, string $value): string {
		return $method . ':' . $value;
	}

	public function buildForHosts(array $hosts): string {
		$altNames = [];
		foreach ($hosts as $host) {
			if (filter_var($host, FILTER_VALIDATE_EMAIL)) {
				$altNames[] = $this->build(IdentifyMethodService::IDENTIFY_EMAIL, $host);
			}
		}
		return implode(', ', $altNames);
	}

	public function resolveUid(array $certificateData, string $host): ?string {
		if (!empty($certificateData['subject']['CN'])) {
			$cn = $certificateData['subject']['CN'];
			if (is_array($cn)) {
				$cn = $cn[0];
			}
			$pattern = '/^(?<method>' . implode('|', IdentifyMethodService::IDENTIFY_METHODS) . '):(?<value>.*), /';
			if (preg_match($pattern, (string)$cn, $matches)) {
				return $matches['method'] . ':' . $matches['value'];
			}
		}

		$parsed = $this->parseFromCertificate($certificateData);
		if (!$parsed) {
			return null;
		}

		$method = $parsed['method'];
		$value = $parsed['value'];

		if (in_array($method, [IdentifyMethodService::IDENTIFY_EMAIL, IdentifyMethodService::IDENTIFY_ACCOUNT], true)) {
			if (str_ends_with($value, $host)) {
				$uid = str_replace('@' . $host, '', $value);
				$user = $this->userManager->get($uid);
				if ($user) {
					return IdentifyMethodService::IDENTIFY_ACCOUNT . ':' . $uid;
				}

				$users = $this->userManager->getByEmail($value);
				if (!empty($users)) {
					$user = current($users);
					return IdentifyMethodService::IDENTIFY_ACCOUNT . ':' . $user->getUID();
				}

				return IdentifyMethodService::IDENTIFY_EMAIL . ':' . $value;
			}

			$users = $this->userManager->getByEmail($value);
			if (!empty($users)) {
				$user = current($users);
				return IdentifyMethodService::IDENTIFY_ACCOUNT . ':' . $user->getUID();
			}

			$user = $this->userManager->get($value);
			if ($user) {
				return IdentifyMethodService::IDENTIFY_ACCOUNT . ':' . $user->getUID();
			}
		}

		return $this->build($method, $value);
	}

	private function extractEmail(string $subjectAltName): ?string {
		if (preg_match('/(?:email:)+(?<email>[^\s,]+)/', $subjectAltName, $matches)) {
			if (filter_var($matches['email'], FILTER_VALIDATE_EMAIL)) {
				return $matches['email'];
			}
		}

		if (filter_var($subjectAltName, FILTER_VALIDATE_EMAIL)) {
			return $subjectAltName;
		}

		return null;
	}

	public function extractEmailFromCertificate(array $certificateData): ?string {
		if (empty($certificateData['extensions']['subjectAltName'])) {
			return null;
		}

		$subjectAltName = $certificateData['extensions']['subjectAltName'];
		if (is_array($subjectAltName)) {
			$subjectAltName = $subjectAltName[0];
		}

		return $this->extractEmail((string)$subjectAltName);
	}
}
