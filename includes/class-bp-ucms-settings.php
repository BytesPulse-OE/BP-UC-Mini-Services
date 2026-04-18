<?php
if (!defined('ABSPATH')) {
    exit;
}

final class BP_UCMS_Settings {
    public const OPTION_KEY = 'bp_ucms_settings';
    public const MENU_SLUG = 'bp-uc-mini-services';
    public const NONCE_ACTION = 'bp_ucms_save_settings';
    public const EXPORT_ACTION = 'bp_ucms_export_settings';
    public const IMPORT_ACTION = 'bp_ucms_import_settings';

    public function defaults(): array {
        return [
            'uc_enabled' => 0,
            'uc_allow_logged_in' => 1,
            'uc_title_el' => 'Κάτι μεγάλο ετοιμάζεται…',
            'uc_subtitle_el' => 'Το νέο μας website είναι υπό κατασκευή. Επιστρέφουμε σύντομα με κάτι πραγματικά δυνατό.',
            'uc_contact_el' => 'Για οποιαδήποτε πληροφορία ή συνεργασία, μπορείτε να επικοινωνήσετε μαζί μας στο',
            'uc_title_en' => 'Something big is coming…',
            'uc_subtitle_en' => 'Our new website is under construction. We’ll be back soon with something powerful.',
            'uc_contact_en' => 'For any inquiries or collaborations, feel free to reach us at',
            'contact_email' => get_option('admin_email'),
            'footer_text' => '© 2023 - 2026 BytesPulse — Innovative and tailored IT solutions',
            'logo_url' => '',
            'favicon_url' => '',
            'countdown_enabled' => 0,
            'countdown_start' => '',
            'countdown_end' => '',
            'custom_login_slug' => 'bp-login',
            'block_wp_login' => 1,
            'blocked_login_redirect_page_id' => 0,
            'redirect_administrator_mode' => '',
            'redirect_administrator_page_id' => 0,
            'redirect_editor_mode' => '',
            'redirect_editor_page_id' => 0,
            'redirect_author_mode' => '',
            'redirect_author_page_id' => 0,
            'redirect_contributor_mode' => '',
            'redirect_contributor_page_id' => 0,
            'redirect_subscriber_mode' => '',
            'redirect_subscriber_page_id' => 0,
            'redirect_customer_mode' => '',
            'redirect_customer_page_id' => 0,
            'redirect_client_mode' => '',
            'redirect_client_page_id' => 0,
            'logout_redirect_page_id' => 0,
            'smtp_enabled' => 0,
            'smtp_host' => '',
            'smtp_port' => '587',
            'smtp_encryption' => 'tls',
            'smtp_autotls' => 1,
            'smtp_auth' => 1,
            'smtp_username' => '',
            'smtp_password' => '',
            'mail_from_email' => get_option('admin_email'),
            'mail_from_name' => get_bloginfo('name'),
            'force_mail_from_email' => 1,
            'force_mail_from_name' => 1,
            'smtp_html_emails' => 0,
            'smtp_error_log_enabled' => 0,
            'twofa_enabled' => 1,
            'twofa_issuer' => get_bloginfo('name'),
            'rewrite_slug_flushed' => 'bp-login',
        ];
    }

    public function ensure_defaults(): void {
        if (!get_option(self::OPTION_KEY)) {
            add_option(self::OPTION_KEY, $this->defaults());
        }
    }

    public function get(): array {
        return wp_parse_args(get_option(self::OPTION_KEY, []), $this->defaults());
    }

    public function update(array $settings): void {
        update_option(self::OPTION_KEY, $settings);
    }

    public function delete(): void {
        delete_option(self::OPTION_KEY);
    }

    public function sanitize_login_slug(string $slug): string {
        $slug = sanitize_title($slug);
        return $slug ?: 'bp-login';
    }

    public function supported_roles(): array {
        return [
            'administrator' => __('Administrator', 'bp-uc-mini-services'),
            'editor' => __('Editor', 'bp-uc-mini-services'),
            'author' => __('Author', 'bp-uc-mini-services'),
            'contributor' => __('Contributor', 'bp-uc-mini-services'),
            'subscriber' => __('Subscriber', 'bp-uc-mini-services'),
            'customer' => __('Customer', 'bp-uc-mini-services'),
            'client' => __('Client', 'bp-uc-mini-services'),
        ];
    }

    public function exportable_settings(array $settings): array {
        $allowed = array_keys($this->defaults());
        $export = [];

        foreach ($allowed as $key) {
            if (array_key_exists($key, $settings)) {
                $export[$key] = $settings[$key];
            }
        }

        return $export;
    }

    public function import_from_array(array $import): array {
        $defaults = $this->defaults();
        $current = $this->get();
        $merged = wp_parse_args($import, $current);

        $checkbox_fields = [
            'uc_enabled', 'uc_allow_logged_in', 'countdown_enabled', 'block_wp_login',
            'smtp_enabled', 'smtp_autotls', 'smtp_auth', 'force_mail_from_email',
            'force_mail_from_name', 'smtp_html_emails', 'smtp_error_log_enabled',
            'twofa_enabled',
        ];

        foreach ($checkbox_fields as $field) {
            $merged[$field] = empty($merged[$field]) ? 0 : 1;
        }

        $email_fields = ['contact_email', 'mail_from_email'];
        foreach ($email_fields as $field) {
            $merged[$field] = sanitize_email((string) ($merged[$field] ?? $defaults[$field]));
        }

        $url_fields = ['logo_url', 'favicon_url'];
        foreach ($url_fields as $field) {
            $merged[$field] = esc_url_raw((string) ($merged[$field] ?? ''));
        }

        $merged['custom_login_slug'] = $this->sanitize_login_slug((string) ($merged['custom_login_slug'] ?? $defaults['custom_login_slug']));
        $merged['smtp_encryption'] = in_array(($merged['smtp_encryption'] ?? ''), ['none', 'ssl', 'tls'], true) ? $merged['smtp_encryption'] : 'tls';
        $merged['smtp_port'] = preg_replace('/[^0-9]/', '', (string) ($merged['smtp_port'] ?? $defaults['smtp_port']));

        $page_fields = [
            'blocked_login_redirect_page_id', 'redirect_administrator_page_id', 'redirect_editor_page_id',
            'redirect_author_page_id', 'redirect_contributor_page_id', 'redirect_subscriber_page_id',
            'redirect_customer_page_id', 'redirect_client_page_id', 'logout_redirect_page_id',
        ];
        foreach ($page_fields as $field) {
            $merged[$field] = absint($merged[$field] ?? 0);
        }

        $mode_fields = [
            'redirect_administrator_mode', 'redirect_editor_mode', 'redirect_author_mode',
            'redirect_contributor_mode', 'redirect_subscriber_mode', 'redirect_customer_mode',
            'redirect_client_mode',
        ];
        foreach ($mode_fields as $field) {
            $value = (string) ($merged[$field] ?? '');
            $value = $value === '' ? '' : sanitize_key($value);
            $merged[$field] = in_array($value, ['', 'same', 'page', 'admin'], true) ? $value : '';
        }

        $text_fields = [
            'uc_title_el', 'uc_subtitle_el', 'uc_contact_el', 'uc_title_en', 'uc_subtitle_en',
            'uc_contact_en', 'footer_text', 'countdown_start', 'countdown_end', 'smtp_host',
            'smtp_username', 'mail_from_name', 'twofa_issuer', 'rewrite_slug_flushed',
        ];
        foreach ($text_fields as $field) {
            $merged[$field] = sanitize_textarea_field((string) ($merged[$field] ?? $defaults[$field]));
        }

        if (isset($import['smtp_password']) && $import['smtp_password'] !== '') {
            $merged['smtp_password'] = (string) $import['smtp_password'];
        }

        return $this->exportable_settings($merged);
    }

    public function constant_map(): array {
        return [
            'smtp_enabled' => 'BP_UCMS_SMTP_ENABLED',
            'smtp_host' => 'BP_UCMS_SMTP_HOST',
            'smtp_port' => 'BP_UCMS_SMTP_PORT',
            'smtp_encryption' => 'BP_UCMS_SMTP_ENCRYPTION',
            'smtp_autotls' => 'BP_UCMS_SMTP_AUTOTLS',
            'smtp_auth' => 'BP_UCMS_SMTP_AUTH',
            'smtp_username' => 'BP_UCMS_SMTP_USERNAME',
            'smtp_password' => 'BP_UCMS_SMTP_PASSWORD',
            'mail_from_email' => 'BP_UCMS_SMTP_FROM_EMAIL',
            'mail_from_name' => 'BP_UCMS_SMTP_FROM_NAME',
            'force_mail_from_email' => 'BP_UCMS_SMTP_FORCE_FROM_EMAIL',
            'force_mail_from_name' => 'BP_UCMS_SMTP_FORCE_FROM_NAME',
            'smtp_html_emails' => 'BP_UCMS_SMTP_HTML_EMAILS',
            'smtp_error_log_enabled' => 'BP_UCMS_SMTP_ERROR_LOG_ENABLED',
        ];
    }

    public function get_with_runtime_overrides(): array {
        $settings = $this->get();

        foreach ($this->constant_map() as $key => $constant) {
            if (!defined($constant)) {
                continue;
            }

            $value = constant($constant);
            if (in_array($key, ['smtp_enabled', 'smtp_autotls', 'smtp_auth', 'force_mail_from_email', 'force_mail_from_name', 'smtp_html_emails', 'smtp_error_log_enabled'], true)) {
                $settings[$key] = $value ? 1 : 0;
            } elseif ($key === 'smtp_port') {
                $settings[$key] = preg_replace('/[^0-9]/', '', (string) $value);
            } elseif ($key === 'smtp_encryption') {
                $settings[$key] = in_array($value, ['none', 'ssl', 'tls'], true) ? $value : 'tls';
            } else {
                $settings[$key] = (string) $value;
            }
        }

        return $settings;
    }
}
