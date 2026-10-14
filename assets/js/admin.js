jQuery(function ($) {
    $('.bp-ucms-media-field').each(function () {
        const container = $(this);
        const input = container.find('.bp-ucms-media-url');
        const preview = container.find('.bp-ucms-media-preview');
        let frame;

        container.find('.bp-ucms-media-button').on('click', function (event) {
            event.preventDefault();

            if (frame) {
                frame.open();
                return;
            }

            frame = wp.media({
                title: 'Select media',
                button: { text: 'Use this file' },
                multiple: false
            });

            frame.on('select', function () {
                const attachment = frame.state().get('selection').first().toJSON();
                input.val(attachment.url);
                preview.html('<img src="' + attachment.url + '" alt="">');
            });

            frame.open();
        });

        container.find('.bp-ucms-media-clear').on('click', function (event) {
            event.preventDefault();
            input.val('');
            preview.html('');
        });
    });

    const smtpEncryption = $('select[name="smtp_encryption"]');
    const smtpPort = $('input[name="smtp_port"]');
    const defaultPorts = {
        none: '25',
        ssl: '465',
        tls: '587'
    };

    smtpEncryption.on('change', function () {
        const selected = $(this).val();
        if (defaultPorts[selected]) {
            smtpPort.val(defaultPorts[selected]);
        }
    });
});
