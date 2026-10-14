<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (defined('BP_UCMS_PRESERVE_DATA') && BP_UCMS_PRESERVE_DATA) {
    return;
}

delete_option('bp_ucms_settings');

wp_clear_scheduled_hook('bp_ucms_cleanup_mail_log');

$uploads = wp_upload_dir();
$base_dir = trailingslashit($uploads['basedir']) . 'bp-uc-mini-services';
$log_dir = trailingslashit($base_dir) . 'logs';
$log_file = trailingslashit($log_dir) . 'mail-errors.log';

if (file_exists($log_file)) {
    @unlink($log_file);
}
if (is_dir($log_dir)) {
    @rmdir($log_dir);
}
if (is_dir($base_dir)) {
    @rmdir($base_dir);
}

if (file_exists(__DIR__ . '/includes/class-bp-ucms-2fa.php')) {
    require_once __DIR__ . '/includes/class-bp-ucms-2fa.php';
    if (class_exists('BP_UCMS_Two_FA')) {
        BP_UCMS_Two_FA::delete_all_user_meta();
    }
}
