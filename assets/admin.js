// Admin Background Image – Main script
document.addEventListener('DOMContentLoaded', function () {

    const uploadButton = document.getElementById('pkabg_upload_button');
    const removeButton = document.getElementById('pkabg_remove_button');
    const imageInput   = document.getElementById('pkabg_image');

    const overlayInput = document.getElementById('pkabg_overlay');
    const blurInput    = document.getElementById('pkabg_blur');
    const colorInput   = document.getElementById('pkabg_color');

    // Current selected image URL (from PHP)
    let currentImageUrl = pkabgData.imageUrl || '';

    /**
     * Toggle visibility of dependent controls (overlay, blur)
     */
    function updateVisibility() {
        const hasImage = !!currentImageUrl;

        document.querySelectorAll('.pkabg-dependent').forEach(el => {
            el.style.display = hasImage ? '' : 'none';
        });
    }

    /**
     * Persist settings via AJAX
     */
    function saveSettings() {
        fetch(pkabgData.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'pkabg_save_settings',
                nonce: pkabgData.nonce,
                image_id: imageInput.value || 0,
                overlay: overlayInput.value,
                blur: blurInput.value,
                color: colorInput.value
            })
        });
    }

    /**
     * Convert HEX to RGBA string
     */
    function hexToRGBA(hex, alpha) {
        hex = hex.replace('#', '');

        let r, g, b;

        if (hex.length === 3) {
            r = parseInt(hex[0] + hex[0], 16);
            g = parseInt(hex[1] + hex[1], 16);
            b = parseInt(hex[2] + hex[2], 16);
        } else {
            r = parseInt(hex.substring(0, 2), 16);
            g = parseInt(hex.substring(2, 4), 16);
            b = parseInt(hex.substring(4, 6), 16);
        }

        return `rgba(${r},${g},${b},${alpha})`;
    }

    /**
     * Apply background + overlay styles dynamically
     */
    function applyBackground(url, overlay, blur) {

        let style = document.getElementById('pkabg-live-style');

        if (!url) {
            if (style) style.remove();
            return;
        }

        const color = colorInput ? colorInput.value : '#000000';
        const rgba  = hexToRGBA(color, overlay);

        if (!style) {
            style = document.createElement('style');
            style.id = 'pkabg-live-style';
            document.head.appendChild(style);
        }

        style.innerHTML = `
            body.wp-admin {
                position: relative;
                z-index: 0;
            }

            body.wp-admin::before {
                content: "";
                position: fixed;
                inset: 0;
                background: url("${url}") no-repeat center center fixed;
                background-size: cover;
                filter: blur(${blur}px);
                z-index: -2;
            }

            body.wp-admin::after {
                content: "";
                position: fixed;
                inset: 0;
                background: ${rgba};
                z-index: -1;
                pointer-events: none;
            }
        `;
    }

    /**
     * Update UI + optionally persist
     */
    function updateLive(save = true) {
        applyBackground(currentImageUrl, overlayInput.value, blurInput.value);

        if (save) {
            saveSettings();
        }
    }

    /**
     * Initialize range sliders with live output
     */
    function initRanges() {
        document.querySelectorAll('.pkabg-range-group').forEach(group => {
            const input = group.querySelector('input');
            const output = group.querySelector('output');

            if (!input || !output) return;

            output.value = input.value;

            input.addEventListener('input', function () {
                output.value = input.value;
                updateLive(true);
            });
        });
    }

    /**
     * Media uploader
     */
    if (uploadButton) {
        uploadButton.addEventListener('click', function (e) {
            e.preventDefault();

            const frame = wp.media({
                title: 'Select Image',
                button: { text: 'Use this image' },
                multiple: false
            });

            frame.on('select', function () {
                const attachment = frame.state().get('selection').first().toJSON();

                imageInput.value = attachment.id;
                currentImageUrl  = attachment.url;

                if (removeButton) {
                    removeButton.style.display = 'inline-block';
                }

                updateVisibility();
                updateLive(true);
            });

            frame.open();
        });
    }

    /**
     * Remove image and reset values
     */
    if (removeButton) {
        removeButton.addEventListener('click', function (e) {
            e.preventDefault();

            imageInput.value = '';
            currentImageUrl  = '';

            overlayInput.value = 0;
            blurInput.value    = 0;

            if (colorInput) {
                colorInput.value = '#000000';
            }

            document.querySelectorAll('.pkabg-range-group output').forEach(o => {
                o.value = 0;
            });

            removeButton.style.display = 'none';

            updateVisibility();
            updateLive(true);
        });
    }

    /**
     * Color picker live update
     */
    if (colorInput) {
        colorInput.addEventListener('input', function () {
            updateLive(true);
        });
    }

    initRanges();
    updateVisibility();
    updateLive(false);

});