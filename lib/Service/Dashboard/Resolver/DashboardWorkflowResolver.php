<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Dashboard\Resolver;

use OCA\Libresign\Enum\DashboardWorkflowAction;
use OCA\Libresign\Enum\DashboardWorkflowStatus;
use OCA\Libresign\Enum\PaymentStatus;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Service\Dashboard\ValueObject\DashboardWorkflowContext;

final class DashboardWorkflowResolver
{

	public function resolveStatus(
		DashboardWorkflowContext $context,
	): DashboardWorkflowStatus {

		if ($this->requiresPayment($context)) {
			return DashboardWorkflowStatus::PAYMENT_REQUIRED;
		}

		if (
			$context->participantContext->isSigner
			&& $context->participantContext->canSignNow
		) {
			return DashboardWorkflowStatus::ACTION_REQUIRED;
		}

		if ($this->isWorkflowCompleted($context)) {
			return DashboardWorkflowStatus::COMPLETED;
		}

		if ($context->isDraft) {
			return DashboardWorkflowStatus::DRAFT;
		}

		return DashboardWorkflowStatus::WAITING_FOR_OTHERS;
	}

	public function resolvePrimaryAction(
		DashboardWorkflowContext $context,
	): DashboardWorkflowAction {

		if ($this->requiresPayment($context)) {
			return DashboardWorkflowAction::COMPLETE_PAYMENT;
		}

		if (
			$context->participantContext->isSigner
			&& $context->participantContext->canSignNow
		) {
			return DashboardWorkflowAction::SIGN;
		}

		if (
			$this->isWorkflowCompleted($context)
			|| $context->participantContext->hasSigned
		) {
			return DashboardWorkflowAction::VIEW;
		}

		if ($context->isDraft) {
			return DashboardWorkflowAction::NONE;
		}

		return DashboardWorkflowAction::WAIT;
	}

	public function canUserAct(
		DashboardWorkflowContext $context,
	): bool {
		return in_array(
			$this->resolvePrimaryAction($context),
			[
				DashboardWorkflowAction::SIGN,
				DashboardWorkflowAction::COMPLETE_PAYMENT,
			],
			true
		);
	}

	public function resolveStatusLabel(
		DashboardWorkflowContext $context,
	): string {

		return match ($this->resolveStatus($context)) {

			DashboardWorkflowStatus::ACTION_REQUIRED =>
			'Waiting for your signature',

			DashboardWorkflowStatus::WAITING_FOR_OTHERS =>
			'Waiting for other participants',

			DashboardWorkflowStatus::PAYMENT_REQUIRED =>
			'Payment required before signing',

			DashboardWorkflowStatus::COMPLETED =>
			'Completed',

			DashboardWorkflowStatus::DRAFT =>
			'Draft',
		};
	}

	private function requiresPayment(
		DashboardWorkflowContext $context,
	): bool {

		$payment = $context->payment;

		if (!$payment) {
			return false;
		}

		return $payment->getPaymentStatus() !== PaymentStatus::PAID;
	}


	private function isWorkflowCompleted(
		DashboardWorkflowContext $context,
	): bool {

		foreach ($context->signRequests as $signRequest) {

			if (
				$signRequest->getStatusEnum()
				!== SignRequestStatus::SIGNED
			) {
				return false;
			}
		}

		return !$this->requiresPayment($context);
	}
}
