<?php

namespace gateway\enums;

abstract class TransactionKind
{
	// Subscriptions
    const SUBSCRIPTION_STARTED = 'subscriptionStarted';
    const SUBSCRIPTION_UPDATED = 'subscriptionUpdated';
    const SUBSCRIPTION_CANCELLED = 'subscriptionCancelled';

    // Payments
    const PAYMENT_RECEIVED = 'paymentReceived';
	const PAYMENT_REFUNDED = 'paymentRefunded';
	const PAYOFF_MADE = 'payoffMade';

	// Forecast invoices
    const INVOICE_CREATED = 'invoiceCreated';
    const INVOICE_UPDATED = 'invoiceUpdated';
    const INVOICE_DELETED = 'invoiceDeleted';

    // Effective (frozen) invoices
	const INVOICE_PAID = 'invoicePaid';
	const INVOICE_FAILED = 'invoiceFailed';

	// Log everything
	const GENERIC_EVENT = 'generic';

	// TODO: Use better enum
	static function getKeys() {
		$oClass = new \ReflectionClass(__CLASS__);
		return $oClass->getConstants();
	}
}