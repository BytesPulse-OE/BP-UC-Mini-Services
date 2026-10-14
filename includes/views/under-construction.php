<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?php echo esc_html($site_name); ?> – Under Construction</title>
    <?php if ($favicon_url) : ?>
        <link rel="icon" type="image/png" href="<?php echo esc_url($favicon_url); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo esc_url(BP_UCMS_URL . 'assets/css/under-construction.css'); ?>?ver=<?php echo esc_attr(BP_UCMS_VERSION); ?>">
</head>
<body>
    <div class="aurora"></div>
    <canvas id="starfield"></canvas>

    <div class="controls">
        <button class="btn" id="langToggle">EN</button>
        <button class="btn" id="themeToggle">Light</button>
    </div>

    <div class="container">
        <?php if ($logo_url) : ?>
            <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($site_name); ?>" class="logo">
        <?php endif; ?>

        <h1 id="title"><?php echo esc_html($translations['el']['title']); ?></h1>
        <p id="subtitle"><?php echo esc_html($translations['el']['subtitle']); ?></p>

        <p class="contact" id="contactMsg">
            <?php echo esc_html($translations['el']['contact']); ?>
            <a href="mailto:<?php echo antispambot(esc_attr($contact_email)); ?>"><?php echo esc_html($contact_email); ?></a>
        </p>

        <?php if ($countdown_enabled) : ?>
            <div id="countdown"></div>
        <?php endif; ?>

        <div class="pulse-wrapper">
            <canvas id="pulseCanvas"></canvas>
        </div>
    </div>

    <footer><?php echo esc_html($settings['footer_text']); ?></footer>

    <script>
        window.BPUCMSData = <?php echo wp_json_encode([
            'translations' => $translations,
            'countdownEnabled' => $countdown_enabled,
            'countdownStart' => $countdown_start,
            'countdownEnd' => $countdown_end,
            'contactEmail' => $contact_email,
        ]); ?>;
    </script>
    <script src="<?php echo esc_url(BP_UCMS_URL . 'assets/js/under-construction.js'); ?>?ver=<?php echo esc_attr(BP_UCMS_VERSION); ?>"></script>
</body>
</html>
