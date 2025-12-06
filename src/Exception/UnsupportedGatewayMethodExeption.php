<?php

namespace Emmy\Ego\Exception;

use Exception;

/**
 * Methods that are not supported by a Payment Gateway e.g verifyAccountNumber, getBanks etc.
 */
class UnsupportedGatewayMethodException extends Exception {}
