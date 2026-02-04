<?php
if (!defined('ABSPATH')) exit;

class HAM_Settings {
    public static function get_setting($key, $default = '') {
        return get_option($key, $default);
    }
    
    public static function update_setting($key, $value) {
        return update_option($key, $value);
    }
}

new HAM_Settings();
