<?php
if (!defined('ABSPATH')) {
    exit;
}

final class BP_UCMS_Two_FA {
    private const META_ENABLED = 'bp_ucms_2fa_enabled';
    private const META_SECRET = 'bp_ucms_2fa_secret';
    private const META_PENDING_SECRET = 'bp_ucms_2fa_pending_secret';
    private const META_RECOVERY_CODES = 'bp_ucms_2fa_recovery_codes';
    private const LOGIN_TOKEN_PREFIX = 'bp_ucms_2fa_login_';
    private const RECOVERY_CODES_TRANSIENT_PREFIX = 'bp_ucms_2fa_codes_';
    private const LOGIN_TOKEN_TTL = 600;
    private const OTP_WINDOW = 1;

    private BP_UCMS_Settings $settings;

    public function __construct(BP_UCMS_Settings $settings) {
        $this->settings = $settings;
    }

    public function init(): void {
        add_action('show_user_profile', [$this, 'render_profile_section']);
        add_action('edit_user_profile', [$this, 'render_profile_section']);
        add_action('admin_notices', [$this, 'render_profile_notices']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_profile_assets']);
        add_action('wp_ajax_bp_ucms_enable_2fa', [$this, 'handle_enable_ajax']);
        add_action('wp_ajax_bp_ucms_disable_2fa', [$this, 'handle_disable_ajax']);
        add_action('wp_ajax_bp_ucms_regenerate_2fa_recovery', [$this, 'handle_regenerate_recovery_ajax']);
        add_filter('authenticate', [$this, 'maybe_intercept_login'], 80, 3);
        add_action('login_form_bp_ucms_2fa', [$this, 'render_2fa_login_form']);
    }

    public function enqueue_profile_assets(string $hook): void {
        if (!in_array($hook, ['profile.php', 'user-edit.php'], true)) {
            return;
        }

        wp_enqueue_script('bp-ucms-qrcode', BP_UCMS_URL . 'assets/js/qrcode-bundle.js', [], BP_UCMS_VERSION, true);
        wp_enqueue_script('bp-ucms-profile-2fa', BP_UCMS_URL . 'assets/js/profile-2fa.js', ['bp-ucms-qrcode'], BP_UCMS_VERSION, true);
        wp_localize_script('bp-ucms-profile-2fa', 'bpUcms2fa', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'enableNonce' => wp_create_nonce('bp_ucms_enable_2fa_ajax'),
            'disableNonce' => wp_create_nonce('bp_ucms_disable_2fa_ajax'),
            'regenerateNonce' => wp_create_nonce('bp_ucms_regenerate_2fa_recovery_ajax'),
            'invalidCodeMessage' => __('Please enter a valid 6-digit verification code.', 'bp-uc-mini-services'),
            'requestFailedMessage' => __('The request could not be completed. Please try again.', 'bp-uc-mini-services'),
            'disableConfirmMessage' => __('Disable 2FA for this account?', 'bp-uc-mini-services'),
            'busyMessage' => __('Working...', 'bp-uc-mini-services'),
            'copiedMessage' => __('Copied', 'bp-uc-mini-services'),
            'copyFailedMessage' => __('Copy failed. Copy the recovery codes manually from the box below.', 'bp-uc-mini-services'),
            'downloadFailedMessage' => __('The recovery codes could not be downloaded automatically. Please copy them manually from the box below.', 'bp-uc-mini-services'),
        ]);
        wp_add_inline_style('common', '.bp-ucms-2fa-wrap{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;margin-top:16px;max-width:900px}.bp-ucms-2fa-grid{display:grid;grid-template-columns:minmax(220px,260px) 1fr;gap:20px;align-items:start}.bp-ucms-2fa-qr svg{max-width:220px;height:auto;border:1px solid #dcdcde;background:#fff;padding:8px;border-radius:8px}.bp-ucms-2fa-secret{font-family:monospace;font-size:14px;word-break:break-all;background:#f6f7f7;padding:8px;border-radius:6px}.bp-ucms-2fa-recovery-list code{display:inline-block;margin:0 8px 8px 0;padding:6px 8px;background:#f6f7f7;border-radius:6px}.bp-ucms-2fa-status{font-weight:600}.bp-ucms-2fa-muted{color:#646970}.bp-ucms-2fa-warning{color:#b32d2e;font-weight:600}.bp-ucms-2fa-actions{display:flex;flex-wrap:wrap;gap:10px;align-items:center}.bp-ucms-2fa-actions .button[disabled]{opacity:.7;cursor:wait}@media (max-width:782px){.bp-ucms-2fa-grid{grid-template-columns:1fr}}');
    }

    public function render_profile_section(WP_User $user): void {
        if (!$this->is_module_enabled()) {
            return;
        }

        $viewer_id = get_current_user_id();
        $can_manage_target = current_user_can('edit_user', $user->ID);
        if (!$can_manage_target) {
            return;
        }

        $is_self = ((int) $viewer_id === (int) $user->ID);
        $enabled = $this->is_user_enabled($user->ID);
        $issuer = $this->get_issuer_name();
        $pending_secret = '';
        $otpauth_uri = '';

        if ($is_self && !$enabled) {
            $pending_secret = $this->get_or_create_pending_secret($user->ID);
            $otpauth_uri = $this->build_otpauth_uri($user, $pending_secret, $issuer);
        }

        $recovery_codes = [];
        if ($is_self && isset($_GET['bp_ucms_2fa_codes'])) {
            $transient_key = self::RECOVERY_CODES_TRANSIENT_PREFIX . md5((string) $user->ID . '|' . sanitize_key(wp_unslash($_GET['bp_ucms_2fa_codes'])));
            $recovery_codes = get_transient($transient_key);
            if (is_array($recovery_codes)) {
                delete_transient($transient_key);
            } else {
                $recovery_codes = [];
            }
        }
        ?>
        <h2><?php esc_html_e('Two-Factor Authentication (2FA)', 'bp-uc-mini-services'); ?></h2>
        <div class="bp-ucms-2fa-wrap">
            <p class="bp-ucms-2fa-status">
                <?php echo $enabled ? esc_html__('2FA is enabled for this account.', 'bp-uc-mini-services') : esc_html__('2FA is currently disabled for this account.', 'bp-uc-mini-services'); ?>
            </p>

            <?php if ($enabled) : ?>
                <p class="bp-ucms-2fa-muted"><?php esc_html_e('Use your authenticator app to generate a 6-digit code during login. Recovery codes can be used once each if you lose access to your app.', 'bp-uc-mini-services'); ?></p>
                <?php if (!empty($recovery_codes)) : ?>
                    <div class="notice notice-warning inline"><p><strong><?php esc_html_e('Save these new recovery codes now.', 'bp-uc-mini-services'); ?></strong> <?php esc_html_e('They are shown only once. Download or copy them before leaving this page.', 'bp-uc-mini-services'); ?></p></div>
                    <div class="bp-ucms-2fa-recovery-actions">
                        <button type="button" class="button button-secondary bp-ucms-copy-recovery" data-target="#bp-ucms-2fa-recovery-list-<?php echo esc_attr((string) $user->ID); ?>" data-plain-text="<?php echo esc_attr(implode("
", $recovery_codes)); ?>"><?php esc_html_e('Copy recovery codes', 'bp-uc-mini-services'); ?></button>
                        <button type="button" class="button button-secondary bp-ucms-download-recovery" data-target="#bp-ucms-2fa-recovery-list-<?php echo esc_attr((string) $user->ID); ?>" data-filename="bp-ucms-recovery-codes-<?php echo esc_attr((string) $user->ID); ?>.txt" data-plain-text="<?php echo esc_attr(implode("
", $recovery_codes)); ?>"><?php esc_html_e('Download TXT', 'bp-uc-mini-services'); ?></button>
                        <span class="bp-ucms-2fa-muted"><?php esc_html_e('Store them somewhere safe. Each code can be used only once.', 'bp-uc-mini-services'); ?></span>
                    </div>
                    <div class="bp-ucms-2fa-recovery-list" id="bp-ucms-2fa-recovery-list-<?php echo esc_attr((string) $user->ID); ?>">
                        <?php foreach ($recovery_codes as $code) : ?>
                            <code><?php echo esc_html($code); ?></code>
                        <?php endforeach; ?>
                    </div>
                    <textarea class="large-text code bp-ucms-recovery-textarea" rows="8" readonly><?php echo esc_textarea(implode("
", $recovery_codes)); ?></textarea>
                <?php endif; ?>

                <?php if ($is_self || current_user_can('manage_options')) : ?>
                    <p class="bp-ucms-2fa-actions">
                        <button type="button" class="button button-secondary bp-ucms-regenerate-2fa" data-user-id="<?php echo esc_attr((string) $user->ID); ?>"><?php esc_html_e('Regenerate recovery codes', 'bp-uc-mini-services'); ?></button>
                        <button type="button" class="button button-secondary bp-ucms-disable-2fa" data-user-id="<?php echo esc_attr((string) $user->ID); ?>"><?php esc_html_e('Disable 2FA', 'bp-uc-mini-services'); ?></button>
                    </p>
                <?php endif; ?>
            <?php elseif ($is_self) : ?>
                <div class="bp-ucms-2fa-grid">
                    <div>
                        <div class="bp-ucms-2fa-qr" data-otpauth="<?php echo esc_attr($otpauth_uri); ?>"></div>
                    </div>
                    <div>
                        <p><?php esc_html_e('Scan this QR code with any compatible authenticator app, such as Google Authenticator, Microsoft Authenticator, Authy, 1Password, Bitwarden, or Aegis.', 'bp-uc-mini-services'); ?></p>
                        <p><strong><?php esc_html_e('Manual setup key', 'bp-uc-mini-services'); ?></strong></p>
                        <div class="bp-ucms-2fa-secret"><?php echo esc_html($pending_secret); ?></div>
                        <p class="bp-ucms-2fa-muted"><?php esc_html_e('Then enter a valid 6-digit code below to finish activation.', 'bp-uc-mini-services'); ?></p>

                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><label for="bp_ucms_2fa_code"><?php esc_html_e('Verification code', 'bp-uc-mini-services'); ?></label></th>
                                <td>
                                    <input type="text" class="regular-text" name="bp_ucms_2fa_code" id="bp_ucms_2fa_code" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code">
                                    <p class="description"><?php esc_html_e('Enter the current 6-digit code from your authenticator app and click Enable 2FA.', 'bp-uc-mini-services'); ?></p>
                                    <p><button type="button" class="button button-primary bp-ucms-enable-2fa-submit" data-user-id="<?php echo esc_attr((string) $user->ID); ?>" data-code-source="#bp_ucms_2fa_code"><?php esc_html_e('Enable 2FA', 'bp-uc-mini-services'); ?></button></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            <?php else : ?>
                <p class="bp-ucms-2fa-muted"><?php esc_html_e('2FA can only be configured by the account owner from their own profile.', 'bp-uc-mini-services'); ?></p>
                <?php if (current_user_can('manage_options')) : ?>
                    <p><button type="button" class="button button-secondary bp-ucms-disable-2fa" data-user-id="<?php echo esc_attr((string) $user->ID); ?>"><?php esc_html_e('Admin reset 2FA', 'bp-uc-mini-services'); ?></button></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }


    public function render_profile_notices(): void {
        global $pagenow;
        if (!in_array($pagenow, ['profile.php', 'user-edit.php'], true) || empty($_GET['bp_ucms_2fa_notice'])) {
            return;
        }

        $status = sanitize_key(wp_unslash($_GET['bp_ucms_2fa_notice']));
        $notices = [
            'enabled' => ['success', __('Two-factor authentication has been enabled.', 'bp-uc-mini-services')],
            'disabled' => ['success', __('Two-factor authentication has been disabled.', 'bp-uc-mini-services')],
            'recovery_regenerated' => ['success', __('Recovery codes have been regenerated.', 'bp-uc-mini-services')],
            'invalid_code' => ['error', __('The verification code was not valid. Please try again.', 'bp-uc-mini-services')],
            'expired' => ['error', __('Your 2FA setup session expired. Please reload your profile and try again.', 'bp-uc-mini-services')],
            'not_allowed' => ['error', __('You are not allowed to change 2FA for this account.', 'bp-uc-mini-services')],
        ];

        if (!isset($notices[$status])) {
            return;
        }

        [$type, $message] = $notices[$status];
        echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    public function handle_enable_ajax(): void {
        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        check_ajax_referer('bp_ucms_enable_2fa_ajax', 'nonce');

        if (!$user_id) {
            wp_send_json_error(['message' => __('Invalid user.', 'bp-uc-mini-services')], 400);
        }

        if ((int) get_current_user_id() !== $user_id || !current_user_can('edit_user', $user_id)) {
            wp_send_json_error(['message' => __('You are not allowed to change 2FA for this account.', 'bp-uc-mini-services')], 403);
        }

        $code = isset($_POST['bp_ucms_2fa_code']) ? sanitize_text_field(wp_unslash($_POST['bp_ucms_2fa_code'])) : '';
        $secret = (string) get_user_meta($user_id, self::META_PENDING_SECRET, true);

        if ($secret === '') {
            wp_send_json_success(['redirect' => $this->get_profile_redirect_url($user_id, 'expired')]);
        }

        if (!$this->verify_totp_code($secret, $code)) {
            wp_send_json_success(['redirect' => $this->get_profile_redirect_url($user_id, 'invalid_code')]);
        }

        update_user_meta($user_id, self::META_SECRET, $this->encrypt_secret($secret));
        update_user_meta($user_id, self::META_ENABLED, 1);
        delete_user_meta($user_id, self::META_PENDING_SECRET);

        $codes = $this->generate_recovery_codes();
        update_user_meta($user_id, self::META_RECOVERY_CODES, $this->hash_recovery_codes($codes));
        $token = wp_generate_password(20, false, false);
        set_transient(self::RECOVERY_CODES_TRANSIENT_PREFIX . md5((string) $user_id . '|' . $token), $codes, HOUR_IN_SECONDS);

        wp_send_json_success(['redirect' => $this->get_profile_redirect_url($user_id, 'enabled', ['bp_ucms_2fa_codes' => $token])]);
    }

    public function handle_disable_ajax(): void {
        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        check_ajax_referer('bp_ucms_disable_2fa_ajax', 'nonce');

        if (!$user_id) {
            wp_send_json_error(['message' => __('Invalid user.', 'bp-uc-mini-services')], 400);
        }

        if (!current_user_can('edit_user', $user_id)) {
            wp_send_json_error(['message' => __('You are not allowed to change 2FA for this account.', 'bp-uc-mini-services')], 403);
        }

        $this->disable_user_2fa($user_id);
        wp_send_json_success(['redirect' => $this->get_profile_redirect_url($user_id, 'disabled')]);
    }

    public function handle_regenerate_recovery_ajax(): void {
        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        check_ajax_referer('bp_ucms_regenerate_2fa_recovery_ajax', 'nonce');

        if (!$user_id) {
            wp_send_json_error(['message' => __('Invalid user.', 'bp-uc-mini-services')], 400);
        }

        if (!current_user_can('edit_user', $user_id) || !$this->is_user_enabled($user_id)) {
            wp_send_json_error(['message' => __('You are not allowed to change 2FA for this account.', 'bp-uc-mini-services')], 403);
        }

        $codes = $this->generate_recovery_codes();
        update_user_meta($user_id, self::META_RECOVERY_CODES, $this->hash_recovery_codes($codes));
        $token = wp_generate_password(20, false, false);
        set_transient(self::RECOVERY_CODES_TRANSIENT_PREFIX . md5((string) $user_id . '|' . $token), $codes, HOUR_IN_SECONDS);

        wp_send_json_success(['redirect' => $this->get_profile_redirect_url($user_id, 'recovery_regenerated', ['bp_ucms_2fa_codes' => $token])]);
    }

    public function maybe_intercept_login($user, string $username, string $password) {
        if (!($user instanceof WP_User)) {
            return $user;
        }

        if (!$this->is_module_enabled() || !$this->is_user_enabled($user->ID) || !$this->is_login_form_request()) {
            return $user;
        }

        $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : 'login';
        if ($action !== 'login' && $action !== '') {
            return $user;
        }

        if (!empty($_REQUEST['bp_ucms_2fa_verified'])) {
            return $user;
        }

        $token = wp_generate_password(40, false, false);
        $remember = !empty($_REQUEST['rememberme']);
        $redirect_to = isset($_REQUEST['redirect_to']) ? esc_url_raw(wp_unslash($_REQUEST['redirect_to'])) : '';
        $context = [
            'user_id' => (int) $user->ID,
            'remember' => $remember ? 1 : 0,
            'redirect_to' => $redirect_to,
            'ua' => isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) wp_unslash($_SERVER['HTTP_USER_AGENT']), 0, 255) : '',
            'ip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
        ];
        set_transient(self::LOGIN_TOKEN_PREFIX . $token, $context, self::LOGIN_TOKEN_TTL);

        $twofa_url = add_query_arg([
            'action' => 'bp_ucms_2fa',
            'token' => rawurlencode($token),
            'bp_ucms_from' => 'custom-login',
        ], wp_login_url());

        wp_safe_redirect($twofa_url);
        exit;
    }

    public function render_2fa_login_form(): void {
        $token = isset($_REQUEST['token']) ? sanitize_text_field(wp_unslash($_REQUEST['token'])) : '';
        $context = $token ? get_transient(self::LOGIN_TOKEN_PREFIX . $token) : false;

        if (!$token || !is_array($context) || empty($context['user_id'])) {
            wp_safe_redirect(add_query_arg('login', 'expired', wp_login_url()));
            exit;
        }

        $user = get_user_by('id', (int) $context['user_id']);
        if (!$user instanceof WP_User || !$this->is_user_enabled($user->ID)) {
            delete_transient(self::LOGIN_TOKEN_PREFIX . $token);
            wp_safe_redirect(add_query_arg('login', 'failed', wp_login_url()));
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            check_admin_referer('bp_ucms_verify_2fa_' . $token);
            $code = isset($_POST['bp_ucms_2fa_code']) ? sanitize_text_field(wp_unslash($_POST['bp_ucms_2fa_code'])) : '';

            $valid = false;
            if ($this->verify_user_totp_code($user->ID, $code)) {
                $valid = true;
            } elseif ($this->consume_recovery_code($user->ID, $code)) {
                $valid = true;
            }

            if ($valid) {
                delete_transient(self::LOGIN_TOKEN_PREFIX . $token);
                wp_set_auth_cookie($user->ID, !empty($context['remember']), is_ssl());
                $_REQUEST['bp_ucms_2fa_verified'] = '1';
                do_action('wp_login', $user->user_login, $user);
                $redirect_to = !empty($context['redirect_to']) ? $context['redirect_to'] : '';
                $redirect_to = apply_filters('login_redirect', $redirect_to, $redirect_to, $user);
                wp_safe_redirect($redirect_to ?: home_url('/'));
                exit;
            }

            $error = new WP_Error('bp_ucms_invalid_2fa', __('The authentication code was invalid. Try again or use a recovery code.', 'bp-uc-mini-services'));
            login_header(__('Two-Factor Authentication', 'bp-uc-mini-services'), '', $error);
            $this->render_2fa_form_html($token, $user);
            login_footer();
            exit;
        }

        login_header(__('Two-Factor Authentication', 'bp-uc-mini-services'));
        $this->render_2fa_form_html($token, $user);
        login_footer();
        exit;
    }

    private function render_2fa_form_html(string $token, WP_User $user): void {
        ?>
        <form name="bp_ucms_2faform" id="bp_ucms_2faform" action="<?php echo esc_url(add_query_arg(['action' => 'bp_ucms_2fa', 'token' => rawurlencode($token), 'bp_ucms_from' => 'custom-login'], wp_login_url())); ?>" method="post" autocomplete="off">
            <?php wp_nonce_field('bp_ucms_verify_2fa_' . $token); ?>
            <p>
                <?php echo esc_html(sprintf(__('Enter the 6-digit code from your authenticator app for %s.', 'bp-uc-mini-services'), $user->user_login)); ?>
            </p>
            <p>
                <label for="bp_ucms_2fa_code"><?php esc_html_e('Authentication code or recovery code', 'bp-uc-mini-services'); ?><br>
                    <input type="text" name="bp_ucms_2fa_code" id="bp_ucms_2fa_code" class="input" inputmode="numeric" autocomplete="one-time-code" required>
                </label>
            </p>
            <p class="description"><?php esc_html_e('You can also enter one of your one-time recovery codes.', 'bp-uc-mini-services'); ?></p>
            <p class="submit">
                <input type="submit" class="button button-primary button-large" value="<?php echo esc_attr__('Verify and log in', 'bp-uc-mini-services'); ?>">
            </p>
        </form>
        <p id="nav"><a href="<?php echo esc_url(wp_login_url()); ?>"><?php esc_html_e('Back to login', 'bp-uc-mini-services'); ?></a></p>
        <?php
    }

    private function is_module_enabled(): bool {
        $settings = $this->settings->get();
        return !empty($settings['twofa_enabled']);
    }

    private function get_issuer_name(): string {
        $settings = $this->settings->get();
        $issuer = !empty($settings['twofa_issuer']) ? (string) $settings['twofa_issuer'] : get_bloginfo('name');
        return trim($issuer) !== '' ? $issuer : get_bloginfo('name');
    }

    private function get_or_create_pending_secret(int $user_id): string {
        $secret = (string) get_user_meta($user_id, self::META_PENDING_SECRET, true);
        if ($secret !== '') {
            return $secret;
        }
        $secret = $this->generate_base32_secret();
        update_user_meta($user_id, self::META_PENDING_SECRET, $secret);
        return $secret;
    }

    private function build_otpauth_uri(WP_User $user, string $secret, string $issuer): string {
        $account = $user->user_email ?: $user->user_login;
        $label = rawurlencode($issuer . ':' . $account);
        return 'otpauth://totp/' . $label . '?secret=' . rawurlencode($secret) . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
    }

    public function is_user_enabled(int $user_id): bool {
        return (bool) get_user_meta($user_id, self::META_ENABLED, true) && (string) get_user_meta($user_id, self::META_SECRET, true) !== '';
    }

    private function verify_user_totp_code(int $user_id, string $code): bool {
        $secret = $this->get_user_secret($user_id);
        if ($secret === '') {
            return false;
        }
        return $this->verify_totp_code($secret, $code);
    }

    private function get_user_secret(int $user_id): string {
        $stored = (string) get_user_meta($user_id, self::META_SECRET, true);
        return $stored !== '' ? $this->decrypt_secret($stored) : '';
    }

    public function verify_totp_code(string $secret, string $code): bool {
        $code = preg_replace('/\s+/', '', $code);
        if (!preg_match('/^[0-9]{6}$/', (string) $code)) {
            return false;
        }

        $time_step = (int) floor(time() / 30);
        for ($offset = -1 * self::OTP_WINDOW; $offset <= self::OTP_WINDOW; $offset++) {
            if (hash_equals($this->generate_totp_code($secret, $time_step + $offset), (string) $code)) {
                return true;
            }
        }

        return false;
    }

    public function generate_totp_code(string $secret, ?int $time_step = null): string {
        $time_step = $time_step ?? (int) floor(time() / 30);
        $binary_secret = $this->base32_decode($secret);
        if ($binary_secret === '') {
            return '';
        }

        $counter = pack('N*', 0) . pack('N*', $time_step);
        $hash = hash_hmac('sha1', $counter, $binary_secret, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);
        $otp = $binary % 1000000;

        return str_pad((string) $otp, 6, '0', STR_PAD_LEFT);
    }

    private function generate_base32_secret(int $bytes = 20): string {
        return $this->base32_encode(random_bytes($bytes));
    }

    public function base32_encode(string $binary): string {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        $output = '';
        $length = strlen($binary);

        for ($i = 0; $i < $length; $i++) {
            $bits .= str_pad(decbin(ord($binary[$i])), 8, '0', STR_PAD_LEFT);
        }

        $chunks = str_split($bits, 5);
        foreach ($chunks as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $output .= $alphabet[bindec($chunk)];
        }

        return $output;
    }

    public function base32_decode(string $secret): string {
        $alphabet = array_flip(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'));
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret));
        if ($secret === '') {
            return '';
        }

        $bits = '';
        $chars = str_split($secret);
        foreach ($chars as $char) {
            if (!isset($alphabet[$char])) {
                return '';
            }
            $bits .= str_pad(decbin($alphabet[$char]), 5, '0', STR_PAD_LEFT);
        }

        $bytes = str_split($bits, 8);
        $output = '';
        foreach ($bytes as $byte) {
            if (strlen($byte) === 8) {
                $output .= chr(bindec($byte));
            }
        }

        return $output;
    }

    private function generate_recovery_codes(int $count = 8): array {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(wp_generate_password(4, false, false) . '-' . wp_generate_password(4, false, false));
        }
        return $codes;
    }

    private function hash_recovery_codes(array $codes): array {
        $hashed = [];
        foreach ($codes as $code) {
            $normalized = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $code));
            $hashed[] = wp_hash_password($normalized);
        }
        return $hashed;
    }

    private function consume_recovery_code(int $user_id, string $input): bool {
        $stored = get_user_meta($user_id, self::META_RECOVERY_CODES, true);
        if (!is_array($stored) || empty($stored)) {
            return false;
        }

        $normalized = strtoupper(preg_replace('/[^A-Z0-9]/', '', $input));
        if ($normalized === '') {
            return false;
        }

        foreach ($stored as $index => $hash) {
            if (wp_check_password($normalized, $hash)) {
                unset($stored[$index]);
                update_user_meta($user_id, self::META_RECOVERY_CODES, array_values($stored));
                return true;
            }
        }

        return false;
    }

    private function encrypt_secret(string $secret): string {
        $key = hash('sha256', wp_salt('auth'), true);
        if (function_exists('openssl_encrypt')) {
            $iv = random_bytes(16);
            $ciphertext = openssl_encrypt($secret, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            if ($ciphertext !== false) {
                return 'enc:' . base64_encode($iv . $ciphertext);
            }
        }

        return 'plain:' . base64_encode($secret);
    }

    private function decrypt_secret(string $stored): string {
        if (strpos($stored, 'enc:') === 0 && function_exists('openssl_decrypt')) {
            $data = base64_decode(substr($stored, 4), true);
            if ($data !== false && strlen($data) > 16) {
                $iv = substr($data, 0, 16);
                $ciphertext = substr($data, 16);
                $key = hash('sha256', wp_salt('auth'), true);
                $decrypted = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
                if ($decrypted !== false) {
                    return $decrypted;
                }
            }
        }

        if (strpos($stored, 'plain:') === 0) {
            $decoded = base64_decode(substr($stored, 6), true);
            return $decoded !== false ? $decoded : '';
        }

        return '';
    }

    private function disable_user_2fa(int $user_id): void {
        delete_user_meta($user_id, self::META_ENABLED);
        delete_user_meta($user_id, self::META_SECRET);
        delete_user_meta($user_id, self::META_PENDING_SECRET);
        delete_user_meta($user_id, self::META_RECOVERY_CODES);
    }

    private function get_profile_redirect_url(int $user_id, string $notice, array $extra_args = []): string {
        $url = ((int) get_current_user_id() === $user_id)
            ? admin_url('profile.php')
            : add_query_arg('user_id', $user_id, admin_url('user-edit.php'));
        $args = array_merge(['bp_ucms_2fa_notice' => $notice], $extra_args);
        return add_query_arg($args, $url);
    }

    private function redirect_profile_notice(int $user_id, string $notice, array $extra_args = []): void {
        wp_safe_redirect($this->get_profile_redirect_url($user_id, $notice, $extra_args));
        exit;
    }

    private function is_login_form_request(): bool {
        global $pagenow;
        return isset($pagenow) && $pagenow === 'wp-login.php' && !wp_doing_ajax() && !(defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) && !(defined('REST_REQUEST') && REST_REQUEST);
    }

    public static function delete_all_user_meta(): void {
        global $wpdb;
        $keys = [
            self::META_ENABLED,
            self::META_SECRET,
            self::META_PENDING_SECRET,
            self::META_RECOVERY_CODES,
        ];
        foreach ($keys as $key) {
            $wpdb->delete($wpdb->usermeta, ['meta_key' => $key], ['%s']);
        }
    }
}
