<?php
namespace PayPingInstallment\Compatibility;

class CompatibilityManager {
    private static $active_plugins = [];

    public static function init() {
        self::$active_plugins = apply_filters('active_plugins', get_option('active_plugins'));
    }

    public static function is_plugin_active($plugin_basename) {
        return in_array($plugin_basename, self::$active_plugins);
    }
}