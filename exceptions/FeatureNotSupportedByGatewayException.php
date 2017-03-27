<?php

namespace gateway\exceptions;

/**
 * Raise on impossible orders:
 * Order trialDays > 0 and gateway does not support trials
 * Order recurringAmount > 0 and gateway does not support recurring
 */
class FeatureNotSupportedByGatewayException extends GatewayException {

}