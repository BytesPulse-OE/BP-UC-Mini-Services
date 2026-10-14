<?php
if (!defined('ABSPATH')) {
    exit;
}

final class BP_UCMS_SMTP {
    private const CRON_HOOK = 'bp_ucms_cleanup_mail_log';
    private const LOG_RETENTION_DAYS = 30;

    private BP_UCMS_Settings $settings;

    public function __construct(BP_UCMS_Settings $settings) {
        $this->settings = $settings;
    }

    public function init(): void {
        add_action('phpmailer_init', [$this, 'configure_phpmailer']);
        add_filter('wp_mail_from', [$this, 'filter_mail_from']);
        add_filter('wp_mail_from_name', [$this, 'filter_mail_from_name']);
        add_filter('wp_mail_content_type', [$this, 'filter_mail_content_type']);
        add_action('wp_mail_failed', [$this, 'handle_mail_failed']);
        add_action(self::CRON_HOOK, [$this, 'cleanup_log']);
    }

    public static function activate(): void {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    public static function deactivate(): void {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    public function configure_phpmailer(PHPMailer\PHPMailer\PHPMailer $phpmailer): void {
        $settings = $this->settings->get_with_runtime_overrides();
        if (empty($settings['smtp_enabled'])) {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host = (string) ($settings['smtp_host'] ?? '');
        $phpmailer->Port = (int) ($settings['smtp_port'] ?? 587);
        $phpmailer->SMTPAuth = !empty($settings['smtp_auth']);
        $phpmailer->Username = (string) ($settings['smtp_username'] ?? '');
        $phpmailer->Password = (string) ($settings['smtp_password'] ?? '');
        $phpmailer->SMTPAutoTLS = !empty($settings['smtp_autotls']);
        $phpmailer->Timeout = 20;

        $encryption = (string) ($settings['smtp_encryption'] ?? '');
        if ($encryption === 'ssl' || $encryption === 'tls') {
            $phpmailer->SMTPSecure = $encryption;
        } else {
            $phpmailer->SMTPSecure = '';
        }

        if (!empty($settings['mail_from_email']) && is_email($settings['mail_from_email'])) {
            $phpmailer->setFrom(
                $settings['mail_from_email'],
                (string) ($settings['mail_from_name'] ?? ''),
                false
            );
        }
    }

    public function filter_mail_from(string $from_email): string {
        $settings = $this->settings->get_with_runtime_overrides();
        if (!empty($settings['mail_from_email']) && !empty($settings['force_mail_from_email']) && is_email($settings['mail_from_email'])) {
            return $settings['mail_from_email'];
        }

        return $from_email;
    }

    public function filter_mail_from_name(string $from_name): string {
        $settings = $this->settings->get_with_runtime_overrides();
        if (!empty($settings['mail_from_name']) && !empty($settings['force_mail_from_name'])) {
            return $settings['mail_from_name'];
        }

        return $from_name;
    }

    public function filter_mail_content_type(string $content_type): string {
        $settings = $this->settings->get_with_runtime_overrides();
        if (!empty($settings['smtp_html_emails'])) {
            return 'text/html';
        }

        return $content_type;
    }

    public function handle_mail_failed(WP_Error $error): void {
        $settings = $this->settings->get_with_runtime_overrides();
        if (empty($settings['smtp_error_log_enabled'])) {
            return;
        }

        $data = $error->get_error_data();
        $message = $error->get_error_message();

        $context = [
            'error' => $message,
        ];

        if (is_array($data)) {
            $context['to'] = $data['to'] ?? '';
            $context['subject'] = $data['subject'] ?? '';
        }

        $this->append_log($context);
    }

    public function send_test_email(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to do this.', 'bp-uc-mini-services'));
        }

        check_admin_referer('bp_ucms_send_test_email');

        $redirect_url = add_query_arg(['page' => BP_UCMS_Settings::MENU_SLUG], admin_url('admin.php'));
        $email = isset($_POST['test_email_to']) ? sanitize_email(wp_unslash($_POST['test_email_to'])) : '';

        if (!is_email($email)) {
            wp_safe_redirect(add_query_arg('smtp_test', 'invalid_email', $redirect_url));
            exit;
        }

        $subject = sprintf('[%s] SMTP Test Email', wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES));
        $body = 'This is a test email sent from BP UC & Mini Services SMTP module.';
        $sent = wp_mail($email, $subject, $body);

        wp_safe_redirect(add_query_arg('smtp_test', $sent ? 'success' : 'failed', $redirect_url));
        exit;
    }

    public function cleanup_log(): void {
        $path = $this->get_log_file_path();
        if (!file_exists($path)) {
            return;
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        $cutoff = time() - (DAY_IN_SECONDS * self::LOG_RETENTION_DAYS);
        $kept = [];

        foreach ($lines as $line) {
            $parts = explode("\t", $line, 3);
            $timestamp = isset($parts[0]) ? (int) $parts[0] : 0;
            if ($timestamp >= $cutoff) {
                $kept[] = $line;
            }
        }

        if (empty($kept)) {
            @unlink($path);
            return;
        }

        @file_put_contents($path, implode(PHP_EOL, $kept) . PHP_EOL, LOCK_EX);
    }

    public function get_recent_log_lines(int $limit = 100): array {
        $path = $this->get_log_file_path();
        if (!file_exists($path)) {
            return [];
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        $lines = array_slice($lines, -1 * absint($limit));
        $output = [];

        foreach ($lines as $line) {
            $parts = explode("\t", $line, 3);
            if (count($parts) === 3) {
                $output[] = $parts[1] . ' ' . $parts[2];
            } else {
                $output[] = $line;
            }
        }

        return $output;
    }

    public function get_log_file_path(): string {
        $uploads = wp_upload_dir();
        $dir = trailingslashit($uploads['basedir']) . 'bp-uc-mini-services/logs';
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }

        return trailingslashit($dir) . 'mail-errors.log';
    }

    public function delete_log_files(): void {
        $uploads = wp_upload_dir();
        $dir = trailingslashit($uploads['basedir']) . 'bp-uc-mini-services/logs';
        $file = trailingslashit($dir) . 'mail-errors.log';

        if (file_exists($file)) {
            @unlink($file);
        }
        if (is_dir($dir)) {
            @rmdir($dir);
        }
        $parent_dir = dirname($dir);
        if (is_dir($parent_dir) && basename($parent_dir) === 'bp-uc-mini-services') {
            @rmdir($parent_dir);
        }
    }

    private function append_log(array $context): void {
        $path = $this->get_log_file_path();
        $timestamp = time();
        $date = '[' . gmdate('Y-m-d H:i:s', $timestamp) . ' UTC]';
        $payload = wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $line = $timestamp . "\t" . $date . "\t" . $payload . PHP_EOL;
        @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
        $this->cleanup_log();
    }
}
