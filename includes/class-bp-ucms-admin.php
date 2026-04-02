<?php
if (!defined('ABSPATH')) {
    exit;
}

final class BP_UCMS_Admin {
    private BP_UCMS_Settings $settings;

    public function __construct(BP_UCMS_Settings $settings) {
        $this->settings = $settings;
    }

    public function init(): void {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_post_bp_ucms_save_settings', [$this, 'save_settings']);
        add_action('admin_post_bp_ucms_send_test_email', [bp_ucms()->smtp, 'send_test_email']);
        add_action('admin_post_bp_ucms_export_settings', [$this, 'export_settings']);
        add_action('admin_post_bp_ucms_import_settings', [$this, 'import_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_init', [$this, 'maybe_flush_rewrite_after_slug_change']);
    }

    public function register_menu(): void {
        add_menu_page(
            __('BP UC & Mini Services', 'bp-uc-mini-services'),
            __('BP UC & Mini Services', 'bp-uc-mini-services'),
            'manage_options',
            BP_UCMS_Settings::MENU_SLUG,
            [$this, 'render_page'],
            'dashicons-shield-alt',
            58
        );
    }

    public function enqueue_assets(string $hook): void {
        if ($hook !== 'toplevel_page_' . BP_UCMS_Settings::MENU_SLUG) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('bp-ucms-admin', BP_UCMS_URL . 'assets/css/admin.css', [], BP_UCMS_VERSION);
        wp_enqueue_script('bp-ucms-admin', BP_UCMS_URL . 'assets/js/admin.js', ['jquery'], BP_UCMS_VERSION, true);
    }

    public function maybe_flush_rewrite_after_slug_change(): void {
        $settings = $this->settings->get();
        $slug = $this->settings->sanitize_login_slug($settings['custom_login_slug'] ?? 'bp-login');

        if (($settings['rewrite_slug_flushed'] ?? '') !== $slug) {
            $settings['rewrite_slug_flushed'] = $slug;
            $this->settings->update($settings);
            bp_ucms()->login->register_login_rewrite();
            flush_rewrite_rules(false);
        }
    }

    public function save_settings(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to do this.', 'bp-uc-mini-services'));
        }

        check_admin_referer(BP_UCMS_Settings::NONCE_ACTION);

        $defaults = $this->settings->defaults();
        $existing = $this->settings->get();
        $settings = $existing;

        $checkbox_fields = [
            'uc_enabled', 'uc_allow_logged_in', 'countdown_enabled', 'block_wp_login',
            'smtp_enabled', 'smtp_autotls', 'smtp_auth', 'force_mail_from_email',
            'force_mail_from_name', 'smtp_html_emails', 'smtp_error_log_enabled',
            'twofa_enabled',
        ];

        foreach ($checkbox_fields as $field) {
            $settings[$field] = isset($_POST[$field]) ? 1 : 0;
        }

        $text_fields = [
            'uc_title_el', 'uc_subtitle_el', 'uc_contact_el', 'uc_title_en', 'uc_subtitle_en',
            'uc_contact_en', 'footer_text', 'contact_email', 'logo_url', 'favicon_url',
            'countdown_start', 'countdown_end', 'custom_login_slug', 'smtp_host', 'smtp_port',
            'smtp_encryption', 'smtp_username', 'mail_from_email', 'mail_from_name', 'twofa_issuer',
        ];

        foreach ($text_fields as $field) {
            $value = isset($_POST[$field]) ? wp_unslash($_POST[$field]) : $defaults[$field];

            if (in_array($field, ['logo_url', 'favicon_url'], true)) {
                $settings[$field] = esc_url_raw(trim((string) $value));
            } elseif (in_array($field, ['contact_email', 'mail_from_email'], true)) {
                $settings[$field] = sanitize_email((string) $value);
            } elseif ($field === 'custom_login_slug') {
                $settings[$field] = $this->settings->sanitize_login_slug((string) $value);
            } elseif ($field === 'smtp_encryption') {
                $settings[$field] = in_array($value, ['none', 'ssl', 'tls'], true) ? $value : 'tls';
            } elseif ($field === 'smtp_port') {
                $settings[$field] = preg_replace('/[^0-9]/', '', (string) $value);
            } else {
                $settings[$field] = sanitize_textarea_field((string) $value);
            }
        }

        if (isset($_POST['smtp_password'])) {
            $smtp_password = (string) wp_unslash($_POST['smtp_password']);
            if ($smtp_password !== '') {
                $settings['smtp_password'] = $smtp_password;
            }
        }

        $page_fields = [
            'blocked_login_redirect_page_id', 'redirect_administrator_page_id', 'redirect_editor_page_id',
            'redirect_author_page_id', 'redirect_contributor_page_id', 'redirect_subscriber_page_id',
            'redirect_customer_page_id', 'redirect_client_page_id', 'logout_redirect_page_id',
        ];

        foreach ($page_fields as $field) {
            $settings[$field] = isset($_POST[$field]) ? absint($_POST[$field]) : 0;
        }

        $mode_fields = [
            'redirect_administrator_mode', 'redirect_editor_mode', 'redirect_author_mode',
            'redirect_contributor_mode', 'redirect_subscriber_mode', 'redirect_customer_mode',
            'redirect_client_mode',
        ];

        foreach ($mode_fields as $field) {
            $value = isset($_POST[$field]) ? sanitize_key(wp_unslash($_POST[$field])) : 'same';
            $settings[$field] = in_array($value, ['same', 'page', 'admin'], true) ? $value : 'same';
        }

        $this->settings->update($this->settings->import_from_array($settings));
        bp_ucms()->login->register_login_rewrite();
        flush_rewrite_rules(false);

        wp_safe_redirect(add_query_arg([
            'page' => BP_UCMS_Settings::MENU_SLUG,
            'updated' => '1',
        ], admin_url('admin.php')));
        exit;
    }

    public function export_settings(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to do this.', 'bp-uc-mini-services'));
        }

        check_admin_referer(BP_UCMS_Settings::EXPORT_ACTION);

        $payload = [
            'plugin' => 'BP UC & Mini Services',
            'version' => BP_UCMS_VERSION,
            'exported_at_gmt' => gmdate('c'),
            'settings' => $this->settings->exportable_settings($this->settings->get()),
        ];

        nocache_headers();
        header('Content-Type: application/json; charset=' . get_option('blog_charset'));
        header('Content-Disposition: attachment; filename=bp-uc-mini-services-settings-' . gmdate('Ymd-His') . '.json');
        echo wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function import_settings(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to do this.', 'bp-uc-mini-services'));
        }

        check_admin_referer(BP_UCMS_Settings::IMPORT_ACTION);

        $redirect_url = add_query_arg(['page' => BP_UCMS_Settings::MENU_SLUG], admin_url('admin.php'));

        if (empty($_FILES['bp_ucms_import_file']['tmp_name'])) {
            wp_safe_redirect(add_query_arg('import', 'missing', $redirect_url));
            exit;
        }

        $file = $_FILES['bp_ucms_import_file'];
        if (!empty($file['error'])) {
            wp_safe_redirect(add_query_arg('import', 'upload_error', $redirect_url));
            exit;
        }

        $contents = file_get_contents($file['tmp_name']);
        $decoded = json_decode((string) $contents, true);

        if (!is_array($decoded) || empty($decoded['settings']) || !is_array($decoded['settings'])) {
            wp_safe_redirect(add_query_arg('import', 'invalid', $redirect_url));
            exit;
        }

        $imported_settings = $this->settings->import_from_array($decoded['settings']);
        $this->settings->update($imported_settings);
        bp_ucms()->login->register_login_rewrite();
        flush_rewrite_rules(false);

        wp_safe_redirect(add_query_arg('import', 'success', $redirect_url));
        exit;
    }

    public function render_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $saved_settings = $this->settings->get();
        $runtime_settings = $this->settings->get_with_runtime_overrides();
        $pages = get_pages(['sort_column' => 'post_title', 'sort_order' => 'asc']);
        $custom_login_url = home_url('/' . $this->settings->sanitize_login_slug($saved_settings['custom_login_slug']) . '/');
        $constant_map = $this->settings->constant_map();
        ?>
        <div class="wrap bp-ucms-wrap">
            <h1>BP UC &amp; Mini Services</h1>

            <?php $this->render_notices(); ?>

            <div class="bp-ucms-card">
                <p>
                    <strong><?php esc_html_e('Current custom login URL:', 'bp-uc-mini-services'); ?></strong>
                    <a href="<?php echo esc_url($custom_login_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($custom_login_url); ?></a>
                </p>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field(BP_UCMS_Settings::NONCE_ACTION); ?>
                <input type="hidden" name="action" value="bp_ucms_save_settings">

                <div class="bp-ucms-card">
                    <h2><?php esc_html_e('Under Construction', 'bp-uc-mini-services'); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr><th scope="row"><?php esc_html_e('Enable under construction page', 'bp-uc-mini-services'); ?></th><td><label><input type="checkbox" name="uc_enabled" value="1" <?php checked((int) $saved_settings['uc_enabled'], 1); ?>> <?php esc_html_e('Show the under construction page to visitors.', 'bp-uc-mini-services'); ?></label></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Allow logged-in users', 'bp-uc-mini-services'); ?></th><td><label><input type="checkbox" name="uc_allow_logged_in" value="1" <?php checked((int) $saved_settings['uc_allow_logged_in'], 1); ?>> <?php esc_html_e('Logged-in users can browse the normal website.', 'bp-uc-mini-services'); ?></label></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Logo', 'bp-uc-mini-services'); ?></th><td><?php $this->render_media_field('logo_url', $saved_settings['logo_url']); ?></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Favicon', 'bp-uc-mini-services'); ?></th><td><?php $this->render_media_field('favicon_url', $saved_settings['favicon_url']); ?></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Contact email', 'bp-uc-mini-services'); ?></th><td><input type="email" class="regular-text" name="contact_email" value="<?php echo esc_attr($saved_settings['contact_email']); ?>"></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Footer text', 'bp-uc-mini-services'); ?></th><td><textarea class="large-text" rows="2" name="footer_text"><?php echo esc_textarea($saved_settings['footer_text']); ?></textarea></td></tr>
                    </table>
                </div>

                <div class="bp-ucms-card">
                    <h2><?php esc_html_e('Greek content', 'bp-uc-mini-services'); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr><th scope="row"><?php esc_html_e('Title (EL)', 'bp-uc-mini-services'); ?></th><td><input type="text" class="regular-text" name="uc_title_el" value="<?php echo esc_attr($saved_settings['uc_title_el']); ?>"></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Subtitle (EL)', 'bp-uc-mini-services'); ?></th><td><textarea class="large-text" rows="3" name="uc_subtitle_el"><?php echo esc_textarea($saved_settings['uc_subtitle_el']); ?></textarea></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Contact text (EL)', 'bp-uc-mini-services'); ?></th><td><textarea class="large-text" rows="2" name="uc_contact_el"><?php echo esc_textarea($saved_settings['uc_contact_el']); ?></textarea></td></tr>
                    </table>
                </div>

                <div class="bp-ucms-card">
                    <h2><?php esc_html_e('English content', 'bp-uc-mini-services'); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr><th scope="row"><?php esc_html_e('Title (EN)', 'bp-uc-mini-services'); ?></th><td><input type="text" class="regular-text" name="uc_title_en" value="<?php echo esc_attr($saved_settings['uc_title_en']); ?>"></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Subtitle (EN)', 'bp-uc-mini-services'); ?></th><td><textarea class="large-text" rows="3" name="uc_subtitle_en"><?php echo esc_textarea($saved_settings['uc_subtitle_en']); ?></textarea></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Contact text (EN)', 'bp-uc-mini-services'); ?></th><td><textarea class="large-text" rows="2" name="uc_contact_en"><?php echo esc_textarea($saved_settings['uc_contact_en']); ?></textarea></td></tr>
                    </table>
                </div>

                <div class="bp-ucms-card">
                    <h2><?php esc_html_e('Countdown', 'bp-uc-mini-services'); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr><th scope="row"><?php esc_html_e('Enable countdown', 'bp-uc-mini-services'); ?></th><td><label><input type="checkbox" name="countdown_enabled" value="1" <?php checked((int) $saved_settings['countdown_enabled'], 1); ?>> <?php esc_html_e('Show countdown area on the under construction page.', 'bp-uc-mini-services'); ?></label></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Countdown start', 'bp-uc-mini-services'); ?></th><td><input type="datetime-local" name="countdown_start" value="<?php echo esc_attr($saved_settings['countdown_start']); ?>"><p class="description"><?php esc_html_e('Format uses the WordPress site timezone.', 'bp-uc-mini-services'); ?></p></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Countdown end', 'bp-uc-mini-services'); ?></th><td><input type="datetime-local" name="countdown_end" value="<?php echo esc_attr($saved_settings['countdown_end']); ?>"></td></tr>
                    </table>
                </div>

                <div class="bp-ucms-card">
                    <h2><?php esc_html_e('Login hardening', 'bp-uc-mini-services'); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr><th scope="row"><?php esc_html_e('Custom login slug', 'bp-uc-mini-services'); ?></th><td><input type="text" class="regular-text" name="custom_login_slug" value="<?php echo esc_attr($saved_settings['custom_login_slug']); ?>"><p class="description"><?php esc_html_e('Example: members-login', 'bp-uc-mini-services'); ?></p></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Block direct /wp-login.php access', 'bp-uc-mini-services'); ?></th><td><label><input type="checkbox" name="block_wp_login" value="1" <?php checked((int) $saved_settings['block_wp_login'], 1); ?>> <?php esc_html_e('Visitors can only access the login page from the custom URL.', 'bp-uc-mini-services'); ?></label></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Redirect blocked wp-login.php visitors to page', 'bp-uc-mini-services'); ?></th><td><?php $this->render_pages_dropdown('blocked_login_redirect_page_id', (int) $saved_settings['blocked_login_redirect_page_id'], $pages, true); ?></td></tr>
                    </table>
                </div>

                <div class="bp-ucms-card">
                    <h2><?php esc_html_e('Role-based login redirects', 'bp-uc-mini-services'); ?></h2>
                    <p><?php esc_html_e('You can keep users on the same page they came from, send them to a specific page, or send them to wp-admin.', 'bp-uc-mini-services'); ?></p>
                    <table class="form-table" role="presentation">
                        <?php foreach ($this->settings->supported_roles() as $role_key => $role_label) : ?>
                            <tr>
                                <th scope="row"><?php echo esc_html($role_label); ?></th>
                                <td>
                                    <select name="redirect_<?php echo esc_attr($role_key); ?>_mode">
                                        <option value="same" <?php selected($saved_settings['redirect_' . $role_key . '_mode'], 'same'); ?>><?php esc_html_e('Stay on the same page', 'bp-uc-mini-services'); ?></option>
                                        <option value="page" <?php selected($saved_settings['redirect_' . $role_key . '_mode'], 'page'); ?>><?php esc_html_e('Redirect to a page', 'bp-uc-mini-services'); ?></option>
                                        <option value="admin" <?php selected($saved_settings['redirect_' . $role_key . '_mode'], 'admin'); ?>><?php esc_html_e('Redirect to wp-admin', 'bp-uc-mini-services'); ?></option>
                                    </select>
                                    &nbsp;
                                    <?php $this->render_pages_dropdown('redirect_' . $role_key . '_page_id', (int) $saved_settings['redirect_' . $role_key . '_page_id'], $pages, true); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>

                <div class="bp-ucms-card">
                    <h2><?php esc_html_e('Logout redirect', 'bp-uc-mini-services'); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr><th scope="row"><?php esc_html_e('Page after logout', 'bp-uc-mini-services'); ?></th><td><?php $this->render_pages_dropdown('logout_redirect_page_id', (int) $saved_settings['logout_redirect_page_id'], $pages, true); ?></td></tr>
                    </table>
                </div>

                <div class="bp-ucms-card">
                    <h2><?php esc_html_e('SMTP', 'bp-uc-mini-services'); ?></h2>
                    <p class="description"><?php esc_html_e('You can override SMTP runtime settings from wp-config.php with BP_UCMS_* constants.', 'bp-uc-mini-services'); ?></p>
                    <table class="form-table" role="presentation">
                        <?php $this->render_smtp_row_checkbox('smtp_enabled', __('Enable custom SMTP', 'bp-uc-mini-services'), __('Send WordPress emails through your SMTP server.', 'bp-uc-mini-services'), $saved_settings, $runtime_settings, $constant_map); ?>
                        <?php $this->render_smtp_row_text('smtp_host', __('SMTP host', 'bp-uc-mini-services'), $saved_settings, $runtime_settings, $constant_map); ?>
                        <tr>
                            <th scope="row"><?php esc_html_e('SMTP port', 'bp-uc-mini-services'); ?></th>
                            <td>
                                <input type="text" class="small-text" name="smtp_port" value="<?php echo esc_attr($saved_settings['smtp_port']); ?>">
                                <?php $this->render_constant_notice('smtp_port', $runtime_settings, $constant_map); ?>
                                <p class="description"><?php esc_html_e('The port updates automatically when you change encryption: None = 25, SSL = 465, TLS = 587.', 'bp-uc-mini-services'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Encryption', 'bp-uc-mini-services'); ?></th>
                            <td>
                                <select name="smtp_encryption">
                                    <option value="none" <?php selected($saved_settings['smtp_encryption'], 'none'); ?>><?php esc_html_e('None', 'bp-uc-mini-services'); ?></option>
                                    <option value="ssl" <?php selected($saved_settings['smtp_encryption'], 'ssl'); ?>>SSL</option>
                                    <option value="tls" <?php selected($saved_settings['smtp_encryption'], 'tls'); ?>>TLS</option>
                                </select>
                                <?php $this->render_constant_notice('smtp_encryption', $runtime_settings, $constant_map); ?>
                            </td>
                        </tr>
                        <?php $this->render_smtp_row_checkbox('smtp_autotls', __('Auto TLS', 'bp-uc-mini-services'), __('Allow PHPMailer to upgrade to TLS automatically when supported.', 'bp-uc-mini-services'), $saved_settings, $runtime_settings, $constant_map); ?>
                        <?php $this->render_smtp_row_checkbox('smtp_auth', __('Authentication', 'bp-uc-mini-services'), __('Use SMTP username and password.', 'bp-uc-mini-services'), $saved_settings, $runtime_settings, $constant_map); ?>
                        <?php $this->render_smtp_row_text('smtp_username', __('SMTP username', 'bp-uc-mini-services'), $saved_settings, $runtime_settings, $constant_map); ?>
                        <tr>
                            <th scope="row"><?php esc_html_e('SMTP password', 'bp-uc-mini-services'); ?></th>
                            <td>
                                <input type="password" class="regular-text" name="smtp_password" value="" autocomplete="new-password" placeholder="<?php echo !empty($saved_settings['smtp_password']) ? esc_attr__('Saved password will be kept if left empty', 'bp-uc-mini-services') : ''; ?>">
                                <?php $this->render_constant_notice('smtp_password', $runtime_settings, $constant_map, true); ?>
                                <p class="description"><?php esc_html_e('Leave blank to keep the current password.', 'bp-uc-mini-services'); ?></p>
                            </td>
                        </tr>
                        <?php $this->render_smtp_row_text('mail_from_email', __('From email', 'bp-uc-mini-services'), $saved_settings, $runtime_settings, $constant_map); ?>
                        <?php $this->render_smtp_row_text('mail_from_name', __('From name', 'bp-uc-mini-services'), $saved_settings, $runtime_settings, $constant_map); ?>
                        <?php $this->render_smtp_row_checkbox('force_mail_from_email', __('Force from email', 'bp-uc-mini-services'), __('Override the sender email for all wp_mail() emails.', 'bp-uc-mini-services'), $saved_settings, $runtime_settings, $constant_map); ?>
                        <?php $this->render_smtp_row_checkbox('force_mail_from_name', __('Force from name', 'bp-uc-mini-services'), __('Override the sender name for all wp_mail() emails.', 'bp-uc-mini-services'), $saved_settings, $runtime_settings, $constant_map); ?>
                        <?php $this->render_smtp_row_checkbox('smtp_html_emails', __('Send HTML emails', 'bp-uc-mini-services'), __('Set the global email content type to text/html.', 'bp-uc-mini-services'), $saved_settings, $runtime_settings, $constant_map); ?>
                        <?php $this->render_smtp_row_checkbox('smtp_error_log_enabled', __('Enable error log', 'bp-uc-mini-services'), __('Log only email sending errors for debugging.', 'bp-uc-mini-services'), $saved_settings, $runtime_settings, $constant_map, __('Entries older than 30 days are removed automatically.', 'bp-uc-mini-services')); ?>
                    </table>
                </div>

                <div class="bp-ucms-card">
                    <h2><?php esc_html_e('Two-Factor Authentication (2FA)', 'bp-uc-mini-services'); ?></h2>
                    <p class="description"><?php esc_html_e('Enable the 2FA module globally. When enabled, users can configure TOTP 2FA from their own profile page.', 'bp-uc-mini-services'); ?></p>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable 2FA module', 'bp-uc-mini-services'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="twofa_enabled" value="1" <?php checked((int) $saved_settings['twofa_enabled'], 1); ?>>
                                    <?php esc_html_e('Allow users to enable TOTP 2FA from their profile page.', 'bp-uc-mini-services'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('2FA issuer', 'bp-uc-mini-services'); ?></th>
                            <td>
                                <input type="text" class="regular-text" name="twofa_issuer" value="<?php echo esc_attr($saved_settings['twofa_issuer']); ?>">
                                <p class="description"><?php esc_html_e('This name appears in authenticator apps such as Authy and Google Authenticator.', 'bp-uc-mini-services'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>


                <div class="bp-ucms-card">
                    <h2><?php esc_html_e('SMTP error log', 'bp-uc-mini-services'); ?></h2>
                    <p><?php esc_html_e('Only errors are stored. Successful emails are not logged.', 'bp-uc-mini-services'); ?></p>
                    <textarea class="bp-ucms-log-viewer" readonly><?php echo esc_textarea(implode(PHP_EOL, bp_ucms()->smtp->get_recent_log_lines())); ?></textarea>
                </div>

                <?php submit_button(__('Save settings', 'bp-uc-mini-services')); ?>
            </form>

            <div class="bp-ucms-card">
                <h2><?php esc_html_e('SMTP test email', 'bp-uc-mini-services'); ?></h2>
                <p><?php esc_html_e('Send a test email using the current SMTP settings.', 'bp-uc-mini-services'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('bp_ucms_send_test_email'); ?>
                    <input type="hidden" name="action" value="bp_ucms_send_test_email">
                    <input type="email" class="regular-text" name="test_email_to" value="<?php echo esc_attr(get_option('admin_email')); ?>">
                    <?php submit_button(__('Send test email', 'bp-uc-mini-services'), 'secondary', 'submit', false); ?>
                </form>
            </div>

            <div class="bp-ucms-card">
                <h2><?php esc_html_e('Settings export / import', 'bp-uc-mini-services'); ?></h2>
                <p><?php esc_html_e('Export your plugin settings to JSON or import them on another site. Import replaces the current plugin settings only.', 'bp-uc-mini-services'); ?></p>
                <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start;">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field(BP_UCMS_Settings::EXPORT_ACTION); ?>
                        <input type="hidden" name="action" value="bp_ucms_export_settings">
                        <?php submit_button(__('Export settings', 'bp-uc-mini-services'), 'secondary', 'submit', false); ?>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                        <?php wp_nonce_field(BP_UCMS_Settings::IMPORT_ACTION); ?>
                        <input type="hidden" name="action" value="bp_ucms_import_settings">
                        <input type="file" name="bp_ucms_import_file" accept="application/json,.json" required>
                        <?php submit_button(__('Import settings', 'bp-uc-mini-services'), 'secondary', 'submit', false); ?>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_notices(): void {
        if (isset($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved successfully.', 'bp-uc-mini-services') . '</p></div>';
        }

        if (isset($_GET['smtp_test'])) {
            $status = sanitize_key(wp_unslash($_GET['smtp_test']));
            if ($status === 'success') {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('SMTP test email sent successfully.', 'bp-uc-mini-services') . '</p></div>';
            } elseif ($status === 'failed') {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('SMTP test email failed to send. Check your SMTP settings and error log.', 'bp-uc-mini-services') . '</p></div>';
            } elseif ($status === 'invalid_email') {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Please provide a valid test email address.', 'bp-uc-mini-services') . '</p></div>';
            }
        }

        if (isset($_GET['import'])) {
            $status = sanitize_key(wp_unslash($_GET['import']));
            $messages = [
                'success' => ['success', __('Settings imported successfully.', 'bp-uc-mini-services')],
                'missing' => ['error', __('Please select a JSON file to import.', 'bp-uc-mini-services')],
                'upload_error' => ['error', __('The import file could not be uploaded.', 'bp-uc-mini-services')],
                'invalid' => ['error', __('The selected file is not a valid BP UC & Mini Services settings export.', 'bp-uc-mini-services')],
            ];
            if (isset($messages[$status])) {
                [$type, $message] = $messages[$status];
                echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
            }
        }
    }

    private function render_pages_dropdown(string $name, int $selected, array $pages, bool $allow_empty = true): void {
        echo '<select name="' . esc_attr($name) . '">';
        if ($allow_empty) {
            echo '<option value="0">' . esc_html__('— Select a page —', 'bp-uc-mini-services') . '</option>';
        }
        foreach ($pages as $page) {
            echo '<option value="' . esc_attr((string) $page->ID) . '" ' . selected($selected, (int) $page->ID, false) . '>' . esc_html($page->post_title ?: ('#' . $page->ID)) . '</option>';
        }
        echo '</select>';
    }

    private function render_media_field(string $name, string $value): void {
        ?>
        <div class="bp-ucms-media-field">
            <input type="url" class="regular-text bp-ucms-media-url" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>">
            <button type="button" class="button bp-ucms-media-button"><?php esc_html_e('Select media', 'bp-uc-mini-services'); ?></button>
            <button type="button" class="button bp-ucms-media-clear"><?php esc_html_e('Clear', 'bp-uc-mini-services'); ?></button>
            <div class="bp-ucms-media-preview">
                <?php if (!empty($value)) : ?>
                    <img src="<?php echo esc_url($value); ?>" alt="">
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function render_smtp_row_text(string $key, string $label, array $saved_settings, array $runtime_settings, array $constant_map): void {
        echo '<tr><th scope="row">' . esc_html($label) . '</th><td><input type="text" class="regular-text" name="' . esc_attr($key) . '" value="' . esc_attr((string) $saved_settings[$key]) . '">';
        $this->render_constant_notice($key, $runtime_settings, $constant_map);
        echo '</td></tr>';
    }

    private function render_smtp_row_checkbox(string $key, string $label, string $description, array $saved_settings, array $runtime_settings, array $constant_map, string $extra_description = ''): void {
        echo '<tr><th scope="row">' . esc_html($label) . '</th><td><label><input type="checkbox" name="' . esc_attr($key) . '" value="1" ' . checked((int) $saved_settings[$key], 1, false) . '> ' . esc_html($description) . '</label>';
        $this->render_constant_notice($key, $runtime_settings, $constant_map);
        if ($extra_description !== '') {
            echo '<p class="description">' . esc_html($extra_description) . '</p>';
        }
        echo '</td></tr>';
    }

    private function render_constant_notice(string $key, array $runtime_settings, array $constant_map, bool $mask = false): void {
        if (empty($constant_map[$key]) || !defined($constant_map[$key])) {
            return;
        }

        $value = $runtime_settings[$key] ?? '';
        if ($mask) {
            $value = '********';
        } elseif (is_bool($value) || $value === 0 || $value === 1) {
            $value = $value ? 'true' : 'false';
        }

        echo '<p class="description"><strong>' . esc_html($constant_map[$key]) . '</strong> ' . esc_html__('is active from wp-config.php. Runtime value:', 'bp-uc-mini-services') . ' <code>' . esc_html((string) $value) . '</code></p>';
    }
}
