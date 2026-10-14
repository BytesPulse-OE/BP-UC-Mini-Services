(function () {
    function renderSvg(target, text) {
        if (!window.BPUCMSQRCode || !text) {
            return;
        }

        var qr = new window.BPUCMSQRCode(0, 1);
        qr.addData(text);
        qr.make();

        var count = qr.getModuleCount();
        var size = 220;
        var cell = Math.max(4, Math.floor(size / count));
        var actual = cell * count;
        var svg = [
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + actual + ' ' + actual + '" width="' + actual + '" height="' + actual + '" shape-rendering="crispEdges">',
            '<rect width="100%" height="100%" fill="#fff"/>'
        ];

        for (var r = 0; r < count; r++) {
            for (var c = 0; c < count; c++) {
                if (qr.isDark(r, c)) {
                    svg.push('<rect x="' + (c * cell) + '" y="' + (r * cell) + '" width="' + cell + '" height="' + cell + '" fill="#000"/>');
                }
            }
        }

        svg.push('</svg>');
        target.innerHTML = svg.join('');
    }

    function getCodesText(selector, fallbackText) {
        if (fallbackText) {
            return String(fallbackText).trim();
        }

        var container = selector ? document.querySelector(selector) : null;
        if (!container) {
            return '';
        }

        return Array.prototype.map.call(container.querySelectorAll('code'), function (node) {
            return node.textContent.trim();
        }).filter(Boolean).join('\n');
    }

    function fallbackCopyText(text) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', 'readonly');
        textarea.style.position = 'fixed';
        textarea.style.top = '-9999px';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();
        textarea.setSelectionRange(0, textarea.value.length);
        var ok = false;
        try {
            ok = document.execCommand('copy');
        } catch (e) {
            ok = false;
        }
        document.body.removeChild(textarea);
        return ok;
    }

    function flashButtonText(button, text) {
        if (!button) {
            return;
        }

        var original = button.dataset.originalText || button.textContent;
        button.textContent = text;
        window.setTimeout(function () {
            button.textContent = original;
        }, 1800);
    }

    function copyRecoveryCodes(button) {
        var text = getCodesText(button.getAttribute('data-target') || '', button.getAttribute('data-plain-text') || '');
        if (!text) {
            window.alert((window.bpUcms2fa && window.bpUcms2fa.copyFailedMessage) || 'Copy failed.');
            return;
        }

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function () {
                flashButtonText(button, (window.bpUcms2fa && window.bpUcms2fa.copiedMessage) || 'Copied');
            }).catch(function () {
                if (fallbackCopyText(text)) {
                    flashButtonText(button, (window.bpUcms2fa && window.bpUcms2fa.copiedMessage) || 'Copied');
                    return;
                }
                window.prompt((window.bpUcms2fa && window.bpUcms2fa.copyFailedMessage) || 'Copy the recovery codes below:', text);
            });
            return;
        }

        if (fallbackCopyText(text)) {
            flashButtonText(button, (window.bpUcms2fa && window.bpUcms2fa.copiedMessage) || 'Copied');
            return;
        }

        window.prompt((window.bpUcms2fa && window.bpUcms2fa.copyFailedMessage) || 'Copy the recovery codes below:', text);
    }

    function downloadText(filename, text) {
        try {
            var blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
            var link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = filename;
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.setTimeout(function () {
                URL.revokeObjectURL(link.href);
            }, 1000);
            return true;
        } catch (e) {
            return false;
        }
    }

    function setBusy(button, isBusy) {
        if (!button) {
            return;
        }

        if (isBusy) {
            if (!button.dataset.originalText) {
                button.dataset.originalText = button.textContent;
            }
            button.textContent = (window.bpUcms2fa && window.bpUcms2fa.busyMessage) || 'Working...';
            button.disabled = true;
            return;
        }

        if (button.dataset.originalText) {
            button.textContent = button.dataset.originalText;
        }
        button.disabled = false;
    }

    function postAction(action, userId, nonce, extraData, button) {
        var formData = new FormData();
        formData.append('action', action);
        formData.append('user_id', String(userId));
        formData.append('nonce', nonce);

        Object.keys(extraData || {}).forEach(function (key) {
            formData.append(key, extraData[key]);
        });

        setBusy(button, true);

        return fetch(window.bpUcms2fa.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        }).then(function (response) {
            return response.json();
        }).then(function (payload) {
            setBusy(button, false);

            if (!payload || !payload.success || !payload.data || !payload.data.redirect) {
                var message = payload && payload.data && payload.data.message ? payload.data.message : window.bpUcms2fa.requestFailedMessage;
                window.alert(message);
                return;
            }

            window.location.href = payload.data.redirect;
        }).catch(function () {
            setBusy(button, false);
            window.alert(window.bpUcms2fa.requestFailedMessage);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.bp-ucms-2fa-qr').forEach(function (el) {
            renderSvg(el, el.getAttribute('data-otpauth') || '');
        });

        document.addEventListener('click', function (event) {
            var copyButton = event.target.closest('.bp-ucms-copy-recovery');
            if (copyButton) {
                event.preventDefault();
                copyRecoveryCodes(copyButton);
                return;
            }

            var downloadButton = event.target.closest('.bp-ucms-download-recovery');
            if (downloadButton) {
                event.preventDefault();
                var text = getCodesText(downloadButton.getAttribute('data-target') || '', downloadButton.getAttribute('data-plain-text') || '');
                if (!text) {
                    window.alert((window.bpUcms2fa && window.bpUcms2fa.downloadFailedMessage) || 'The recovery codes could not be downloaded automatically.');
                    return;
                }
                if (!downloadText(downloadButton.getAttribute('data-filename') || 'recovery-codes.txt', text + '\n')) {
                    window.prompt((window.bpUcms2fa && window.bpUcms2fa.downloadFailedMessage) || 'Copy the recovery codes below:', text);
                }
                return;
            }

            var enableButton = event.target.closest('.bp-ucms-enable-2fa-submit');
            if (enableButton) {
                event.preventDefault();
                var sourceSelector = enableButton.getAttribute('data-code-source') || '';
                var source = sourceSelector ? document.querySelector(sourceSelector) : null;
                var value = source ? String(source.value || '').trim() : '';
                var userId = parseInt(enableButton.getAttribute('data-user-id') || '0', 10);

                if (!/^\d{6}$/.test(value)) {
                    window.alert(window.bpUcms2fa.invalidCodeMessage);
                    if (source) {
                        source.focus();
                    }
                    return;
                }

                postAction('bp_ucms_enable_2fa', userId, window.bpUcms2fa.enableNonce, {
                    bp_ucms_2fa_code: value
                }, enableButton);
                return;
            }

            var disableButton = event.target.closest('.bp-ucms-disable-2fa');
            if (disableButton) {
                event.preventDefault();
                var disableUserId = parseInt(disableButton.getAttribute('data-user-id') || '0', 10);
                if (!window.confirm(window.bpUcms2fa.disableConfirmMessage)) {
                    return;
                }
                postAction('bp_ucms_disable_2fa', disableUserId, window.bpUcms2fa.disableNonce, {}, disableButton);
                return;
            }

            var regenButton = event.target.closest('.bp-ucms-regenerate-2fa');
            if (regenButton) {
                event.preventDefault();
                var regenUserId = parseInt(regenButton.getAttribute('data-user-id') || '0', 10);
                postAction('bp_ucms_regenerate_2fa_recovery', regenUserId, window.bpUcms2fa.regenerateNonce, {}, regenButton);
            }
        });
    });
})();
