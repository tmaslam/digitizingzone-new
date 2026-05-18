<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.querySelector('[data-preview-modal]');
    var overlay = document.querySelector('[data-preview-overlay]');
    var body = document.querySelector('[data-preview-modal-body]');
    var title = document.querySelector('[data-preview-modal-title]');
    var meta = document.querySelector('[data-preview-modal-meta]');
    var download = document.querySelector('[data-preview-modal-download]');
    var zoomControls = document.querySelector('[data-preview-modal-zoom-controls]');
    var zoomValue = document.querySelector('[data-preview-modal-zoom-value]');
    var zoomIn = document.querySelector('[data-preview-modal-zoom-in]');
    var zoomOut = document.querySelector('[data-preview-modal-zoom-out]');
    var zoomFit = document.querySelector('[data-preview-modal-zoom-fit]');

    if (!modal || !overlay || !body || !title || !meta || !download || !zoomControls || !zoomValue || !zoomIn || !zoomOut || !zoomFit) {
        return;
    }

    var imageState = {
        node: null,
        scale: 1,
    };

    var updateZoom = function () {
        if (!imageState.node) {
            zoomValue.textContent = '100%';
            return;
        }

        imageState.node.style.transform = 'scale(' + imageState.scale + ')';
        zoomValue.textContent = Math.round(imageState.scale * 100) + '%';
    };

    var setZoomVisible = function (visible) {
        zoomControls.hidden = !visible;
        if (!visible) {
            imageState.node = null;
            imageState.scale = 1;
            zoomValue.textContent = '100%';
        }
    };

    var resetZoom = function () {
        imageState.scale = 1;
        updateZoom();
    };

    var setZoom = function (nextScale) {
        if (!imageState.node) {
            return;
        }

        imageState.scale = Math.min(4, Math.max(0.5, nextScale));
        updateZoom();
    };

    var closePreview = function () {
        body.innerHTML = '';
        title.textContent = 'File Preview';
        meta.textContent = '';
        meta.hidden = true;
        download.hidden = true;
        download.removeAttribute('href');
        setZoomVisible(false);
        modal.hidden = true;
        overlay.hidden = true;
        modal.classList.remove('open');
        overlay.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('preview-open');
    };

    var openPreview = function (link) {
        var previewUrl = link.getAttribute('data-preview-url');
        var previewKind = link.getAttribute('data-preview-kind');
        var previewTitle = link.getAttribute('data-preview-title') || 'File Preview';
        var downloadUrl = link.getAttribute('data-preview-download');
        var previewFallback = link.getAttribute('data-preview-fallback') || link.getAttribute('href');

        if (!previewUrl || !previewKind) {
            window.open(previewFallback, '_blank', 'noopener');
            return;
        }

        title.textContent = 'File Preview';
        meta.textContent = previewTitle;
        meta.hidden = previewTitle === '';
        body.innerHTML = '';
        setZoomVisible(false);

        if (downloadUrl) {
            download.href = downloadUrl;
            download.hidden = false;
        } else {
            download.hidden = true;
            download.removeAttribute('href');
        }

        if (previewKind === 'image') {
            var wrap = document.createElement('div');
            wrap.className = 'preview-image-wrap';

            var stage = document.createElement('div');
            stage.className = 'preview-image-stage';

            var image = document.createElement('img');
            image.className = 'preview-image';
            image.src = previewUrl;
            image.alt = previewTitle;
            image.addEventListener('click', function () {
                if (imageState.scale === 1) {
                    setZoom(1.6);
                    return;
                }

                resetZoom();
            });

            stage.appendChild(image);
            wrap.appendChild(stage);
            body.appendChild(wrap);

            imageState.node = image;
            imageState.scale = 1;
            setZoomVisible(true);
            updateZoom();
        } else if (previewKind === 'pdf') {
            var frame = document.createElement('iframe');
            frame.className = 'preview-frame';
            frame.src = previewUrl;
            frame.title = previewTitle;
            body.appendChild(frame);
        } else {
            var text = document.createElement('pre');
            text.className = 'preview-text';
            text.textContent = 'Loading preview...';
            body.appendChild(text);

            fetch(previewUrl, { credentials: 'same-origin' })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Preview failed');
                    }

                    return response.text();
                })
                .then(function (content) {
                    text.textContent = content || 'No preview content available.';
                })
                .catch(function () {
                    text.textContent = 'Unable to load preview.';
                });
        }

        modal.hidden = false;
        overlay.hidden = false;
        document.body.classList.add('preview-open');

        window.requestAnimationFrame(function () {
            modal.classList.add('open');
            overlay.classList.add('open');
        });

        modal.setAttribute('aria-hidden', 'false');
    };

    zoomIn.addEventListener('click', function () {
        setZoom(imageState.scale + 0.2);
    });

    zoomOut.addEventListener('click', function () {
        setZoom(imageState.scale - 0.2);
    });

    zoomFit.addEventListener('click', function () {
        resetZoom();
    });

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-preview-link]');
        if (trigger) {
            event.preventDefault();
            event.stopPropagation();
            openPreview(trigger);
            if (typeof trigger.blur === 'function') {
                trigger.blur();
            }
            return;
        }

        if (event.target.closest('[data-preview-modal-close]')) {
            closePreview();
            return;
        }

        if (modal.classList.contains('open') && !modal.contains(event.target)) {
            closePreview();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('open')) {
            closePreview();
        }
    });
});
</script>
