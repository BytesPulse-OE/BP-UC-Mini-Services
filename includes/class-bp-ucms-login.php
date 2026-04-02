<?php
if (!defined('ABSPATH')) {
    exit;
}

final class BP_UCMS_Login {
    private BP_UCMS_Settings $settings;

    public function __construct(BP_UCMS_Settings $settings) {
        $this->settings = $settings;
    }

    public function init(): void {
        add_action('init', [$this, 'register_login_rewrite']);
        add_filter('query_vars', [$this, 'register_query_vars']);
        add_action('template_redirect', [$this, 'handle_custom_login_request'], 0);
        add_action('login_init', [$this, 'block_direct_wp_login_access']);
        add_filter('login_url', [$this, 'filter_login_url'], 10, 3);
        add_filter('site_url', [$this, 'filter_site_url_for_login'], 10, 4);
        add_filter('authenticate', [$this, 'capture_login_redirect_target'], 30, 3);
        add_filter('login_redirect', [$this, 'handle_login_redirect'], 999, 3);
        add_filter('logout_redirect', [$this, 'handle_logout_redirect'], 10, 3);
        add_action('login_enqueue_scripts', [$this, 'inject_login_logo_styles']);
        add_filter('login_headerurl', [$this, 'custom_login_logo_url']);
        add_filter('login_headertext', [$this, 'custom_login_logo_title']);
    }

    public function register_query_vars(array $vars): array {
        $vars[] = 'bp_ucms_login';
        return $vars;
    }

    public function register_login_rewrite(): void {
        $settings = $this->settings->get();
        $slug = $this->settings->sanitize_login_slug($settings['custom_login_slug'] ?? 'bp-login');
        add_rewrite_rule('^' . preg_quote($slug, '/') . '/?$', 'index.php?bp_ucms_login=1', 'top');
    }

    public function handle_custom_login_request(): void {
        if (!get_query_var('bp_ucms_login')) {
            return;
        }

        global $pagenow;
        $pagenow = 'wp-login.php';

        if (!defined('WP_LOGIN')) {
            define('WP_LOGIN', true);
        }

        $_GET['bp_ucms_from'] = 'custom-login';
        $_REQUEST['bp_ucms_from'] = 'custom-login';

        require_once ABSPATH . 'wp-login.php';
        exit;
    }

    public function block_direct_wp_login_access(): void {
        $settings = $this->settings->get();

        if (!(int) $settings['block_wp_login']) {
            return;
        }

        if (is_user_logged_in() && empty($_REQUEST['action'])) {
            return;
        }

        if (!empty($_REQUEST['action']) && in_array($_REQUEST['action'], ['logout', 'lostpassword', 'rp', 'resetpass', 'postpass', 'register', 'confirmaction', 'bp_ucms_2fa'], true)) {
            return;
        }

        if ($this->is_request_from_custom_login_slug()) {
            return;
        }

        $redirect_url = home_url('/');
        if (!empty($settings['blocked_login_redirect_page_id'])) {
            $page_url = get_permalink((int) $settings['blocked_login_redirect_page_id']);
            if ($page_url) {
                $redirect_url = $page_url;
            }
        }

        wp_safe_redirect($redirect_url);
        exit;
    }

    private function is_request_from_custom_login_slug(): bool {
        if (isset($_GET['bp_ucms_from']) && $_GET['bp_ucms_from'] === 'custom-login') {
            return true;
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
        $settings = $this->settings->get();
        $slug = '/' . $this->settings->sanitize_login_slug($settings['custom_login_slug']) . '/';

        return strpos(trailingslashit($request_uri), $slug) !== false;
    }

    public function filter_login_url(string $login_url, string $redirect, bool $force_reauth): string {
        $settings = $this->settings->get();
        $custom_url = home_url('/' . $this->settings->sanitize_login_slug($settings['custom_login_slug']) . '/');
        $args = ['bp_ucms_from' => 'custom-login'];

        if (!empty($redirect)) {
            $args['redirect_to'] = $redirect;
        }
        if ($force_reauth) {
            $args['reauth'] = '1';
        }

        return add_query_arg($args, $custom_url);
    }

    public function filter_site_url_for_login(string $url, string $path, ?string $scheme, $blog_id): string {
        if ($scheme !== 'login' && $scheme !== 'login_post') {
            return $url;
        }

        $settings = $this->settings->get();
        $custom_url = home_url('/' . $this->settings->sanitize_login_slug($settings['custom_login_slug']) . '/');

        $query_args = ['bp_ucms_from' => 'custom-login'];
        $parsed_url = wp_parse_url($url);

        if (!empty($parsed_url['query'])) {
            parse_str($parsed_url['query'], $existing_args);
            if (is_array($existing_args)) {
                $query_args = array_merge($existing_args, $query_args);
            }
        }

        return add_query_arg($query_args, $custom_url);
    }

    public function capture_login_redirect_target($user, string $username, string $password) {
        if (!empty($_REQUEST['redirect_to'])) {
            return $user;
        }

        if (!empty($_SERVER['HTTP_REFERER'])) {
            $referer = wp_unslash($_SERVER['HTTP_REFERER']);
            $login_url = wp_login_url();
            $custom_login_base = home_url('/' . $this->settings->sanitize_login_slug($this->settings->get()['custom_login_slug']) . '/');

            if (strpos($referer, 'wp-login.php') === false && strpos($referer, $login_url) === false && strpos($referer, $custom_login_base) === false) {
                $_REQUEST['redirect_to'] = esc_url_raw($referer);
            }
        }

        return $user;
    }

    public function handle_login_redirect(string $redirect_to, string $requested_redirect_to, $user): string {
        if (!($user instanceof WP_User)) {
            return $redirect_to;
        }

        $settings = $this->settings->get();
        $role = $this->resolve_primary_role($user);
        if (!$role) {
            return $redirect_to;
        }

        $mode = $settings['redirect_' . $role . '_mode'] ?? 'same';
        $page_id = !empty($settings['redirect_' . $role . '_page_id']) ? (int) $settings['redirect_' . $role . '_page_id'] : 0;

        if ($mode === 'same' && !empty($requested_redirect_to) && $this->is_safe_frontend_url($requested_redirect_to)) {
            return $requested_redirect_to;
        }

        if ($mode === 'same' && !empty($_REQUEST['redirect_to']) && $this->is_safe_frontend_url(wp_unslash($_REQUEST['redirect_to']))) {
            return esc_url_raw(wp_unslash($_REQUEST['redirect_to']));
        }

        if ($mode === 'page' && $page_id > 0) {
            $page_url = get_permalink($page_id);
            if ($page_url) {
                return $page_url;
            }
        }

        if ($mode === 'admin') {
            return admin_url();
        }

        return !empty($requested_redirect_to) ? $requested_redirect_to : home_url('/');
    }

    public function handle_logout_redirect(string $redirect_to, string $requested_redirect_to, $user): string {
        $settings = $this->settings->get();
        if (!empty($settings['logout_redirect_page_id'])) {
            $page_url = get_permalink((int) $settings['logout_redirect_page_id']);
            if ($page_url) {
                return $page_url;
            }
        }

        if (!empty($requested_redirect_to) && wp_validate_redirect($requested_redirect_to, false)) {
            return $requested_redirect_to;
        }

        return $redirect_to ?: home_url('/');
    }


    public function inject_login_logo_styles(): void {
        $settings = $this->settings->get();
        $logo_url = !empty($settings['logo_url']) ? esc_url($settings['logo_url']) : '';
        if (!$logo_url) {
            return;
        }

        echo '<style>.login h1 a{background-image:url(' . esc_url($logo_url) . ') !important;background-size:contain !important;background-position:center center !important;background-repeat:no-repeat !important;width:280px !important;height:90px !important;max-width:90%;}</style>';
    }

    public function custom_login_logo_url(string $url): string {
        return home_url('/');
    }

    public function custom_login_logo_title(string $title): string {
        return get_bloginfo('name');
    }

    private function resolve_primary_role(WP_User $user): ?string {
        foreach (array_keys($this->settings->supported_roles()) as $role) {
            if (in_array($role, (array) $user->roles, true)) {
                return $role;
            }
        }
        return null;
    }

    private function is_safe_frontend_url(string $url): bool {
        $url = esc_url_raw($url);
        if (empty($url)) {
            return false;
        }

        $home_host = wp_parse_url(home_url('/'), PHP_URL_HOST);
        $target_host = wp_parse_url($url, PHP_URL_HOST);
        if ($target_host && $home_host && strtolower($target_host) !== strtolower($home_host)) {
            return false;
        }

        return strpos($url, 'wp-admin') === false;
    }
}
