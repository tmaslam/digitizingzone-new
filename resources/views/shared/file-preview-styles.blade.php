        [hidden] { display: none !important; }

        body.preview-open {
            overflow: hidden;
        }

        .preview-overlay {
            position: fixed;
            inset: 0;
            background: var(--preview-overlay-bg, transparent);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
            z-index: 60;
        }

        .preview-overlay.open {
            opacity: 1;
            pointer-events: none;
        }

        .preview-modal {
            position: fixed;
            top: 106px;
            right: 24px;
            width: min(720px, calc(100vw - 64px));
            height: min(78vh, 780px);
            display: grid;
            grid-template-rows: auto minmax(0, 1fr);
            background: var(--preview-modal-bg, rgba(255,255,255,0.96));
            border: 1px solid var(--preview-border, rgba(255,255,255,0.7));
            border-radius: 26px;
            box-shadow: var(--preview-shadow, 0 30px 80px rgba(20, 33, 49, 0.28));
            backdrop-filter: blur(16px);
            opacity: 0;
            pointer-events: none;
            transform: translateY(8px) scale(0.99);
            transition: opacity 0.2s ease, transform 0.2s ease;
            z-index: 61;
            overflow: hidden;
        }

        .preview-modal.open {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(0) scale(1);
        }

        .preview-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 18px;
            border-bottom: 1px solid var(--preview-header-border, rgba(24, 34, 45, 0.12));
            background: var(--preview-header-bg, rgba(255,255,255,0.9));
        }

        .preview-title {
            margin: 0;
            font-size: 1.02rem;
            letter-spacing: -0.02em;
        }

        .preview-meta {
            margin: 4px 0 0;
            color: var(--preview-meta-ink, #64707d);
            font-size: 0.88rem;
            line-height: 1.4;
            font-weight: 500;
        }

        .preview-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }

        .preview-zoom {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px;
            border-radius: 999px;
            border: 1px solid var(--preview-toolbar-border, rgba(24, 34, 45, 0.12));
            background: var(--preview-toolbar-bg, rgba(255,255,255,0.92));
        }

        .preview-zoom-value {
            min-width: 58px;
            text-align: center;
            font-size: 0.85rem;
            font-weight: 800;
            color: var(--preview-toolbar-ink, inherit);
        }

        .preview-close,
        .preview-zoom-btn {
            min-height: 38px;
            padding: 9px 13px;
            border: 1px solid var(--preview-button-border, rgba(24, 34, 45, 0.12));
            border-radius: 999px;
            background: var(--preview-button-bg, rgba(24, 34, 45, 0.08)) !important;
            color: var(--preview-button-ink, inherit) !important;
            font: inherit;
            font-weight: 800;
            cursor: pointer;
            box-shadow: none !important;
        }

        .preview-close:hover,
        .preview-zoom-btn:hover {
            background: var(--preview-button-bg-hover, rgba(24, 34, 45, 0.14)) !important;
        }

        .preview-body {
            min-height: 0;
            overflow: auto;
            padding: 16px;
            background: var(--preview-body-bg, linear-gradient(180deg, rgba(248, 250, 252, 0.94), rgba(241, 244, 247, 0.9)));
        }

        .preview-frame,
        .preview-image,
        .preview-text {
            width: 100%;
            border-radius: 18px;
            border: 1px solid var(--preview-frame-border, rgba(24, 34, 45, 0.12));
            background: #fff;
        }

        .preview-frame {
            min-height: min(58vh, 620px);
            display: block;
        }

        .preview-image-wrap {
            display: flex;
            justify-content: center;
            min-height: min(58vh, 620px);
            padding: 10px;
            border-radius: 18px;
            border: 1px solid var(--preview-frame-border, rgba(24, 34, 45, 0.12));
            background: var(--preview-image-wrap-bg, rgba(255,255,255,0.88));
            overflow: auto;
        }

        .preview-image-stage {
            min-width: 100%;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 6px;
        }

        .preview-image {
            width: auto;
            max-width: 100%;
            height: auto;
            border: 0;
            background: transparent;
            transform-origin: center top;
            transition: transform 0.16s ease;
            cursor: zoom-in;
        }

        .preview-text {
            margin: 0;
            padding: 18px;
            overflow: auto;
            color: var(--preview-text-ink, #eff5fb);
            background: var(--preview-text-bg, #0f1720);
            font: 0.95rem/1.6 "SFMono-Regular", Consolas, monospace;
            white-space: pre-wrap;
            word-break: break-word;
        }

        @media (max-width: 720px) {
            .preview-modal {
                inset: 14px;
                width: auto;
                height: auto;
                top: auto;
                right: auto;
                border-radius: 20px;
                transform: translateY(8px) scale(0.99);
            }

            .preview-modal.open {
                transform: translateY(0) scale(1);
            }

            .preview-header {
                align-items: flex-start;
            }

            .preview-actions {
                width: 100%;
                justify-content: flex-start;
            }

            .preview-zoom {
                order: 2;
            }

            .preview-frame,
            .preview-image-wrap {
                min-height: 52vh;
            }
        }
