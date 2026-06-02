<?php

declare(strict_types=1);

namespace OCA\Libresign\BackgroundJob;

use OCA\Libresign\Service\Payment\PaymentService;
use OCA\Libresign\Db\PaymentMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class VerifyPayments extends TimedJob
{
	public function __construct(
		ITimeFactory $time,
		private PaymentMapper $paymentMapper,
		private PaymentService $paymentService,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);

		// Run every 15 seconds (tune later)
		$this->setInterval(15);
		$this->setTimeSensitivity(IJob::TIME_SENSITIVE);
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function run($argument): void
	{
		// small batch → controlled load
		$payments = $this->paymentMapper->findPendingForVerification(100);

		foreach ($payments as $payment) {

			try {

				// safety guard (should already be filtered but double-safe)
				if (!$payment->isReadyForVerification()) {
					continue;
				}

				// lock
				$payment->lockVerification();
				$this->paymentMapper->update($payment);

				// delegate actual verification
				$this->paymentService->syncPaymentStatus($payment);

			} catch (\Throwable $e) {

				$this->logger->error('[VerifyPayments] failed', [
					'paymentId' => $payment->getId(),
					'provider' => $payment->getProvider(),
					'reference' => $payment->getProviderReference(),
					'error' => $e->getMessage(),
					'exception' => get_class($e),
				]);

				// fallback safety unlock (avoid deadlocks)
				try {
					$payment->unlockVerification();
					$this->paymentMapper->update($payment);
				} catch (\Throwable) {
					// swallow — last resort
				}
			}
		}
	}
}
