<?php
/**
 * Plugin Name: BP UC & Mini Services
 * Plugin URI: https://bytespulse.com/
 * Description: Under construction mode, custom login URL hardening, redirects, login branding, lightweight custom SMTP, and per-user TOTP 2FA support.
 * Version: 1.4.11
 * Author: BytesPulse
 * Author URI: https://bytespulse.com/
 * License: GPLv2 or later
 * Text Domain: bp-uc-mini-services
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('BP_UCMS_VERSION')) {
    define('BP_UCMS_VERSION', '1.4.11');
}
if (!defined('BP_UCMS_FILE')) {
    define('BP_UCMS_FILE', __FILE__);
}
if (!defined('BP_UCMS_PATH')) {
    define('BP_UCMS_PATH', plugin_dir_path(__FILE__));
}
if (!defined('BP_UCMS_URL')) {
    define('BP_UCMS_URL', plugin_dir_url(__FILE__));
}

require_once BP_UCMS_PATH . 'includes/class-bp-ucms-plugin.php';

if (!function_exists('bp_ucms')) {
    function bp_ucms(): BP_UCMS_Plugin {
        return BP_UCMS_Plugin::instance();
    }
}

register_activation_hook(__FILE__, ['BP_UCMS_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['BP_UCMS_Plugin', 'deactivate']);

bp_ucms();
