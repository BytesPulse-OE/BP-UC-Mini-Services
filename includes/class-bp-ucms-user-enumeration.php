<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Blocks the common ways anonymous visitors can discover valid usernames / user IDs:
 *
 * 1. ?author=N archive lookups (core redirects them to /author/<user_nicename>/).
 * 2. REST API /wp/v2/users endpoints for unauthenticated requests.
 * 3. Author data (name + archive URL) in oEmbed responses.
 * 4. The users provider of the core XML sitemaps (wp-sitemap-users-*.xml).
 * 5. Login error messages that reveal whether a username/email exists.
 * 6. Lost-password responses that reveal whether a username/email exists.
 */
final class BP_UCMS_User_Enumeration {
    private BP_UCMS_Settings $settings;

    public function __construct(BP_UCMS_Settings $settings) {
        $this->settings = $settings;
    }

    public function init(): void {
        if (!$this->is_enabled()) {
            return;
        }

        add_action('parse_request', [$this, 'block_author_query'], 0);
        add_filter('rest_pre_dispatch', [$this, 'block_rest_users_endpoint'], 10, 3);
        add_filter('oembed_response_data', [$this, 'strip_oembed_author'], 10, 1);
        add_filter('wp_sitemaps_add_provider', [$this, 'remove_users_sitemap'], 10, 2);
        add_filter('authenticate', [$this, 'generic_login_error'], PHP_INT_MAX, 3);
        add_filter('lostpassword_errors', [$this, 'mask_unknown_lostpassword_user'], PHP_INT_MAX, 2);
    }

    private function is_enabled(): bool {
        return (int) ($this->settings->get()['block_user_enumeration'] ?? 1) === 1;
    }

    /**
     * ?author=N is resolved by redirect_canonical() to /author/<nicename>/, and
     * user_nicename defaults to the sanitized login name.
     */
    public function block_author_query(WP $wp): void {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        if (!isset($_GET['author']) && !isset($wp->query_vars['author'])) {
            return;
        }

        if (current_user_can('list_users')) {
            return;
        }

        wp_safe_redirect(home_url('/'), 301);
        exit;
    }

    /**
     * Covers /wp-json/wp/v2/users, /wp/v2/users/<id> and the ?rest_route= form.
     * Logged-in users (cookie + nonce, or application passwords) keep access,
     * so the block editor's author selector keeps working.
     */
    public function block_rest_users_endpoint($result, WP_REST_Server $server, WP_REST_Request $request) {
        if ($result !== null || is_user_logged_in()) {
            return $result;
        }

        if (!preg_match('#^/wp/v2/users(?:/|$)#i', (string) $request->get_route())) {
            return $result;
        }

        return new WP_Error(
            'rest_user_cannot_view',
            __('Sorry, you are not allowed to list users.', 'bp-uc-mini-services'),
            ['status' => rest_authorization_required_code()]
        );
    }

    public function strip_oembed_author(array $data): array {
        unset($data['author_name'], $data['author_url']);
        return $data;
    }

    /**
     * @param WP_Sitemaps_Provider|false $provider
     * @return WP_Sitemaps_Provider|false
     */
    public function remove_users_sitemap($provider, string $name) {
        return $name === 'users' ? false : $provider;
    }

    /**
     * Collapses "unknown username", "unknown email" and "wrong password" into one
     * message. Runs last, after core, the spam check and the 2FA intercept; other
     * error codes (empty fields, 2FA, spam) are left untouched.
     */
    public function generic_login_error($user, $username, $password) {
        if (!is_wp_error($user)) {
            return $user;
        }

        $leaking_codes = ['invalid_username', 'invalid_email', 'incorrect_password'];
        $codes = $user->get_error_codes();

        if (!array_intersect($codes, $leaking_codes)) {
            return $user;
        }

        $generic = new WP_Error(
            'incorrect_password',
            sprintf(
                /* translators: %s: lost password URL */
                __('<strong>Error:</strong> The username, email address or password is incorrect. <a href="%s">Lost your password?</a>', 'bp-uc-mini-services'),
                esc_url(wp_lostpassword_url())
            )
        );

        foreach ($codes as $code) {
            if (in_array($code, $leaking_codes, true)) {
                continue;
            }
            foreach ($user->get_error_messages($code) as $message) {
                $generic->add($code, $message, $user->get_error_data($code));
            }
        }

        return $generic;
    }

    /**
     * For an unknown username/email, respond exactly like a successful request
     * (same redirect as wp-login.php) without sending any email.
     */
    public function mask_unknown_lostpassword_user(WP_Error $errors, $user_data): WP_Error {
        if ($user_data instanceof WP_User) {
            return $errors;
        }

        if (!did_action('login_init')) {
            return $errors;
        }

        // Only the "user not found" outcome is masked; an empty field or errors
        // added by other plugins (e.g. captcha) are still shown.
        $codes = $errors->get_error_codes();
        if (array_diff($codes, ['invalid_email', 'invalidcombo'])) {
            return $errors;
        }

        // Mirrors the success branch of the lostpassword case in wp-login.php.
        $redirect_to = !empty($_REQUEST['redirect_to']) ? $_REQUEST['redirect_to'] : 'wp-login.php?checkemail=confirm';
        wp_safe_redirect($redirect_to);
        exit;
    }
}
