<?php
if (!defined('ABSPATH')) {
    exit;
}

final class BP_UCMS_Under_Construction {
    private BP_UCMS_Settings $settings;

    public function __construct(BP_UCMS_Settings $settings) {
        $this->settings = $settings;
    }

    public function init(): void {
        add_action('template_redirect', [$this, 'handle_under_construction_mode'], 1);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets(): void {
        if (!$this->is_under_construction_request()) {
            return;
        }

        wp_enqueue_script('gsap', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js', [], '3.12.5', true);
    }

    private function is_under_construction_request(): bool {
        $settings = $this->settings->get();

        if (is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return false;
        }
        if (!(int) $settings['uc_enabled']) {
            return false;
        }
        if ((int) $settings['uc_allow_logged_in'] && is_user_logged_in()) {
            return false;
        }
        if ($this->is_login_context()) {
            return false;
        }

        return true;
    }

    private function is_login_context(): bool {
        global $pagenow;
        if (get_query_var('bp_ucms_login')) {
            return true;
        }
        return isset($pagenow) && $pagenow === 'wp-login.php';
    }

    public function handle_under_construction_mode(): void {
        if (!$this->is_under_construction_request()) {
            return;
        }

        status_header(503);
        nocache_headers();
        $this->render_page();
        exit;
    }

    private function render_page(): void {
        $settings = $this->settings->get();
        $site_name = get_bloginfo('name');
        $logo_url = !empty($settings['logo_url']) ? $settings['logo_url'] : '';
        $favicon_url = !empty($settings['favicon_url']) ? $settings['favicon_url'] : '';
        $contact_email = !empty($settings['contact_email']) ? $settings['contact_email'] : get_option('admin_email');
        $countdown_enabled = (int) $settings['countdown_enabled'] === 1;
        $countdown_start = !empty($settings['countdown_start']) ? $settings['countdown_start'] : '';
        $countdown_end = !empty($settings['countdown_end']) ? $settings['countdown_end'] : '';

        $translations = [
            'el' => [
                'title' => $settings['uc_title_el'],
                'subtitle' => $settings['uc_subtitle_el'],
                'contact' => $settings['uc_contact_el'],
                'days' => 'ημέρες',
                'hours' => 'ώρες',
                'minutes' => 'λεπτά',
                'seconds' => 'δευτερόλεπτα',
                'done' => 'Έφτασε η στιγμή!',
                'starts_in' => 'Ξεκινά σε:',
            ],
            'en' => [
                'title' => $settings['uc_title_en'],
                'subtitle' => $settings['uc_subtitle_en'],
                'contact' => $settings['uc_contact_en'],
                'days' => 'days',
                'hours' => 'hours',
                'minutes' => 'minutes',
                'seconds' => 'seconds',
                'done' => 'The time has come!',
                'starts_in' => 'Starts in:',
            ],
        ];
        include BP_UCMS_PATH . 'includes/views/under-construction.php';
    }
}
