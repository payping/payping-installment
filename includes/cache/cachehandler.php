<?php
namespace PayPingInstallment\Cache;

class CacheHandler {
    public static function get($key) {
        return get_transient($key);
    }

    public static function set($key, $value, $expiration = 3600) {
        set_transient($key, $value, $expiration);
    }

    public static function delete($key) {
        delete_transient($key);
    }
}