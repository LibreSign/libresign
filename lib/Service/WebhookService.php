<?php

namespace OCA\Libresign\Service;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;

class WebhookService {
	/** @var IL10N */
	private $l10n;

	public function __construct(
		IL10N $l10n
	) {
		$this->l10n = $l10n;
	}

	public function validate(array $data) {
		$response = $this->validateFile($data);
		if ($response) {
			return $response;
		}
		$response = $this->validateUsers($data);
		if ($response) {
			return $response;
		}
	}

	public function validateFile($data) {
		if (empty($data['file'])) {
			return new DataResponse(
				[
					'message' => (string)$this->l10n->t('Empty file'),
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
		if (empty($data['file']['url']) && empty($data['file']['base64'])) {
			return new DataResponse(
				[
					'message' => (string)$this->l10n->t('Inform url or base64 to sign'),
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
		if (!empty($data['file']['url'])) {
			if (!filter_var($data['file']['url'], FILTER_VALIDATE_URL)) {
				return new DataResponse(
					[
						'message' => (string)$this->l10n->t('Invalid url file'),
					],
					Http::STATUS_UNPROCESSABLE_ENTITY
				);
			}
		}
		if (!empty($data['file']['base64'])) {
			$input = base64_decode($data['file']['base64']);
			$base64 = base64_encode($input);
			if ($input != $base64) {
				return new DataResponse(
					[
						'message' => (string)$this->l10n->t('Invalid base64 file'),
					],
					Http::STATUS_UNPROCESSABLE_ENTITY
				);
			}
		}
	}

	public function validateUsers($data)
	{
		if (empty($data['users'])) {
			return new DataResponse(
				[
					'message' => (string)$this->l10n->t('Empty users collection'),
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
		if (!is_array($data['users'])) {
			return new DataResponse(
				[
					'message' => (string)$this->l10n->t('User collection need is an array'),
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
		foreach ($data['users'] as $index => $user) {
			$response = $this->validateUser($user, $index);
			if ($response) {
				return $response;
			}
		}
	}

	public function validateUser($user, $index) {
		if (!is_array($user)) {
			return new DataResponse(
				[
					'message' => (string)$this->l10n->t('User collection need is an array: user ' . $index),
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
		if (!$user) {
			return new DataResponse(
				[
					'message' => (string)$this->l10n->t('User collection need is an array with values: user ' . $index),
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
		if (empty($user['email'])) {
			return new DataResponse(
				[
					'message' => (string)$this->l10n->t('User need an email: user ' . $index),
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
		if (!filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
			return new DataResponse(
				[
					'message' => (string)$this->l10n->t('Invalid email: user ' . $index),
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
	}
}
