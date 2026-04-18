<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once BP_UCMS_PATH . 'includes/class-bp-ucms-settings.php';
require_once BP_UCMS_PATH . 'includes/class-bp-ucms-admin.php';
require_once BP_UCMS_PATH . 'includes/class-bp-ucms-login.php';
require_once BP_UCMS_PATH . 'includes/class-bp-ucms-under-construction.php';
require_once BP_UCMS_PATH . 'includes/class-bp-ucms-smtp.php';
require_once BP_UCMS_PATH . 'includes/class-bp-ucms-2fa.php';

final class BP_UCMS_Plugin {
    private static ?BP_UCMS_Plugin $instance = null;

    public BP_UCMS_Settings $settings;
    public BP_UCMS_Admin $admin;
    public BP_UCMS_Login $login;
    public BP_UCMS_Under_Construction $under_construction;
    public BP_UCMS_SMTP $smtp;
    public BP_UCMS_Two_FA $twofa;

    public static function instance(): BP_UCMS_Plugin {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate(): void {
        $settings = new BP_UCMS_Settings();
        $settings->ensure_defaults();

        $login = new BP_UCMS_Login($settings);
        $login->register_login_rewrite();
        BP_UCMS_SMTP::activate();

        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        BP_UCMS_SMTP::deactivate();
        flush_rewrite_rules();
    }

    private function __construct() {
        $this->settings = new BP_UCMS_Settings();
        $this->admin = new BP_UCMS_Admin($this->settings);
        $this->login = new BP_UCMS_Login($this->settings);
        $this->under_construction = new BP_UCMS_Under_Construction($this->settings);
        $this->smtp = new BP_UCMS_SMTP($this->settings);
        $this->twofa = new BP_UCMS_Two_FA($this->settings);

        add_action('plugins_loaded', [$this, 'boot']);
    }

    public function boot(): void {
        $this->settings->ensure_defaults();
        $this->admin->init();
        $this->login->init();
        $this->under_construction->init();
        $this->smtp->init();
        $this->twofa->init();
    }
}
