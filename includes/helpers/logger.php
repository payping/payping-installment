<?php
namespace PayPingInstallment\Helpers;

class Logger {
    public static function log($message) {
        error_log('[PayPing Instalment]: ' . $message);
    }
}
