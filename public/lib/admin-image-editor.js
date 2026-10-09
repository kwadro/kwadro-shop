(function () {
    'use strict';

    const cfg = window.ImageEditorConfig || {};
    const root = document.getElementById('image-editor');
    if (!root || !cfg.defaults) {
        return;
    }

    const i18n = cfg.i18n || {};
    const aspects = cfg.aspects || {};
    const fonts = Array.isArray(cfg.fonts) && cfg.fonts.length
        ? cfg.fonts
        : [
              { id: 'Montserrat', stack: 'Montserrat, Arial, sans-serif' },
              { id: 'Roboto', stack: 'Roboto, Arial, sans-serif' },
              { id: 'Playfair Display', stack: '"Playfair Display", Georgia, serif' },
              { id: 'Oswald', stack: 'Oswald, Arial Narrow, sans-serif' },
              { id: 'Caveat', stack: 'Caveat, "Comic Sans MS", cursive' },
          ];
    const defaultFontId = fonts[0].id;
    const textSizes = Array.isArray(cfg.textSizes) && cfg.textSizes.length
        ? cfg.textSizes
        : [
              { id: 'xxxs', ratio: 0.01125 },
              { id: 'xxs', ratio: 0.018 },
              { id: 'xs', ratio: 0.028 },
              { id: 's', ratio: 0.04 },
              { id: 'm', ratio: 0.055 },
              { id: 'l', ratio: 0.075 },
              { id: 'xl', ratio: 0.1 },
          ];
    const defaultTextSizeId = 'm';
    const fontWeights = Array.isArray(cfg.fontWeights) && cfg.fontWeights.length
        ? cfg.fontWeights.map(function (w) {
              return String(w.id);
          })
        : ['300', '400', '500', '600', '700', '800', '900'];
    const defaultFontWeight = '700';
    const canvas = document.getElementById('image-editor-canvas');
    const ctx = canvas.getContext('2d');
    const stage = root.querySelector('[data-ie-stage]');
    const stageWrap = root.querySelector('[data-ie-stage-wrap]');
    const zoomSizer = root.querySelector('[data-ie-zoom-sizer]');
    const viewport = root.querySelector('[data-ie-viewport]');
    const overlay = root.querySelector('[data-ie-overlay]');
    const cropEl = root.querySelector('[data-ie-crop]');
    const layerBoxesEl = root.querySelector('[data-ie-layer-boxes]');
    const statusEl = root.querySelector('[data-ie-status]');
    const sizeBadge = root.querySelector('[data-ie-size-badge]');
    const savedEl = root.querySelector('[data-ie-saved]');
    const cropHint = root.querySelector('[data-ie-crop-hint]');
    const zoomInBtn = root.querySelector('[data-ie-zoom-in]');
    const zoomOutBtn = root.querySelector('[data-ie-zoom-out]');
    const zoomResetBtn = root.querySelector('[data-ie-zoom-reset]');
    const zoomLabelEl = root.querySelector('[data-ie-zoom-label]');
    const regionEl = root.querySelector('[data-ie-region]');
    const regionToggleBtn = root.querySelector('[data-ie-region-toggle]');
    const regionSaveBtn = root.querySelector('[data-ie-region-save]');
    const regionCancelBtn = root.querySelector('[data-ie-region-cancel]');
    const regionHintEl = root.querySelector('[data-ie-region-hint]');
    const regionExportEl = root.querySelector('[data-ie-region-export]');
    const regionWidthEl = root.querySelector('[data-ie-region-width]');
    const regionHeightEl = root.querySelector('[data-ie-region-height]');
    const regionNameEl = root.querySelector('[data-ie-region-name]');
    const canvasWidthEl = root.querySelector('[data-ie-canvas-width]');
    const canvasHeightEl = root.querySelector('[data-ie-canvas-height]');
    const ZOOM_MIN = 0.5;
    const ZOOM_MAX = 4;
    const ZOOM_STEP = 0.25;
    const ZOOM_DEFAULT = 1;
    let previewZoom = ZOOM_DEFAULT;
    let regionSelectMode = false;
    let regionBox = null; // normalized {x,y,w,h} in canvas space
    const textListEl = root.querySelector('[data-ie-text-list]');
    const overlayListEl = root.querySelector('[data-ie-overlay-list]');
    const lineListEl = root.querySelector('[data-ie-line-list]');
    const frameListEl = root.querySelector('[data-ie-frame-list]');
    const textForm = root.querySelector('[data-ie-text-form]');
    const overlayForm = root.querySelector('[data-ie-overlay-form]');
    const lineForm = root.querySelector('[data-ie-line-form]');
    const frameForm = root.querySelector('[data-ie-frame-form]');

    const state = normalizeState(JSON.parse(JSON.stringify(cfg.defaults)));
    let step = 1;
    let bgImage = null;
    const overlayImages = {};
    let currentFilename = null;
    let sourceUploadBaseName = 'image';
    let regionSourceWidth = 0;
    let regionSourceHeight = 0;
    let uid = 1;
    let display = {
        drawW: 0,
        drawH: 0,
        sourceScale: 1,
        sourceOx: 0,
        sourceOy: 0,
        cssScaleX: 1,
        cssScaleY: 1,
    };
    let drag = null;

    function nextId(prefix) {
        uid += 1;
        return prefix + '-' + Date.now().toString(36) + '-' + uid;
    }

    function normalizeState(raw) {
        const s = Object.assign({}, cfg.defaults, raw || {});
        s.texts = Array.isArray(s.texts) ? s.texts : [];
        s.overlays = Array.isArray(s.overlays) ? s.overlays : [];
        s.lines = Array.isArray(s.lines) ? s.lines : [];
        s.frames = Array.isArray(s.frames) ? s.frames : [];
        const legacyWeight = normalizeFontWeight(s.fontWeight || defaultFontWeight);

        // Migrate legacy single-text content.
        if ((!s.texts || s.texts.length === 0) && raw && (raw.text || raw.textBox)) {
            s.texts = [
                {
                    id: nextId('text'),
                    text: raw.text || '',
                    color: raw.textColor || '#ffffff',
                    align: raw.textAlign || 'center',
                    font: normalizeFontId(raw.font || raw.fontFamily || defaultFontId),
                    size: normalizeTextSizeId(raw.size || defaultTextSizeId),
                    weight: normalizeFontWeight(raw.weight || raw.fontWeight || legacyWeight),
                    box: raw.textBox || { x: 0.1, y: 0.35, w: 0.8, h: 0.25 },
                },
            ];
        }

        s.texts = s.texts.map(function (item) {
            return {
                id: item.id || nextId('text'),
                text: item.text || '',
                color: item.color || '#ffffff',
                align: item.align || 'center',
                font: normalizeFontId(item.font || item.fontFamily || defaultFontId),
                size: normalizeTextSizeId(item.size || defaultTextSizeId),
                weight: normalizeFontWeight(item.weight || item.fontWeight || legacyWeight),
                box: normalizeBox(item.box || { x: 0.1, y: 0.35, w: 0.8, h: 0.2 }),
            };
        });
        s.overlays = s.overlays.map(function (item) {
            const aspect = Number(item.imageAspect);
            return {
                id: item.id || nextId('overlay'),
                url: item.url || '',
                opacity: typeof item.opacity === 'number' ? clamp(item.opacity, 0, 1) : 1,
                imageAspect: Number.isFinite(aspect) && aspect > 0 ? aspect : null,
                box: normalizeBox(item.box || { x: 0.2, y: 0.2, w: 0.4, h: 0.3 }),
            };
        });
        s.lines = s.lines.map(function (item) {
            return normalizeLine(item);
        });
        s.frames = s.frames.map(function (item) {
            return normalizeFrame(item);
        });
        s.selectedTextId = s.selectedTextId || null;
        s.selectedOverlayId = s.selectedOverlayId || null;
        s.selectedLineId = s.selectedLineId || null;
        s.selectedFrameId = s.selectedFrameId || null;
        s.fontWeight = legacyWeight;
        return s;
    }

    function normalizeFontWeight(value) {
        const id = String(value || '').trim();
        return fontWeights.indexOf(id) >= 0 ? id : defaultFontWeight;
    }

    function normalizeLine(item) {
        const src = item || {};
        function coord(value, fallback) {
            const n = Number(value);
            return Number.isFinite(n) ? clamp(n, 0, 1) : fallback;
        }
        return {
            id: src.id || nextId('line'),
            x1: coord(src.x1, 0.1),
            y1: coord(src.y1, 0.5),
            x2: coord(src.x2, 0.9),
            y2: coord(src.y2, 0.5),
            color: src.color || '#d2d6dc',
            width: clamp(Math.round(Number(src.width) || 2), 1, 80),
        };
    }

    function numOr(value, fallback) {
        const n = Number(value);
        return Number.isFinite(n) ? n : fallback;
    }

    /** Normalize #RGB / #RRGGBB / RRGGBB to uppercase #RRGGBB, or null if invalid. */
    function normalizeHex(value) {
        let raw = String(value || '').trim();
        if (!raw) {
            return null;
        }
        if (raw.charAt(0) !== '#') {
            raw = '#' + raw;
        }
        const short = /^#([0-9a-fA-F]{3})$/.exec(raw);
        if (short) {
            const s = short[1];
            return ('#' + s[0] + s[0] + s[1] + s[1] + s[2] + s[2]).toUpperCase();
        }
        const full = /^#([0-9a-fA-F]{6})$/.exec(raw);
        if (full) {
            return ('#' + full[1]).toUpperCase();
        }
        return null;
    }

    function setColorInputs(pickerSel, hexSel, value) {
        const hex = normalizeHex(value) || '#000000';
        const picker = root.querySelector(pickerSel);
        const hexInput = root.querySelector(hexSel);
        if (picker) {
            picker.value = hex.toLowerCase();
        }
        if (hexInput && document.activeElement !== hexInput) {
            hexInput.value = hex;
        }
    }

    function bindColorPair(pickerSel, hexSel, onChange) {
        const picker = root.querySelector(pickerSel);
        const hexInput = root.querySelector(hexSel);
        if (!picker && !hexInput) {
            return;
        }
        function apply(raw, source) {
            const hex = normalizeHex(raw);
            if (!hex) {
                if (source === 'hex' && hexInput) {
                    hexInput.classList.add('is-invalid');
                }
                return;
            }
            if (hexInput) {
                hexInput.classList.remove('is-invalid');
            }
            setColorInputs(pickerSel, hexSel, hex);
            onChange(hex);
        }
        if (picker) {
            picker.addEventListener('input', function (e) {
                apply(e.target.value, 'picker');
            });
        }
        if (hexInput) {
            hexInput.addEventListener('change', function (e) {
                apply(e.target.value, 'hex');
            });
            hexInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    apply(e.target.value, 'hex');
                }
            });
            hexInput.addEventListener('blur', function (e) {
                apply(e.target.value, 'hex');
            });
        }
    }

    function normalizeFrame(item) {
        const src = item || {};
        return {
            id: src.id || nextId('frame'),
            box: normalizeBox(src.box || { x: 0.08, y: 0.7, w: 0.84, h: 0.22 }),
            borderColor: src.borderColor || '#dce0e6',
            borderWidth: clamp(Math.round(numOr(src.borderWidth, 2)), 0, 80),
            borderRadius: clamp(Math.round(numOr(src.borderRadius, 16)), 0, 400),
            fillEnabled: !!src.fillEnabled,
            fillColor: src.fillColor || '#f8f9fb',
            square: !!src.square,
        };
    }

    /** Force frame box to a visual square in canvas pixels. */
    function makeFrameSquare(item) {
        if (!item || !item.box) {
            return;
        }
        const canvasW = Math.max(1, state.width);
        const canvasH = Math.max(1, state.height);
        const pxW = item.box.w * canvasW;
        const pxH = item.box.h * canvasH;
        const side = Math.max(8, Math.min(pxW, pxH));
        const newW = side / canvasW;
        const newH = side / canvasH;
        item.box = normalizeBox({
            x: clamp(item.box.x, 0, 1 - newW),
            y: clamp(item.box.y, 0, 1 - newH),
            w: newW,
            h: newH,
        });
    }

    function frameSquareRatio() {
        // Normalized box.w / box.h for a pixel-perfect square on the canvas.
        return state.height / Math.max(1, state.width);
    }

    function refreshSquareFrames() {
        state.frames.forEach(function (frame) {
            if (frame.square) {
                makeFrameSquare(frame);
            }
        });
    }

    function normalizeBox(box) {
        return {
            x: clamp(Number(box.x) || 0, 0, 0.98),
            y: clamp(Number(box.y) || 0, 0, 0.98),
            w: clamp(Number(box.w) || 0.3, 0.008, 1),
            h: clamp(Number(box.h) || 0.15, 0.008, 1),
        };
    }

    /**
     * Shrink/grow the text hit-box to the rendered text with a small pad,
     * so the drag selection hugs the letters instead of a large empty frame.
     */
    function fitTextBoxToContent(item) {
        if (!item || !item.box) {
            return;
        }
        const text = String(item.text || '');
        if (!text.trim()) {
            return;
        }
        const canvasW = Math.max(1, state.width);
        const canvasH = Math.max(1, state.height);
        const fontFamily = fontStack(item.font || defaultFontId);
        const fontWeight = normalizeFontWeight(item.weight || state.fontWeight || defaultFontWeight);
        const preferred = Math.max(8, Math.round(canvasH * textSizeRatio(item.size || defaultTextSizeId)));
        const measure = document.createElement('canvas').getContext('2d');
        if (!measure) {
            return;
        }
        measure.font = fontWeight + ' ' + preferred + 'px ' + fontFamily;

        // Wrap against current width first, then shrink to the widest line.
        const wrapAt = Math.max(8, item.box.w * canvasW);
        const lines = wrapText(measure, text, wrapAt);
        const lineHeight = preferred * 1.15;
        const totalH = Math.max(lineHeight, lines.length * lineHeight);
        const widest = lines.reduce(function (max, line) {
            return Math.max(max, measure.measureText(line).width);
        }, 0);

        const padX = Math.max(2, Math.round(preferred * 0.12));
        const padY = Math.max(1, Math.round(preferred * 0.08));
        const contentW = Math.min(canvasW, Math.max(8, widest + padX * 2));
        const contentH = Math.min(canvasH, Math.max(8, totalH + padY * 2));
        const newW = contentW / canvasW;
        const newH = contentH / canvasH;

        const old = item.box;
        const align = item.align || 'center';
        let newX = old.x;
        if (align === 'center') {
            newX = old.x + old.w / 2 - newW / 2;
        } else if (align === 'right') {
            newX = old.x + old.w - newW;
        }
        const newY = old.y + old.h / 2 - newH / 2;
        item.box = normalizeBox({
            x: clamp(newX, 0, 1 - newW),
            y: clamp(newY, 0, 1 - newH),
            w: newW,
            h: newH,
        });
    }

    function normalizeFontId(value) {
        const id = String(value || '').trim();
        const found = fonts.find(function (font) {
            return font.id === id || font.stack === id || id.indexOf(font.id) === 0;
        });
        return found ? found.id : defaultFontId;
    }

    function fontStack(fontId) {
        const found = fonts.find(function (font) {
            return font.id === fontId;
        });
        return found ? found.stack : fonts[0].stack;
    }

    function normalizeTextSizeId(value) {
        const id = String(value || '').trim().toLowerCase();
        const found = textSizes.find(function (size) {
            return size.id === id;
        });
        return found ? found.id : defaultTextSizeId;
    }

    function textSizeRatio(sizeId) {
        const found = textSizes.find(function (size) {
            return size.id === sizeId;
        });
        return found && typeof found.ratio === 'number' ? found.ratio : 0.055;
    }

    function clamp(v, min, max) {
        return Math.max(min, Math.min(max, v));
    }

    function setStatus(msg) {
        if (statusEl) {
            statusEl.textContent = msg || '';
        }
    }

    function selectedText() {
        return state.texts.find(function (t) {
            return t.id === state.selectedTextId;
        }) || null;
    }

    function selectedOverlay() {
        return state.overlays.find(function (o) {
            return o.id === state.selectedOverlayId;
        }) || null;
    }

    function selectedLine() {
        return state.lines.find(function (l) {
            return l.id === state.selectedLineId;
        }) || null;
    }

    function selectedFrame() {
        return state.frames.find(function (f) {
            return f.id === state.selectedFrameId;
        }) || null;
    }

    function aspectRatioFor(key) {
        const a = aspects[key] || aspects['9:16'];
        return a.w / Math.max(1, a.h);
    }

    function clampOutputSize(value) {
        const n = Math.round(Number(value) || 0);
        return Math.max(16, Math.min(4000, n || 16));
    }

    function syncCanvasSizeFields() {
        if (canvasWidthEl) {
            canvasWidthEl.value = String(state.width || '');
        }
        if (canvasHeightEl) {
            canvasHeightEl.value = String(state.height || '');
        }
        if (sizeBadge) {
            sizeBadge.textContent = (state.aspect || '9:16') + ' · ' + state.width + '×' + state.height;
        }
    }

    function setCanvasHeight(height, opts) {
        const options = opts || {};
        const h = clampOutputSize(height);
        const ratio = aspectRatioFor(state.aspect);
        const w = clampOutputSize(Math.round(h * ratio));
        state.height = h;
        state.width = w;
        refreshSquareFrames();
        syncCanvasSizeFields();
        if (bgImage && state.bgType === 'image') {
            ensureCrop();
        }
        if (!options.silent) {
            render();
            window.requestAnimationFrame(function () {
                applyPreviewZoom(previewZoom);
            });
        }
    }

    function applyAspect(key) {
        const a = aspects[key] || aspects['9:16'];
        state.aspect = key;
        state.width = a.w;
        state.height = a.h;
        refreshSquareFrames();
        root.querySelectorAll('[data-aspect]').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.getAttribute('data-aspect') === key);
        });
        if (stage) {
            stage.setAttribute('data-aspect', key);
        }
        syncCanvasSizeFields();
        if (bgImage && state.bgType === 'image') {
            ensureCrop();
        }
        render();
        // Recalculate overlays after the stage box settles to the new ratio.
        window.requestAnimationFrame(function () {
            applyPreviewZoom(previewZoom);
        });
    }

    function normalizeDownloadName(name) {
        let value = String(name || '').trim().replace(/[\/\\]+/g, '_');
        if (!value) {
            value = 'image_cut.png';
        }
        if (!/\.png$/i.test(value)) {
            value += '.png';
        }
        return value;
    }

    function baseNameFromFileName(name) {
        const raw = String(name || '').replace(/\.[^.]+$/, '').trim() || 'image';
        return raw.replace(/[^\p{L}\p{N}._-]+/gu, '_').replace(/_+/g, '_').replace(/^[._-]+|[._-]+$/g, '') || 'image';
    }

    function suggestRegionDownloadName() {
        return normalizeDownloadName(sourceUploadBaseName + '_cut.png');
    }

    function regionNativeSize() {
        if (!regionBox) {
            return { w: 0, h: 0 };
        }
        const box = normalizeRegionBox(regionBox);
        return {
            w: Math.max(1, Math.round(box.w * state.width)),
            h: Math.max(1, Math.round(box.h * state.height)),
        };
    }

    function syncRegionExportFields(resetName) {
        const size = regionNativeSize();
        regionSourceWidth = size.w;
        regionSourceHeight = size.h;
        if (regionWidthEl && regionHeightEl && regionSourceHeight > 0) {
            const currentH = clampOutputSize(regionHeightEl.value || regionSourceHeight);
            const keepCustom = !resetName && Number(regionHeightEl.value) > 0;
            const h = keepCustom ? currentH : regionSourceHeight;
            regionHeightEl.value = String(h);
            regionWidthEl.value = String(
                Math.max(1, Math.round(regionSourceWidth * (h / regionSourceHeight)))
            );
        } else if (regionWidthEl && regionHeightEl) {
            regionWidthEl.value = '';
            regionHeightEl.value = '';
        }
        if (regionNameEl && (resetName || !regionNameEl.value)) {
            regionNameEl.value = suggestRegionDownloadName();
        }
    }

    function showStep(n) {
        step = n;
        root.querySelectorAll('[data-ie-step]').forEach(function (btn) {
            btn.classList.toggle('is-active', Number(btn.getAttribute('data-ie-step')) === n);
        });
        root.querySelectorAll('[data-ie-panel]').forEach(function (panel) {
            panel.classList.toggle('is-active', Number(panel.getAttribute('data-ie-panel')) === n);
        });
        if (n === 3) {
            state.selectedOverlayId = null;
            state.selectedLineId = null;
            state.selectedFrameId = null;
            if (!state.selectedTextId && state.texts[0]) {
                state.selectedTextId = state.texts[0].id;
            }
            state.texts.forEach(function (item) {
                fitTextBoxToContent(item);
            });
            syncTextForm();
            renderTextList();
            render();
        }
        if (n === 4) {
            state.selectedTextId = null;
            state.selectedLineId = null;
            state.selectedFrameId = null;
            if (!state.selectedOverlayId && state.overlays[0]) {
                state.selectedOverlayId = state.overlays[0].id;
            }
            syncOverlayForm();
            renderOverlayList();
        }
        if (n === 5) {
            state.selectedTextId = null;
            state.selectedOverlayId = null;
            if (!state.selectedLineId && !state.selectedFrameId) {
                if (state.lines[0]) {
                    state.selectedLineId = state.lines[0].id;
                } else if (state.frames[0]) {
                    state.selectedFrameId = state.frames[0].id;
                }
            }
            syncLineForm();
            syncFrameForm();
            renderLineList();
            renderFrameList();
        }
        render();
    }

    function setBgType(type) {
        if (type === 'none') {
            state.bgType = 'none';
        } else if (type === 'image') {
            state.bgType = 'image';
        } else {
            state.bgType = 'color';
        }
        const noneCb = root.querySelector('[data-ie-bg-none]');
        if (noneCb) {
            noneCb.checked = state.bgType === 'none';
        }
        const optionsBlock = root.querySelector('[data-ie-bg-options]');
        if (optionsBlock) {
            optionsBlock.hidden = state.bgType === 'none';
        }
        root.querySelectorAll('[data-ie-bg-type]').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.getAttribute('data-ie-bg-type') === state.bgType);
            btn.disabled = state.bgType === 'none';
        });
        const colorBlock = root.querySelector('[data-ie-bg-color-block]');
        const imageBlock = root.querySelector('[data-ie-bg-image-block]');
        if (colorBlock) {
            colorBlock.hidden = state.bgType !== 'color';
        }
        if (imageBlock) {
            imageBlock.hidden = state.bgType !== 'image';
        }
        render();
    }

    function ensureCrop() {
        if (!bgImage) {
            return;
        }
        const targetRatio = state.width / state.height;
        const srcRatio = bgImage.naturalWidth / bgImage.naturalHeight;
        if (!state.crop) {
            if (srcRatio > targetRatio) {
                const h = bgImage.naturalHeight;
                const w = Math.round(h * targetRatio);
                state.crop = { x: Math.round((bgImage.naturalWidth - w) / 2), y: 0, w: w, h: h };
            } else {
                const w = bgImage.naturalWidth;
                const h = Math.round(w / targetRatio);
                state.crop = { x: 0, y: Math.round((bgImage.naturalHeight - h) / 2), w: w, h: h };
            }
        } else {
            const c = state.crop;
            let w = c.w;
            let h = Math.round(w / targetRatio);
            if (h > bgImage.naturalHeight) {
                h = bgImage.naturalHeight;
                w = Math.round(h * targetRatio);
            }
            if (w > bgImage.naturalWidth) {
                w = bgImage.naturalWidth;
                h = Math.round(w / targetRatio);
            }
            c.w = Math.max(40, w);
            c.h = Math.max(40, h);
            c.x = clamp(c.x, 0, bgImage.naturalWidth - c.w);
            c.y = clamp(c.y, 0, bgImage.naturalHeight - c.h);
        }
        if (cropHint) {
            const needsCrop = Math.abs(srcRatio - targetRatio) > 0.01;
            cropHint.hidden = !needsCrop;
        }
    }

    function loadImage(url) {
        return new Promise(function (resolve, reject) {
            const img = new Image();
            img.onload = function () {
                resolve(img);
            };
            img.onerror = reject;
            img.src = url;
        });
    }

    async function ensureOverlayImages() {
        const jobs = [];
        state.overlays.forEach(function (item) {
            if (!item.url) {
                return;
            }
            if (!overlayImages[item.id] || overlayImages[item.id].__url !== item.url) {
                jobs.push(
                    loadImage(item.url).then(function (img) {
                        img.__url = item.url;
                        overlayImages[item.id] = img;
                        if (img.naturalWidth > 0 && img.naturalHeight > 0) {
                            item.imageAspect = img.naturalWidth / img.naturalHeight;
                        }
                    }).catch(function () {
                        delete overlayImages[item.id];
                    }),
                );
            } else if (!item.imageAspect) {
                const img = overlayImages[item.id];
                if (img && img.naturalWidth > 0 && img.naturalHeight > 0) {
                    item.imageAspect = img.naturalWidth / img.naturalHeight;
                }
            }
        });
        if (jobs.length) {
            await Promise.all(jobs);
        }
    }

    /** Locked box.w / box.h so on-canvas pixels match the image aspect ratio. */
    function getOverlayLockRatio(item) {
        const img = overlayImages[item.id];
        const imageAspect =
            (item && item.imageAspect) ||
            (img && img.naturalHeight ? img.naturalWidth / img.naturalHeight : null);
        if (imageAspect && state.width > 0 && state.height > 0) {
            return imageAspect * (state.height / state.width);
        }
        const box = item && item.box;
        if (box && box.h > 0) {
            return box.w / box.h;
        }
        return 1;
    }

    function applyOverlayAspect(item) {
        if (!item || !item.box) {
            return;
        }
        const ratio = getOverlayLockRatio(item);
        if (!(ratio > 0)) {
            return;
        }
        let w = item.box.w;
        let h = w / ratio;
        if (h > 1 - item.box.y) {
            h = Math.max(0.05, 1 - item.box.y);
            w = h * ratio;
        }
        if (w > 1 - item.box.x) {
            w = Math.max(0.05, 1 - item.box.x);
            h = w / ratio;
        }
        item.box.w = clamp(w, 0.05, 1 - item.box.x);
        item.box.h = clamp(item.box.w / ratio, 0.05, 1 - item.box.y);
    }

    function syncDisplayMetrics() {
        // Use layout size (pre-transform) so overlay boxes stay aligned when zoomed.
        const layoutW = stage.clientWidth || canvas.clientWidth;
        const layoutH = stage.clientHeight || canvas.clientHeight;
        display.drawW = layoutW;
        display.drawH = layoutH;
        display.cssScaleX = layoutW / canvas.width;
        display.cssScaleY = layoutH / canvas.height;
    }

    function applyPreviewZoom(nextZoom, anchor) {
        const prev = previewZoom;
        previewZoom = Math.round(clamp(nextZoom, ZOOM_MIN, ZOOM_MAX) * 100) / 100;
        if (stageWrap) {
            stageWrap.style.transform = previewZoom === 1 ? '' : 'scale(' + previewZoom + ')';
        }
        // Expand scrollable area to match the visual scaled size.
        if (zoomSizer && stage) {
            const baseW = stage.offsetWidth;
            const baseH = stage.offsetHeight;
            zoomSizer.style.width = Math.max(1, Math.round(baseW * previewZoom)) + 'px';
            zoomSizer.style.height = Math.max(1, Math.round(baseH * previewZoom)) + 'px';
        }
        if (zoomLabelEl) {
            zoomLabelEl.textContent = Math.round(previewZoom * 100) + '%';
        }
        if (zoomOutBtn) {
            zoomOutBtn.disabled = previewZoom <= ZOOM_MIN + 0.001;
        }
        if (zoomInBtn) {
            zoomInBtn.disabled = previewZoom >= ZOOM_MAX - 0.001;
        }
        if (viewport && prev > 0 && previewZoom !== prev) {
            // Keep the point under the cursor/center stable while zooming.
            const rect = viewport.getBoundingClientRect();
            const ax = anchor && typeof anchor.x === 'number' ? anchor.x : rect.left + rect.width / 2;
            const ay = anchor && typeof anchor.y === 'number' ? anchor.y : rect.top + rect.height / 2;
            const relX = (viewport.scrollLeft + (ax - rect.left)) / prev;
            const relY = (viewport.scrollTop + (ay - rect.top)) / prev;
            viewport.scrollLeft = relX * previewZoom - (ax - rect.left);
            viewport.scrollTop = relY * previewZoom - (ay - rect.top);
        }
        window.requestAnimationFrame(function () {
            updateOverlays();
        });
    }

    function zoomBy(delta, anchor) {
        applyPreviewZoom(previewZoom + delta, anchor);
    }

    function isCropMode() {
        return step === 2 && state.bgType === 'image' && !!bgImage;
    }

    function isLayerEditMode() {
        return !regionSelectMode && (step === 3 || step === 4 || step === 5);
    }

    function isRegionSelectMode() {
        return !!regionSelectMode;
    }

    function normalizeRegionBox(box) {
        let x = clamp(Number(box.x) || 0, 0, 0.98);
        let y = clamp(Number(box.y) || 0, 0, 0.98);
        let w = clamp(Number(box.w) || 0.2, 0.02, 1 - x);
        let h = clamp(Number(box.h) || 0.2, 0.02, 1 - y);
        return { x: x, y: y, w: w, h: h };
    }

    function setRegionSelectMode(on) {
        regionSelectMode = !!on;
        if (stage) {
            stage.classList.toggle('is-region-select', regionSelectMode);
        }
        if (regionToggleBtn) {
            regionToggleBtn.classList.toggle('active', regionSelectMode);
            regionToggleBtn.classList.toggle('btn-primary', regionSelectMode);
            regionToggleBtn.classList.toggle('btn-outline-primary', !regionSelectMode);
        }
        if (regionSaveBtn) {
            regionSaveBtn.hidden = !regionSelectMode;
        }
        if (regionCancelBtn) {
            regionCancelBtn.hidden = !regionSelectMode;
        }
        if (regionHintEl) {
            regionHintEl.hidden = !regionSelectMode;
        }
        if (regionExportEl) {
            regionExportEl.hidden = !regionSelectMode;
        }
        if (!regionSelectMode) {
            regionBox = null;
            regionSourceWidth = 0;
            regionSourceHeight = 0;
        } else if (!regionBox) {
            regionBox = { x: 0.15, y: 0.15, w: 0.7, h: 0.4 };
        }
        if (regionSelectMode) {
            syncRegionExportFields(true);
        }
        updateOverlays();
    }

    function updateRegionOverlay() {
        if (!regionEl) {
            return;
        }
        if (!regionSelectMode || !regionBox) {
            regionEl.hidden = true;
            return;
        }
        regionEl.hidden = false;
        const box = normalizeRegionBox(regionBox);
        regionBox = box;
        regionEl.style.left = box.x * display.drawW + 'px';
        regionEl.style.top = box.y * display.drawH + 'px';
        regionEl.style.width = box.w * display.drawW + 'px';
        regionEl.style.height = box.h * display.drawH + 'px';
        syncRegionExportFields(false);
    }

    async function saveRegionPng() {
        if (!regionBox || regionBox.w < 0.01 || regionBox.h < 0.01) {
            setStatus(i18n.regionEmpty || 'Select a region first.');
            return;
        }
        await ensureOverlayImages();
        const box = normalizeRegionBox(regionBox);
        const full = document.createElement('canvas');
        full.width = state.width;
        full.height = state.height;
        const fullCtx = full.getContext('2d');
        // Force transparent background for this export.
        const prevBg = state.bgType;
        state.bgType = 'none';
        drawComposition(fullCtx, full.width, full.height);
        state.bgType = prevBg;

        const sx = Math.round(box.x * full.width);
        const sy = Math.round(box.y * full.height);
        const sw = Math.max(1, Math.round(box.w * full.width));
        const sh = Math.max(1, Math.round(box.h * full.height));
        const cropped = document.createElement('canvas');
        cropped.width = sw;
        cropped.height = sh;
        cropped.getContext('2d').drawImage(full, sx, sy, sw, sh, 0, 0, sw, sh);

        const targetH = clampOutputSize(regionHeightEl && regionHeightEl.value ? regionHeightEl.value : sh);
        let out = cropped;
        if (targetH !== sh) {
            const targetW = Math.max(1, Math.round(sw * (targetH / sh)));
            out = document.createElement('canvas');
            out.width = targetW;
            out.height = targetH;
            const octx = out.getContext('2d');
            octx.imageSmoothingEnabled = true;
            octx.imageSmoothingQuality = 'high';
            octx.drawImage(cropped, 0, 0, sw, sh, 0, 0, targetW, targetH);
        }

        const link = document.createElement('a');
        link.href = out.toDataURL('image/png');
        link.download = normalizeDownloadName(
            (regionNameEl && regionNameEl.value) || suggestRegionDownloadName()
        );
        document.body.appendChild(link);
        link.click();
        link.remove();
        setStatus(i18n.regionSaved || 'Region PNG downloaded.');
        render();
    }

    function dateStamp() {
        const d = new Date();
        const p = function (n) {
            return String(n).padStart(2, '0');
        };
        return (
            d.getFullYear() +
            p(d.getMonth() + 1) +
            p(d.getDate()) +
            '-' +
            p(d.getHours()) +
            p(d.getMinutes()) +
            p(d.getSeconds())
        );
    }

    function updateOverlays() {
        syncDisplayMetrics();
        if (isCropMode()) {
            overlay.hidden = false;
            overlay.classList.add('is-active');
            layerBoxesEl.innerHTML = '';
            if (regionEl) {
                regionEl.hidden = true;
            }
            const scaleBuf = Math.min(canvas.width / bgImage.naturalWidth, canvas.height / bgImage.naturalHeight);
            const oxBuf = (canvas.width - bgImage.naturalWidth * scaleBuf) / 2;
            const oyBuf = (canvas.height - bgImage.naturalHeight * scaleBuf) / 2;
            display.sourceScale = scaleBuf;
            display.sourceOx = oxBuf;
            display.sourceOy = oyBuf;
            const c = state.crop;
            cropEl.style.left = (oxBuf + c.x * scaleBuf) * display.cssScaleX + 'px';
            cropEl.style.top = (oyBuf + c.y * scaleBuf) * display.cssScaleY + 'px';
            cropEl.style.width = c.w * scaleBuf * display.cssScaleX + 'px';
            cropEl.style.height = c.h * scaleBuf * display.cssScaleY + 'px';
            return;
        }

        overlay.hidden = true;
        overlay.classList.remove('is-active');

        if (isRegionSelectMode()) {
            layerBoxesEl.innerHTML = '';
            updateRegionOverlay();
            return;
        }

        if (regionEl) {
            regionEl.hidden = true;
        }

        if (!isLayerEditMode()) {
            layerBoxesEl.innerHTML = '';
            return;
        }

        if (step === 5) {
            const parts = [];
            state.frames.forEach(function (item) {
                const box = item.box;
                const active = item.id === state.selectedFrameId ? ' is-active' : '';
                parts.push(
                    '<div class="img-editor__layerbox' +
                        active +
                        '" data-frame-id="' +
                        item.id +
                        '" style="left:' +
                        box.x * display.drawW +
                        'px;top:' +
                        box.y * display.drawH +
                        'px;width:' +
                        box.w * display.drawW +
                        'px;height:' +
                        box.h * display.drawH +
                        'px;">' +
                        '<span class="img-editor__textbox-handle" data-thandle="se"></span>' +
                        '</div>',
                );
            });
            state.lines.forEach(function (item) {
                const pad = 10;
                const left = Math.min(item.x1, item.x2) * display.drawW - pad;
                const top = Math.min(item.y1, item.y2) * display.drawH - pad;
                const width = Math.abs(item.x2 - item.x1) * display.drawW + pad * 2;
                const height = Math.abs(item.y2 - item.y1) * display.drawH + pad * 2;
                const active = item.id === state.selectedLineId ? ' is-active' : '';
                parts.push(
                    '<div class="img-editor__line-hit' +
                        active +
                        '" data-line-id="' +
                        item.id +
                        '" style="left:' +
                        left +
                        'px;top:' +
                        top +
                        'px;width:' +
                        Math.max(width, 20) +
                        'px;height:' +
                        Math.max(height, 20) +
                        'px;"></div>',
                );
                if (item.id === state.selectedLineId) {
                    parts.push(
                        '<span class="img-editor__line-handle" data-line-id="' +
                            item.id +
                            '" data-line-handle="start" style="left:' +
                            item.x1 * display.drawW +
                            'px;top:' +
                            item.y1 * display.drawH +
                            'px;"></span>',
                    );
                    parts.push(
                        '<span class="img-editor__line-handle" data-line-id="' +
                            item.id +
                            '" data-line-handle="end" style="left:' +
                            item.x2 * display.drawW +
                            'px;top:' +
                            item.y2 * display.drawH +
                            'px;"></span>',
                    );
                }
            });
            layerBoxesEl.innerHTML = parts.join('');
            return;
        }

        const items = step === 3 ? state.texts : state.overlays;
        const selectedId = step === 3 ? state.selectedTextId : state.selectedOverlayId;
        const idAttr = step === 3 ? 'data-layer-id' : 'data-layer-id';
        layerBoxesEl.innerHTML = items
            .map(function (item) {
                const box = item.box;
                const active = item.id === selectedId ? ' is-active' : '';
                return (
                    '<div class="img-editor__layerbox' +
                    active +
                    '" ' +
                    idAttr +
                    '="' +
                    item.id +
                    '" style="left:' +
                    box.x * display.drawW +
                    'px;top:' +
                    box.y * display.drawH +
                    'px;width:' +
                    box.w * display.drawW +
                    'px;height:' +
                    box.h * display.drawH +
                    'px;">' +
                    '<span class="img-editor__textbox-handle" data-thandle="se"></span>' +
                    '</div>'
                );
            })
            .join('');
    }

    function render() {
        if (isCropMode()) {
            canvas.width = state.width;
            canvas.height = state.height;
            ctx.fillStyle = '#0f172a';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            const scale = Math.min(canvas.width / bgImage.naturalWidth, canvas.height / bgImage.naturalHeight);
            const dw = bgImage.naturalWidth * scale;
            const dh = bgImage.naturalHeight * scale;
            const ox = (canvas.width - dw) / 2;
            const oy = (canvas.height - dh) / 2;
            ctx.drawImage(bgImage, ox, oy, dw, dh);
            updateOverlays();
            return;
        }

        canvas.width = state.width;
        canvas.height = state.height;
        drawComposition(ctx, canvas.width, canvas.height);
        updateOverlays();
    }

    function drawComposition(targetCtx, w, h) {
        targetCtx.clearRect(0, 0, w, h);
        if (state.bgType === 'image' && bgImage && state.crop) {
            const c = state.crop;
            targetCtx.drawImage(bgImage, c.x, c.y, c.w, c.h, 0, 0, w, h);
        } else if (state.bgType !== 'none') {
            targetCtx.fillStyle = state.bgColor || '#1e293b';
            targetCtx.fillRect(0, 0, w, h);
        }

        state.frames.forEach(function (item) {
            drawFrame(targetCtx, item, w, h);
        });

        state.overlays.forEach(function (item) {
            const img = overlayImages[item.id];
            if (!img) {
                return;
            }
            const box = item.box;
            targetCtx.save();
            targetCtx.globalAlpha = typeof item.opacity === 'number' ? item.opacity : 1;
            targetCtx.drawImage(img, box.x * w, box.y * h, box.w * w, box.h * h);
            targetCtx.restore();
        });

        state.lines.forEach(function (item) {
            drawLine(targetCtx, item, w, h);
        });

        state.texts.forEach(function (item) {
            drawFittedText(targetCtx, item, w, h);
        });
    }

    function drawRoundedRectPath(targetCtx, x, y, width, height, radius) {
        const r = Math.max(0, Math.min(radius, Math.min(width, height) / 2));
        targetCtx.beginPath();
        targetCtx.moveTo(x + r, y);
        targetCtx.arcTo(x + width, y, x + width, y + height, r);
        targetCtx.arcTo(x + width, y + height, x, y + height, r);
        targetCtx.arcTo(x, y + height, x, y, r);
        targetCtx.arcTo(x, y, x + width, y, r);
        targetCtx.closePath();
    }

    function drawFrame(targetCtx, item, w, h) {
        const box = item.box;
        const x = box.x * w;
        const y = box.y * h;
        const width = Math.max(1, box.w * w);
        const height = Math.max(1, box.h * h);
        const radius = Number(item.borderRadius) || 0;
        const borderWidth = Number(item.borderWidth) || 0;

        targetCtx.save();
        drawRoundedRectPath(targetCtx, x, y, width, height, radius);
        if (item.fillEnabled) {
            targetCtx.fillStyle = item.fillColor || '#f8f9fb';
            targetCtx.fill();
        }
        if (borderWidth > 0) {
            targetCtx.lineWidth = borderWidth;
            targetCtx.strokeStyle = item.borderColor || '#dce0e6';
            targetCtx.stroke();
        }
        targetCtx.restore();
    }

    function drawLine(targetCtx, item, w, h) {
        targetCtx.save();
        targetCtx.strokeStyle = item.color || '#d2d6dc';
        targetCtx.lineWidth = Math.max(1, Number(item.width) || 2);
        targetCtx.lineCap = 'round';
        targetCtx.beginPath();
        targetCtx.moveTo(item.x1 * w, item.y1 * h);
        targetCtx.lineTo(item.x2 * w, item.y2 * h);
        targetCtx.stroke();
        targetCtx.restore();
    }

    function drawFittedText(targetCtx, item, w, h) {
        const text = (item.text || '').trim();
        if (!text) {
            return;
        }
        const box = item.box;
        const boxX = box.x * w;
        const boxY = box.y * h;
        const boxW = Math.max(8, box.w * w);
        const boxH = Math.max(8, box.h * h);
        const fontFamily = fontStack(item.font || defaultFontId);
        const fontWeight = normalizeFontWeight(item.weight || state.fontWeight || defaultFontWeight);
        const align = item.align || 'center';
        const preferred = Math.max(10, Math.round(h * textSizeRatio(item.size || defaultTextSizeId)));

        // Prefer selected size, but shrink to fit the text box if needed.
        let lo = 8;
        let hi = Math.min(preferred, Math.max(8, Math.floor(boxH)));
        let best = lo;
        while (lo <= hi) {
            const mid = Math.floor((lo + hi) / 2);
            targetCtx.font = fontWeight + ' ' + mid + 'px ' + fontFamily;
            const lines = wrapText(targetCtx, text, Math.max(4, boxW - 2));
            const lineHeight = mid * 1.15;
            const totalH = lines.length * lineHeight;
            const widest = lines.reduce(function (max, line) {
                return Math.max(max, targetCtx.measureText(line).width);
            }, 0);
            if (totalH <= boxH && widest <= boxW - 2) {
                best = mid;
                lo = mid + 1;
            } else {
                hi = mid - 1;
            }
        }

        targetCtx.font = fontWeight + ' ' + best + 'px ' + fontFamily;
        targetCtx.fillStyle = item.color || '#ffffff';
        targetCtx.textAlign = align;
        targetCtx.textBaseline = 'top';
        targetCtx.shadowColor = 'rgba(0,0,0,.35)';
        targetCtx.shadowBlur = Math.max(2, best * 0.08);
        targetCtx.shadowOffsetY = Math.max(1, best * 0.04);

        const lines = wrapText(targetCtx, text, Math.max(4, boxW - 2));
        const lineHeight = best * 1.15;
        const totalH = lines.length * lineHeight;
        let startY = boxY + Math.max(0, (boxH - totalH) / 2);
        let startX = boxX + 1;
        if (align === 'center') {
            startX = boxX + boxW / 2;
        } else if (align === 'right') {
            startX = boxX + boxW - 1;
        }

        lines.forEach(function (line, idx) {
            targetCtx.fillText(line, startX, startY + idx * lineHeight, Math.max(4, boxW - 2));
        });
        targetCtx.shadowColor = 'transparent';
    }

    function wrapText(context, text, maxWidth) {
        const paragraphs = String(text).split(/\n/);
        const lines = [];
        paragraphs.forEach(function (paragraph) {
            const words = paragraph.split(/\s+/).filter(Boolean);
            if (words.length === 0) {
                lines.push('');
                return;
            }
            let line = words[0];
            for (let i = 1; i < words.length; i++) {
                const test = line + ' ' + words[i];
                if (context.measureText(test).width > maxWidth) {
                    lines.push(line);
                    line = words[i];
                } else {
                    line = test;
                }
            }
            lines.push(line);
        });
        return lines;
    }

    function renderTextList() {
        if (!textListEl) {
            return;
        }
        if (state.texts.length === 0) {
            textListEl.innerHTML = '<div class="img-editor__layer-empty">' + (i18n.emptyTexts || 'No texts yet') + '</div>';
            return;
        }
        textListEl.innerHTML = state.texts
            .map(function (item, index) {
                const label = (item.text || '').trim() || i18n.untitled || 'Untitled';
                const active = item.id === state.selectedTextId ? ' is-active' : '';
                return (
                    '<button type="button" class="img-editor__layer-item' +
                    active +
                    '" data-select-text="' +
                    item.id +
                    '"><strong>' +
                    (i18n.textItem || 'Text') +
                    ' ' +
                    (index + 1) +
                    '</strong><span>' +
                    escapeHtml(label).slice(0, 48) +
                    '</span></button>'
                );
            })
            .join('');
    }

    function renderOverlayList() {
        if (!overlayListEl) {
            return;
        }
        if (state.overlays.length === 0) {
            overlayListEl.innerHTML = '<div class="img-editor__layer-empty">' + (i18n.emptyOverlays || 'No images yet') + '</div>';
            return;
        }
        overlayListEl.innerHTML = state.overlays
            .map(function (item, index) {
                const active = item.id === state.selectedOverlayId ? ' is-active' : '';
                return (
                    '<button type="button" class="img-editor__layer-item' +
                    active +
                    '" data-select-overlay="' +
                    item.id +
                    '"><strong>' +
                    (i18n.overlayItem || 'Image') +
                    ' ' +
                    (index + 1) +
                    '</strong><span>' +
                    Math.round((item.opacity || 1) * 100) +
                    '% · ' +
                    escapeHtml((item.url || '').split('/').pop() || '') +
                    '</span></button>'
                );
            })
            .join('');
    }

    function renderLineList() {
        if (!lineListEl) {
            return;
        }
        if (state.lines.length === 0) {
            lineListEl.innerHTML = '<div class="img-editor__layer-empty">' + (i18n.emptyLines || 'No lines yet') + '</div>';
            return;
        }
        lineListEl.innerHTML = state.lines
            .map(function (item, index) {
                const active = item.id === state.selectedLineId ? ' is-active' : '';
                return (
                    '<div class="img-editor__layer-row' +
                    active +
                    '">' +
                    '<button type="button" class="img-editor__layer-item' +
                    active +
                    '" data-select-line="' +
                    item.id +
                    '"><strong>' +
                    (i18n.lineItem || 'Line') +
                    ' ' +
                    (index + 1) +
                    '</strong><span>' +
                    item.width +
                    'px · ' +
                    escapeHtml(item.color || '') +
                    '</span></button>' +
                    '<button type="button" class="btn btn-sm btn-outline-danger img-editor__layer-del" data-delete-line="' +
                    item.id +
                    '" title="' +
                    escapeHtml(i18n.removeLayer || 'Remove') +
                    '">×</button>' +
                    '</div>'
                );
            })
            .join('');
    }

    function renderFrameList() {
        if (!frameListEl) {
            return;
        }
        if (state.frames.length === 0) {
            frameListEl.innerHTML = '<div class="img-editor__layer-empty">' + (i18n.emptyFrames || 'No frames yet') + '</div>';
            return;
        }
        frameListEl.innerHTML = state.frames
            .map(function (item, index) {
                const active = item.id === state.selectedFrameId ? ' is-active' : '';
                return (
                    '<div class="img-editor__layer-row' +
                    active +
                    '">' +
                    '<button type="button" class="img-editor__layer-item' +
                    active +
                    '" data-select-frame="' +
                    item.id +
                    '"><strong>' +
                    (i18n.frameItem || 'Frame') +
                    ' ' +
                    (index + 1) +
                    '</strong><span>' +
                    item.borderWidth +
                    'px · r' +
                    item.borderRadius +
                    (item.fillEnabled ? ' · fill' : '') +
                    '</span></button>' +
                    '<button type="button" class="btn btn-sm btn-outline-danger img-editor__layer-del" data-delete-frame="' +
                    item.id +
                    '" title="' +
                    escapeHtml(i18n.removeLayer || 'Remove') +
                    '">×</button>' +
                    '</div>'
                );
            })
            .join('');
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function syncTextForm() {
        const item = selectedText();
        if (!textForm) {
            return;
        }
        if (!item) {
            textForm.hidden = true;
            return;
        }
        textForm.hidden = false;
        root.querySelector('[data-ie-text]').value = item.text || '';
        setColorInputs('[data-ie-text-color]', '[data-ie-text-color-hex]', item.color || '#ffffff');
        root.querySelector('[data-ie-text-align]').value = item.align || 'center';
        const fontSelect = root.querySelector('[data-ie-text-font]');
        if (fontSelect) {
            fontSelect.value = normalizeFontId(item.font || defaultFontId);
            fontSelect.style.fontFamily = fontStack(fontSelect.value);
        }
        const sizeSelect = root.querySelector('[data-ie-text-size]');
        if (sizeSelect) {
            sizeSelect.value = normalizeTextSizeId(item.size || defaultTextSizeId);
        }
        const weightSelect = root.querySelector('[data-ie-text-weight]');
        if (weightSelect) {
            weightSelect.value = normalizeFontWeight(item.weight || defaultFontWeight);
        }
    }

    function overlayPixelSize(item) {
        if (!item || !item.box) {
            return { w: 0, h: 0 };
        }
        return {
            w: Math.max(1, Math.round(item.box.w * state.width)),
            h: Math.max(1, Math.round(item.box.h * state.height)),
        };
    }

    function setOverlayHeightPx(item, heightPx) {
        if (!item || !item.box) {
            return;
        }
        const hPx = clampOutputSize(heightPx);
        const ratio = getOverlayLockRatio(item); // box.w / box.h in normalized space
        let hNorm = hPx / Math.max(1, state.height);
        let wNorm = hNorm * ratio;
        if (wNorm > 1 - item.box.x) {
            wNorm = Math.max(0.05, 1 - item.box.x);
            hNorm = wNorm / Math.max(0.0001, ratio);
        }
        if (hNorm > 1 - item.box.y) {
            hNorm = Math.max(0.05, 1 - item.box.y);
            wNorm = hNorm * ratio;
        }
        item.box.w = clamp(wNorm, 0.05, 1 - item.box.x);
        item.box.h = clamp(hNorm, 0.05, 1 - item.box.y);
        // Re-lock aspect after clamps.
        applyOverlayAspect(item);
    }

    function syncOverlayForm() {
        const item = selectedOverlay();
        if (!overlayForm) {
            return;
        }
        if (!item) {
            overlayForm.hidden = true;
            return;
        }
        overlayForm.hidden = false;
        const range = root.querySelector('[data-ie-opacity]');
        const label = root.querySelector('[data-ie-opacity-label]');
        const widthEl = root.querySelector('[data-ie-overlay-width]');
        const heightEl = root.querySelector('[data-ie-overlay-height]');
        const hintEl = root.querySelector('[data-ie-overlay-size-hint]');
        const pct = Math.round((item.opacity || 1) * 100);
        range.value = String(pct);
        if (label) {
            label.textContent = pct + '%';
        }
        const size = overlayPixelSize(item);
        if (widthEl) {
            widthEl.value = String(size.w);
        }
        if (heightEl && document.activeElement !== heightEl) {
            heightEl.value = String(size.h);
        }
        if (hintEl) {
            const img = overlayImages[item.id];
            const parts = [i18n.overlaySizeHelp || 'Height of the image on the canvas.'];
            if (img && img.naturalWidth && img.naturalHeight) {
                parts.push(
                    (i18n.overlayNaturalSize || 'Source file %width%×%height% px.')
                        .replace('%width%', String(img.naturalWidth))
                        .replace('%height%', String(img.naturalHeight))
                );
            }
            hintEl.textContent = parts.join(' ');
        }
    }

    function pct(value) {
        return Math.round(clamp(Number(value) || 0, 0, 1) * 1000) / 10;
    }

    function syncLineForm() {
        const item = selectedLine();
        if (!lineForm) {
            return;
        }
        if (!item) {
            lineForm.hidden = true;
            return;
        }
        lineForm.hidden = false;
        setColorInputs('[data-ie-line-color]', '[data-ie-line-color-hex]', item.color || '#d2d6dc');
        root.querySelector('[data-ie-line-width]').value = String(item.width || 2);
        root.querySelector('[data-ie-line-x1]').value = String(pct(item.x1));
        root.querySelector('[data-ie-line-y1]').value = String(pct(item.y1));
        root.querySelector('[data-ie-line-x2]').value = String(pct(item.x2));
        root.querySelector('[data-ie-line-y2]').value = String(pct(item.y2));
    }

    function syncFrameForm() {
        const item = selectedFrame();
        if (!frameForm) {
            return;
        }
        if (!item) {
            frameForm.hidden = true;
            return;
        }
        frameForm.hidden = false;
        setColorInputs('[data-ie-frame-border-color]', '[data-ie-frame-border-color-hex]', item.borderColor || '#dce0e6');
        root.querySelector('[data-ie-frame-border-width]').value = String(numOr(item.borderWidth, 0));
        root.querySelector('[data-ie-frame-radius]').value = String(numOr(item.borderRadius, 0));
        const fillEnabled = root.querySelector('[data-ie-frame-fill-enabled]');
        fillEnabled.checked = !!item.fillEnabled;
        setColorInputs('[data-ie-frame-fill-color]', '[data-ie-frame-fill-color-hex]', item.fillColor || '#f8f9fb');
        const fillWrap = root.querySelector('[data-ie-frame-fill-color-wrap]');
        if (fillWrap) {
            fillWrap.hidden = !item.fillEnabled;
        }
        const squareEl = root.querySelector('[data-ie-frame-square]');
        if (squareEl) {
            squareEl.checked = !!item.square;
        }
    }

    function addText() {
        const item = {
            id: nextId('text'),
            text: i18n.untitled || 'Text',
            color: '#ffffff',
            align: 'center',
            font: defaultFontId,
            size: defaultTextSizeId,
            weight: defaultFontWeight,
            box: { x: 0.2, y: 0.2 + state.texts.length * 0.06, w: 0.6, h: 0.08 },
        };
        item.box.y = clamp(item.box.y, 0, 0.85);
        fitTextBoxToContent(item);
        state.texts.push(item);
        state.selectedTextId = item.id;
        state.selectedOverlayId = null;
        state.selectedLineId = null;
        state.selectedFrameId = null;
        renderTextList();
        syncTextForm();
        showStep(3);
        render();
    }

    function addLine() {
        const y = clamp(0.35 + state.lines.length * 0.05, 0.05, 0.95);
        const item = normalizeLine({
            id: nextId('line'),
            x1: 0.05,
            y1: y,
            x2: 0.95,
            y2: y,
            color: '#d2d6dc',
            width: 2,
        });
        state.lines.push(item);
        state.selectedLineId = item.id;
        state.selectedFrameId = null;
        state.selectedTextId = null;
        state.selectedOverlayId = null;
        renderLineList();
        syncLineForm();
        showStep(5);
    }

    function addFrame() {
        const item = normalizeFrame({
            id: nextId('frame'),
            box: { x: 0.08, y: clamp(0.55 + state.frames.length * 0.05, 0.05, 0.7), w: 0.84, h: 0.22 },
            borderColor: '#dce0e6',
            borderWidth: 2,
            borderRadius: 16,
            fillEnabled: true,
            fillColor: '#f8f9fb',
            square: false,
        });
        state.frames.push(item);
        state.selectedFrameId = item.id;
        state.selectedLineId = null;
        state.selectedTextId = null;
        state.selectedOverlayId = null;
        renderFrameList();
        syncFrameForm();
        showStep(5);
    }

    function offsetBoxBeside(box) {
        const src = normalizeBox(box || {});
        const shift = 0.05;
        let x = src.x + shift;
        let y = src.y + shift * 0.6;
        if (x + src.w > 1) {
            x = Math.max(0, src.x - shift);
        }
        if (y + src.h > 1) {
            y = Math.max(0, src.y - shift * 0.6);
        }
        return normalizeBox({ x: x, y: y, w: src.w, h: src.h });
    }

    function offsetPoint(value, delta) {
        return clamp((Number(value) || 0) + delta, 0, 1);
    }

    function duplicateSelectedText() {
        const src = selectedText();
        if (!src) {
            return;
        }
        const item = {
            id: nextId('text'),
            text: src.text,
            color: src.color,
            align: src.align,
            font: src.font,
            size: src.size,
            weight: src.weight,
            box: offsetBoxBeside(src.box),
        };
        fitTextBoxToContent(item);
        state.texts.push(item);
        state.selectedTextId = item.id;
        state.selectedOverlayId = null;
        state.selectedLineId = null;
        state.selectedFrameId = null;
        renderTextList();
        syncTextForm();
        render();
    }

    function duplicateSelectedOverlay() {
        const src = selectedOverlay();
        if (!src) {
            return;
        }
        const item = {
            id: nextId('overlay'),
            url: src.url,
            opacity: typeof src.opacity === 'number' ? src.opacity : 1,
            imageAspect: src.imageAspect || null,
            box: offsetBoxBeside(src.box),
        };
        state.overlays.push(item);
        if (overlayImages[src.id]) {
            overlayImages[item.id] = overlayImages[src.id];
        }
        state.selectedOverlayId = item.id;
        state.selectedTextId = null;
        state.selectedLineId = null;
        state.selectedFrameId = null;
        renderOverlayList();
        syncOverlayForm();
        ensureOverlayImages().then(function () {
            applyOverlayAspect(item);
            syncOverlayForm();
            render();
        });
        render();
    }

    function duplicateSelectedLine() {
        const src = selectedLine();
        if (!src) {
            return;
        }
        const shiftX = 0.05;
        const shiftY = 0.03;
        const dx = Math.max(src.x1, src.x2) + shiftX > 1 ? -shiftX : shiftX;
        const dy = Math.max(src.y1, src.y2) + shiftY > 1 ? -shiftY : shiftY;
        const item = normalizeLine({
            id: nextId('line'),
            x1: offsetPoint(src.x1, dx),
            y1: offsetPoint(src.y1, dy),
            x2: offsetPoint(src.x2, dx),
            y2: offsetPoint(src.y2, dy),
            color: src.color,
            width: src.width,
        });
        state.lines.push(item);
        state.selectedLineId = item.id;
        state.selectedFrameId = null;
        state.selectedTextId = null;
        state.selectedOverlayId = null;
        renderLineList();
        syncLineForm();
        render();
    }

    function duplicateSelectedFrame() {
        const src = selectedFrame();
        if (!src) {
            return;
        }
        const item = normalizeFrame({
            id: nextId('frame'),
            box: offsetBoxBeside(src.box),
            borderColor: src.borderColor,
            borderWidth: src.borderWidth,
            borderRadius: src.borderRadius,
            fillEnabled: !!src.fillEnabled,
            fillColor: src.fillColor,
            square: !!src.square,
        });
        state.frames.push(item);
        state.selectedFrameId = item.id;
        state.selectedLineId = null;
        state.selectedTextId = null;
        state.selectedOverlayId = null;
        renderFrameList();
        syncFrameForm();
        render();
    }

    function removeSelectedText() {
        if (!state.selectedTextId) {
            return;
        }
        state.texts = state.texts.filter(function (t) {
            return t.id !== state.selectedTextId;
        });
        state.selectedTextId = state.texts[0] ? state.texts[0].id : null;
        renderTextList();
        syncTextForm();
        render();
    }

    function removeSelectedOverlay() {
        if (!state.selectedOverlayId) {
            return;
        }
        delete overlayImages[state.selectedOverlayId];
        state.overlays = state.overlays.filter(function (o) {
            return o.id !== state.selectedOverlayId;
        });
        state.selectedOverlayId = state.overlays[0] ? state.overlays[0].id : null;
        renderOverlayList();
        syncOverlayForm();
        render();
    }

    function removeSelectedLine() {
        if (!state.selectedLineId) {
            return;
        }
        const removedId = state.selectedLineId;
        state.lines = state.lines.filter(function (l) {
            return l.id !== removedId;
        });
        state.selectedLineId = state.lines[0] ? state.lines[0].id : null;
        renderLineList();
        renderFrameList();
        syncLineForm();
        syncFrameForm();
        render();
    }

    function removeSelectedFrame() {
        if (!state.selectedFrameId) {
            return;
        }
        const removedId = state.selectedFrameId;
        state.frames = state.frames.filter(function (f) {
            return f.id !== removedId;
        });
        state.selectedFrameId = state.frames[0] ? state.frames[0].id : null;
        renderLineList();
        renderFrameList();
        syncLineForm();
        syncFrameForm();
        render();
    }

    function removeLineById(id) {
        if (!id) {
            return;
        }
        state.selectedLineId = id;
        state.selectedFrameId = null;
        removeSelectedLine();
    }

    function removeFrameById(id) {
        if (!id) {
            return;
        }
        state.selectedFrameId = id;
        state.selectedLineId = null;
        removeSelectedFrame();
    }

    function removeSelectedShapeOrLayer() {
        if (step === 5) {
            if (state.selectedLineId) {
                removeSelectedLine();
                return true;
            }
            if (state.selectedFrameId) {
                removeSelectedFrame();
                return true;
            }
        }
        if (step === 3 && state.selectedTextId) {
            removeSelectedText();
            return true;
        }
        if (step === 4 && state.selectedOverlayId) {
            removeSelectedOverlay();
            return true;
        }
        return false;
    }

    async function exportDataUrl() {
        await ensureOverlayImages();
        const exportCanvas = document.createElement('canvas');
        exportCanvas.width = state.width;
        exportCanvas.height = state.height;
        const exportCtx = exportCanvas.getContext('2d');
        drawComposition(exportCtx, exportCanvas.width, exportCanvas.height);
        // Transparent background must be PNG (JPEG has no alpha).
        if (state.bgType === 'none') {
            return exportCanvas.toDataURL('image/png');
        }
        return exportCanvas.toDataURL('image/jpeg', 0.92);
    }

    async function save(kind) {
        setStatus(i18n.saving || 'Saving…');
        try {
            const image = await exportDataUrl();
            const payload = {
                image: image,
                content: {
                    aspect: state.aspect,
                    width: state.width,
                    height: state.height,
                    bgType: state.bgType,
                    bgColor: state.bgColor,
                    bgImageUrl: state.bgImageUrl,
                    crop: state.crop,
                    texts: state.texts,
                    overlays: state.overlays,
                    lines: state.lines,
                    frames: state.frames,
                    fontWeight: state.fontWeight,
                    saveKind: kind || 'final',
                },
            };
            if (currentFilename) {
                payload.filename = currentFilename;
            }
            const res = await fetch(cfg.saveUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (!res.ok || !data.ok) {
                throw new Error(data.error || 'save failed');
            }
            currentFilename = data.filename;
            if (Array.isArray(data.saved_images)) {
                renderSaved(data.saved_images);
            }
            setStatus(data.message || i18n.saved || 'Saved');
        } catch (e) {
            setStatus(i18n.saveError || 'Save failed');
        }
    }

    async function uploadFile(file, kind) {
        const body = new FormData();
        body.append('file', file);
        body.append('kind', kind);
        setStatus('…');
        const res = await fetch(cfg.uploadUrl, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();
        if (!res.ok || !data.ok) {
            throw new Error('upload failed');
        }
        return data;
    }

    async function uploadBackground(file) {
        try {
            const data = await uploadFile(file, 'bg');
            sourceUploadBaseName = baseNameFromFileName(file && file.name);
            state.bgType = 'image';
            state.bgImageUrl = data.url;
            state.crop = null;
            bgImage = await loadImage(data.url + '?t=' + Date.now());
            ensureCrop();
            setBgType('image');
            if (regionSelectMode) {
                syncRegionExportFields(true);
            }
            setStatus('');
            render();
        } catch (e) {
            setStatus(i18n.uploadError || 'Upload failed');
        }
    }

    async function uploadOverlay(file) {
        try {
            const data = await uploadFile(file, 'overlay');
            const item = {
                id: nextId('overlay'),
                url: data.url,
                opacity: 1,
                imageAspect: null,
                box: { x: 0.2, y: 0.2 + state.overlays.length * 0.05, w: 0.4, h: 0.3 },
            };
            item.box.y = clamp(item.box.y, 0, 0.65);
            state.overlays.push(item);
            state.selectedOverlayId = item.id;
            state.selectedTextId = null;
            await ensureOverlayImages();
            applyOverlayAspect(item);
            renderOverlayList();
            syncOverlayForm();
            showStep(4);
            setStatus('');
        } catch (e) {
            setStatus(i18n.uploadError || 'Upload failed');
        }
    }

    function renderSaved(items) {
        if (!savedEl) {
            return;
        }
        const list = Array.isArray(items) ? items : cfg.savedImages || [];
        if (list.length === 0) {
            savedEl.innerHTML = '<div class="img-editor__saved-empty">—</div>';
            return;
        }
        savedEl.innerHTML = list
            .map(function (item) {
                return (
                    '<div class="img-editor__saved-item">' +
                    '<img src="' + item.url + '" alt="">' +
                    '<div class="img-editor__saved-actions">' +
                    '<button type="button" class="btn btn-sm btn-outline-primary" data-ie-load="' + item.filename + '">' + (i18n.edit || 'Edit') + '</button>' +
                    '<a class="btn btn-sm btn-outline-secondary" href="' + item.url + '" target="_blank" rel="noopener">' + (i18n.open || 'Open') + '</a>' +
                    '<button type="button" class="btn btn-sm btn-outline-danger" data-ie-delete="' + item.filename + '">' + (i18n.delete || 'Delete') + '</button>' +
                    '</div></div>'
                );
            })
            .join('');
    }

    async function loadSaved(filename) {
        const url = (cfg.loadUrlTemplate || '').replace('__FILENAME__', encodeURIComponent(filename));
        try {
            const res = await fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            if (!res.ok || !data.ok) {
                throw new Error('load failed');
            }
            currentFilename = data.filename;
            sourceUploadBaseName = baseNameFromFileName(data.filename || filename);
            Object.keys(overlayImages).forEach(function (key) {
                delete overlayImages[key];
            });
            const normalized = normalizeState(data.content || {});
            const savedW = clampOutputSize(normalized.width);
            const savedH = clampOutputSize(normalized.height);
            Object.keys(state).forEach(function (key) {
                delete state[key];
            });
            Object.assign(state, normalized);
            applyAspect(state.aspect || '9:16');
            state.width = savedW;
            state.height = savedH;
            state.frames.forEach(function (frame) {
                if (frame.square) {
                    makeFrameSquare(frame);
                }
            });
            syncCanvasSizeFields();
            setColorInputs('[data-ie-bg-color]', '[data-ie-bg-color-hex]', state.bgColor || '#1e293b');
            if (state.bgType === 'none') {
                bgImage = null;
                setBgType('none');
            } else if (state.bgType === 'image' && state.bgImageUrl) {
                bgImage = await loadImage(state.bgImageUrl);
                ensureCrop();
                setBgType('image');
            } else {
                bgImage = null;
                setBgType('color');
            }
            await ensureOverlayImages();
            if (state.lines.length || state.frames.length) {
                showStep(5);
            } else if (state.overlays.length) {
                showStep(4);
            } else if (state.texts.length) {
                showStep(3);
            } else {
                showStep(2);
            }
            render();
            window.requestAnimationFrame(function () {
                applyPreviewZoom(previewZoom);
            });
            setStatus('');
        } catch (e) {
            setStatus(i18n.loadError || 'Load failed');
        }
    }

    async function deleteSaved(filename) {
        if (!window.confirm(i18n.deleteConfirm || 'Delete?')) {
            return;
        }
        const url = (cfg.deleteUrlTemplate || '').replace('__FILENAME__', encodeURIComponent(filename));
        try {
            const res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json();
            if (!res.ok || !data.ok) {
                throw new Error('delete failed');
            }
            if (currentFilename === filename) {
                currentFilename = null;
            }
            if (Array.isArray(data.saved_images)) {
                renderSaved(data.saved_images);
            }
            setStatus(data.message || i18n.deleted || 'Deleted');
        } catch (e) {
            setStatus(i18n.deleteError || 'Delete failed');
        }
    }

    function pointerPos(e) {
        const rect = stage.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        const visualX = clientX - rect.left;
        const visualY = clientY - rect.top;
        // Convert from scaled visual coords back to layout coords inside the stage.
        const scaleX = rect.width > 0 ? stage.clientWidth / rect.width : 1;
        const scaleY = rect.height > 0 ? stage.clientHeight / rect.height : 1;
        return { x: visualX * scaleX, y: visualY * scaleY };
    }

    function onPointerDown(e) {
        if (isCropMode()) {
            const p = pointerPos(e);
            const handle = e.target.getAttribute('data-handle');
            const c = state.crop;
            const box = {
                left: (display.sourceOx + c.x * display.sourceScale) * display.cssScaleX,
                top: (display.sourceOy + c.y * display.sourceScale) * display.cssScaleY,
                w: c.w * display.sourceScale * display.cssScaleX,
                h: c.h * display.sourceScale * display.cssScaleY,
            };
            if (handle) {
                drag = { type: 'crop-resize', handle: handle, start: p, crop: Object.assign({}, c) };
            } else if (p.x >= box.left && p.x <= box.left + box.w && p.y >= box.top && p.y <= box.top + box.h) {
                drag = { type: 'crop-move', start: p, crop: Object.assign({}, c) };
            }
            e.preventDefault();
            return;
        }

        if (isRegionSelectMode()) {
            const p = pointerPos(e);
            const handle = e.target.getAttribute('data-region-handle');
            const onRegion = !!(e.target.closest && e.target.closest('[data-ie-region]'));
            if (handle && regionBox) {
                drag = {
                    type: 'region-resize',
                    handle: handle,
                    start: p,
                    box: Object.assign({}, regionBox),
                };
            } else if (onRegion && regionBox) {
                drag = {
                    type: 'region-move',
                    start: p,
                    box: Object.assign({}, regionBox),
                };
            } else {
                const x = clamp(p.x / display.drawW, 0, 1);
                const y = clamp(p.y / display.drawH, 0, 1);
                drag = {
                    type: 'region-create',
                    start: p,
                    origin: { x: x, y: y },
                };
                regionBox = { x: x, y: y, w: 0.02, h: 0.02 };
                updateRegionOverlay();
            }
            e.preventDefault();
            return;
        }

        if (!isLayerEditMode()) {
            return;
        }

        const p = pointerPos(e);

        if (step === 5) {
            const lineHandle = e.target.getAttribute('data-line-handle');
            const lineEl = e.target.closest('[data-line-id]');
            const frameEl = e.target.closest('[data-frame-id]');

            if (lineHandle && lineEl) {
                const id = lineEl.getAttribute('data-line-id');
                state.selectedLineId = id;
                state.selectedFrameId = null;
                renderLineList();
                renderFrameList();
                syncLineForm();
                syncFrameForm();
                const item = selectedLine();
                if (!item) {
                    return;
                }
                drag = {
                    type: 'line-endpoint',
                    endpoint: lineHandle,
                    start: p,
                    line: { x1: item.x1, y1: item.y1, x2: item.x2, y2: item.y2 },
                    id: id,
                };
                updateOverlays();
                e.preventDefault();
                return;
            }

            if (lineEl) {
                const id = lineEl.getAttribute('data-line-id');
                state.selectedLineId = id;
                state.selectedFrameId = null;
                renderLineList();
                renderFrameList();
                syncLineForm();
                syncFrameForm();
                const item = selectedLine();
                if (!item) {
                    return;
                }
                drag = {
                    type: 'line-move',
                    start: p,
                    line: { x1: item.x1, y1: item.y1, x2: item.x2, y2: item.y2 },
                    id: id,
                };
                updateOverlays();
                e.preventDefault();
                return;
            }

            if (frameEl) {
                const id = frameEl.getAttribute('data-frame-id');
                const handle = e.target.getAttribute('data-thandle');
                state.selectedFrameId = id;
                state.selectedLineId = null;
                renderLineList();
                renderFrameList();
                syncLineForm();
                syncFrameForm();
                const item = selectedFrame();
                if (!item) {
                    return;
                }
                drag = {
                    type: handle === 'se' ? 'layer-resize' : 'layer-move',
                    kind: 'frame',
                    start: p,
                    box: Object.assign({}, item.box),
                    aspect: item.square ? frameSquareRatio() : 0,
                    id: id,
                };
                updateOverlays();
                e.preventDefault();
            }
            return;
        }

        const layerEl = e.target.closest('[data-layer-id]');
        if (!layerEl) {
            return;
        }
        const id = layerEl.getAttribute('data-layer-id');
        const handle = e.target.getAttribute('data-thandle');

        if (step === 3) {
            state.selectedTextId = id;
            state.selectedOverlayId = null;
            renderTextList();
            syncTextForm();
            const item = selectedText();
            if (!item) {
                return;
            }
            drag = {
                type: handle === 'se' ? 'layer-resize' : 'layer-move',
                kind: 'text',
                start: p,
                box: Object.assign({}, item.box),
                id: id,
            };
        } else {
            state.selectedOverlayId = id;
            state.selectedTextId = null;
            renderOverlayList();
            syncOverlayForm();
            const item = selectedOverlay();
            if (!item) {
                return;
            }
            drag = {
                type: handle === 'se' ? 'layer-resize' : 'layer-move',
                kind: 'overlay',
                start: p,
                box: Object.assign({}, item.box),
                aspect: getOverlayLockRatio(item),
                id: id,
            };
        }
        updateOverlays();
        e.preventDefault();
    }

    function onPointerMove(e) {
        if (!drag) {
            return;
        }
        const p = pointerPos(e);
        const dx = p.x - drag.start.x;
        const dy = p.y - drag.start.y;

        if (drag.type === 'region-create') {
            const x1 = drag.origin.x;
            const y1 = drag.origin.y;
            const x2 = clamp(p.x / display.drawW, 0, 1);
            const y2 = clamp(p.y / display.drawH, 0, 1);
            regionBox = normalizeRegionBox({
                x: Math.min(x1, x2),
                y: Math.min(y1, y2),
                w: Math.max(0.02, Math.abs(x2 - x1)),
                h: Math.max(0.02, Math.abs(y2 - y1)),
            });
            updateRegionOverlay();
            e.preventDefault();
            return;
        }

        if (drag.type === 'region-move' && regionBox) {
            regionBox = normalizeRegionBox({
                x: drag.box.x + dx / display.drawW,
                y: drag.box.y + dy / display.drawH,
                w: drag.box.w,
                h: drag.box.h,
            });
            updateRegionOverlay();
            e.preventDefault();
            return;
        }

        if (drag.type === 'region-resize' && regionBox) {
            let x = drag.box.x;
            let y = drag.box.y;
            let w = drag.box.w;
            let h = drag.box.h;
            const ddx = dx / display.drawW;
            const ddy = dy / display.drawH;
            if (drag.handle === 'se') {
                w = drag.box.w + ddx;
                h = drag.box.h + ddy;
            } else if (drag.handle === 'sw') {
                w = drag.box.w - ddx;
                x = drag.box.x + ddx;
                h = drag.box.h + ddy;
            } else if (drag.handle === 'ne') {
                w = drag.box.w + ddx;
                h = drag.box.h - ddy;
                y = drag.box.y + ddy;
            } else if (drag.handle === 'nw') {
                w = drag.box.w - ddx;
                h = drag.box.h - ddy;
                x = drag.box.x + ddx;
                y = drag.box.y + ddy;
            }
            regionBox = normalizeRegionBox({ x: x, y: y, w: w, h: h });
            updateRegionOverlay();
            e.preventDefault();
            return;
        }

        if (drag.type === 'crop-move' && bgImage) {
            const scale = display.sourceScale * display.cssScaleX;
            const scaleY = display.sourceScale * display.cssScaleY;
            state.crop.x = clamp(drag.crop.x + dx / scale, 0, bgImage.naturalWidth - drag.crop.w);
            state.crop.y = clamp(drag.crop.y + dy / scaleY, 0, bgImage.naturalHeight - drag.crop.h);
            state.crop.w = drag.crop.w;
            state.crop.h = drag.crop.h;
            updateOverlays();
            e.preventDefault();
            return;
        }

        if (drag.type === 'crop-resize' && bgImage) {
            const scale = display.sourceScale * display.cssScaleX;
            const ratio = state.width / state.height;
            let x = drag.crop.x;
            let y = drag.crop.y;
            let w = drag.crop.w;
            const ddx = dx / scale;
            if (drag.handle === 'se') {
                w = drag.crop.w + ddx;
            } else if (drag.handle === 'sw') {
                w = drag.crop.w - ddx;
                x = drag.crop.x + ddx;
            } else if (drag.handle === 'ne') {
                w = drag.crop.w + ddx;
                y = drag.crop.y + (drag.crop.h - w / ratio);
            } else if (drag.handle === 'nw') {
                w = drag.crop.w - ddx;
                x = drag.crop.x + ddx;
                y = drag.crop.y + (drag.crop.h - w / ratio);
            }
            w = clamp(w, 40, bgImage.naturalWidth);
            let h = w / ratio;
            if (h > bgImage.naturalHeight) {
                h = bgImage.naturalHeight;
                w = h * ratio;
            }
            x = clamp(x, 0, bgImage.naturalWidth - w);
            y = clamp(y, 0, bgImage.naturalHeight - h);
            state.crop = { x: Math.round(x), y: Math.round(y), w: Math.round(w), h: Math.round(h) };
            updateOverlays();
            e.preventDefault();
            return;
        }

        if (drag.type === 'line-endpoint') {
            const item = state.lines.find(function (l) {
                return l.id === drag.id;
            });
            if (!item) {
                return;
            }
            const ax = clamp(p.x / display.drawW, 0, 1);
            const ay = clamp(p.y / display.drawH, 0, 1);
            if (drag.endpoint === 'start') {
                item.x1 = ax;
                item.y1 = ay;
            } else {
                item.x2 = ax;
                item.y2 = ay;
            }
            syncLineForm();
            render();
            e.preventDefault();
            return;
        }

        if (drag.type === 'line-move') {
            const item = state.lines.find(function (l) {
                return l.id === drag.id;
            });
            if (!item) {
                return;
            }
            const ddx = dx / display.drawW;
            const ddy = dy / display.drawH;
            let x1 = drag.line.x1 + ddx;
            let y1 = drag.line.y1 + ddy;
            let x2 = drag.line.x2 + ddx;
            let y2 = drag.line.y2 + ddy;
            const minX = Math.min(x1, x2);
            const maxX = Math.max(x1, x2);
            const minY = Math.min(y1, y2);
            const maxY = Math.max(y1, y2);
            if (minX < 0) {
                x1 -= minX;
                x2 -= minX;
            }
            if (maxX > 1) {
                x1 -= maxX - 1;
                x2 -= maxX - 1;
            }
            if (minY < 0) {
                y1 -= minY;
                y2 -= minY;
            }
            if (maxY > 1) {
                y1 -= maxY - 1;
                y2 -= maxY - 1;
            }
            item.x1 = clamp(x1, 0, 1);
            item.y1 = clamp(y1, 0, 1);
            item.x2 = clamp(x2, 0, 1);
            item.y2 = clamp(y2, 0, 1);
            syncLineForm();
            render();
            e.preventDefault();
            return;
        }

        const item =
            drag.kind === 'text'
                ? state.texts.find(function (t) {
                      return t.id === drag.id;
                  })
                : drag.kind === 'frame'
                  ? state.frames.find(function (f) {
                        return f.id === drag.id;
                    })
                  : state.overlays.find(function (o) {
                        return o.id === drag.id;
                    });
        if (!item) {
            return;
        }

        if (drag.type === 'layer-move') {
            item.box.x = clamp(drag.box.x + dx / display.drawW, 0, 1 - drag.box.w);
            item.box.y = clamp(drag.box.y + dy / display.drawH, 0, 1 - drag.box.h);
            if (drag.kind === 'overlay') {
                syncOverlayForm();
            }
            render();
            e.preventDefault();
            return;
        }

        if (drag.type === 'layer-resize') {
            if ((drag.kind === 'overlay' || drag.kind === 'frame') && drag.aspect > 0) {
                // Keep locked aspect: drive size from the dominant drag axis.
                const ratio = drag.aspect; // box.w / box.h
                const dxN = dx / display.drawW;
                const dyN = dy / display.drawH;
                let newW;
                let newH;
                if (Math.abs(dxN) * ratio >= Math.abs(dyN)) {
                    newW = drag.box.w + dxN;
                    newH = newW / ratio;
                } else {
                    newH = drag.box.h + dyN;
                    newW = newH * ratio;
                }
                newW = clamp(newW, 0.08, 1 - drag.box.x);
                newH = newW / ratio;
                if (newH > 1 - drag.box.y) {
                    newH = Math.max(0.05, 1 - drag.box.y);
                    newW = newH * ratio;
                    newW = clamp(newW, 0.08, 1 - drag.box.x);
                    newH = newW / ratio;
                }
                if (newH < 0.05) {
                    newH = 0.05;
                    newW = clamp(newH * ratio, 0.08, 1 - drag.box.x);
                    newH = newW / ratio;
                }
                item.box.w = newW;
                item.box.h = newH;
                if (drag.kind === 'overlay') {
                    syncOverlayForm();
                }
            } else {
                item.box.w = clamp(drag.box.w + dx / display.drawW, 0.08, 1 - drag.box.x);
                item.box.h = clamp(drag.box.h + dy / display.drawH, 0.05, 1 - drag.box.y);
            }
            render();
            e.preventDefault();
        }
    }

    function onPointerUp() {
        drag = null;
    }

    // Events
    root.querySelectorAll('[data-aspect]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            applyAspect(btn.getAttribute('data-aspect'));
            currentFilename = null;
        });
    });
    root.querySelectorAll('[data-ie-step]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            showStep(Number(btn.getAttribute('data-ie-step')));
        });
    });
    root.querySelectorAll('[data-ie-next]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            showStep(Number(btn.getAttribute('data-ie-next')));
        });
    });
    root.querySelectorAll('[data-ie-prev]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            showStep(Number(btn.getAttribute('data-ie-prev')));
        });
    });
    root.querySelectorAll('[data-ie-bg-type]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setBgType(btn.getAttribute('data-ie-bg-type'));
        });
    });
    const bgNoneCb = root.querySelector('[data-ie-bg-none]');
    if (bgNoneCb) {
        bgNoneCb.addEventListener('change', function (e) {
            if (e.target.checked) {
                setBgType('none');
            } else {
                setBgType('color');
            }
        });
    }
    bindColorPair('[data-ie-bg-color]', '[data-ie-bg-color-hex]', function (hex) {
        state.bgColor = hex;
        render();
    });
    root.querySelector('[data-ie-bg-upload]').addEventListener('change', function (e) {
        const file = e.target.files && e.target.files[0];
        if (file) {
            uploadBackground(file);
        }
        e.target.value = '';
    });
    root.querySelector('[data-ie-add-text]').addEventListener('click', addText);
    root.querySelector('[data-ie-remove-text]').addEventListener('click', removeSelectedText);
    root.querySelector('[data-ie-remove-overlay]').addEventListener('click', removeSelectedOverlay);
    const duplicateTextBtn = root.querySelector('[data-ie-duplicate-text]');
    const duplicateOverlayBtn = root.querySelector('[data-ie-duplicate-overlay]');
    const duplicateLineBtn = root.querySelector('[data-ie-duplicate-line]');
    const duplicateFrameBtn = root.querySelector('[data-ie-duplicate-frame]');
    if (duplicateTextBtn) {
        duplicateTextBtn.addEventListener('click', duplicateSelectedText);
    }
    if (duplicateOverlayBtn) {
        duplicateOverlayBtn.addEventListener('click', duplicateSelectedOverlay);
    }
    if (duplicateLineBtn) {
        duplicateLineBtn.addEventListener('click', duplicateSelectedLine);
    }
    if (duplicateFrameBtn) {
        duplicateFrameBtn.addEventListener('click', duplicateSelectedFrame);
    }
    const addLineBtn = root.querySelector('[data-ie-add-line]');
    const addFrameBtn = root.querySelector('[data-ie-add-frame]');
    const removeLineBtn = root.querySelector('[data-ie-remove-line]');
    const removeFrameBtn = root.querySelector('[data-ie-remove-frame]');
    if (addLineBtn) {
        addLineBtn.addEventListener('click', addLine);
    }
    if (addFrameBtn) {
        addFrameBtn.addEventListener('click', addFrame);
    }
    if (removeLineBtn) {
        removeLineBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            removeSelectedLine();
        });
    }
    if (removeFrameBtn) {
        removeFrameBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            removeSelectedFrame();
        });
    }
    window.addEventListener('keydown', function (e) {
        if (e.key !== 'Delete' && e.key !== 'Backspace') {
            return;
        }
        const tag = (e.target && e.target.tagName ? e.target.tagName : '').toLowerCase();
        if (tag === 'input' || tag === 'textarea' || tag === 'select' || (e.target && e.target.isContentEditable)) {
            return;
        }
        if (removeSelectedShapeOrLayer()) {
            e.preventDefault();
        }
    });
    root.querySelector('[data-ie-overlay-upload]').addEventListener('change', function (e) {
        const file = e.target.files && e.target.files[0];
        if (file) {
            uploadOverlay(file);
        }
        e.target.value = '';
    });
    function refreshSelectedTextBox(fit) {
        const item = selectedText();
        if (!item) {
            return;
        }
        if (fit) {
            fitTextBoxToContent(item);
        }
        renderTextList();
        render();
    }

    root.querySelector('[data-ie-text]').addEventListener('input', function (e) {
        const item = selectedText();
        if (!item) {
            return;
        }
        item.text = e.target.value;
        refreshSelectedTextBox(true);
    });
    bindColorPair('[data-ie-text-color]', '[data-ie-text-color-hex]', function (hex) {
        const item = selectedText();
        if (!item) {
            return;
        }
        item.color = hex;
        render();
    });
    root.querySelector('[data-ie-text-align]').addEventListener('change', function (e) {
        const item = selectedText();
        if (!item) {
            return;
        }
        item.align = e.target.value;
        refreshSelectedTextBox(true);
    });
    root.querySelector('[data-ie-text-font]').addEventListener('change', function (e) {
        const item = selectedText();
        if (!item) {
            return;
        }
        item.font = normalizeFontId(e.target.value);
        e.target.style.fontFamily = fontStack(item.font);
        refreshSelectedTextBox(true);
    });
    root.querySelector('[data-ie-text-size]').addEventListener('change', function (e) {
        const item = selectedText();
        if (!item) {
            return;
        }
        item.size = normalizeTextSizeId(e.target.value);
        refreshSelectedTextBox(true);
    });
    root.querySelector('[data-ie-text-weight]').addEventListener('change', function (e) {
        const item = selectedText();
        if (!item) {
            return;
        }
        item.weight = normalizeFontWeight(e.target.value);
        refreshSelectedTextBox(true);
    });
    const overlayHeightEl = root.querySelector('[data-ie-overlay-height]');
    if (overlayHeightEl) {
        overlayHeightEl.addEventListener('change', function () {
            const item = selectedOverlay();
            if (!item) {
                return;
            }
            setOverlayHeightPx(item, overlayHeightEl.value);
            syncOverlayForm();
            render();
        });
        overlayHeightEl.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') {
                return;
            }
            e.preventDefault();
            const item = selectedOverlay();
            if (!item) {
                return;
            }
            setOverlayHeightPx(item, overlayHeightEl.value);
            syncOverlayForm();
            render();
        });
    }
    root.querySelector('[data-ie-opacity]').addEventListener('input', function (e) {
        const item = selectedOverlay();
        if (!item) {
            return;
        }
        item.opacity = clamp(Number(e.target.value) / 100, 0, 1);
        const label = root.querySelector('[data-ie-opacity-label]');
        if (label) {
            label.textContent = Math.round(item.opacity * 100) + '%';
        }
        renderOverlayList();
        render();
    });

    function bindLineCoord(attr, key) {
        const el = root.querySelector(attr);
        if (!el) {
            return;
        }
        el.addEventListener('input', function (e) {
            const item = selectedLine();
            if (!item) {
                return;
            }
            item[key] = clamp(Number(e.target.value) / 100, 0, 1);
            render();
        });
    }
    bindColorPair('[data-ie-line-color]', '[data-ie-line-color-hex]', function (hex) {
        const item = selectedLine();
        if (!item) {
            return;
        }
        item.color = hex;
        renderLineList();
        render();
    });
    root.querySelector('[data-ie-line-width]').addEventListener('input', function (e) {
        const item = selectedLine();
        if (!item) {
            return;
        }
        item.width = clamp(Math.round(Number(e.target.value) || 1), 1, 80);
        renderLineList();
        render();
    });
    bindLineCoord('[data-ie-line-x1]', 'x1');
    bindLineCoord('[data-ie-line-y1]', 'y1');
    bindLineCoord('[data-ie-line-x2]', 'x2');
    bindLineCoord('[data-ie-line-y2]', 'y2');

    bindColorPair('[data-ie-frame-border-color]', '[data-ie-frame-border-color-hex]', function (hex) {
        const item = selectedFrame();
        if (!item) {
            return;
        }
        item.borderColor = hex;
        render();
    });
    root.querySelector('[data-ie-frame-border-width]').addEventListener('input', function (e) {
        const item = selectedFrame();
        if (!item) {
            return;
        }
        item.borderWidth = clamp(Math.round(numOr(e.target.value, 0)), 0, 80);
        renderFrameList();
        render();
    });
    root.querySelector('[data-ie-frame-radius]').addEventListener('input', function (e) {
        const item = selectedFrame();
        if (!item) {
            return;
        }
        item.borderRadius = clamp(Math.round(numOr(e.target.value, 0)), 0, 400);
        renderFrameList();
        render();
    });
    const frameSquareEl = root.querySelector('[data-ie-frame-square]');
    if (frameSquareEl) {
        frameSquareEl.addEventListener('change', function (e) {
            const item = selectedFrame();
            if (!item) {
                return;
            }
            item.square = !!e.target.checked;
            if (item.square) {
                makeFrameSquare(item);
            }
            syncFrameForm();
            renderFrameList();
            render();
        });
    }
    root.querySelector('[data-ie-frame-fill-enabled]').addEventListener('change', function (e) {
        const item = selectedFrame();
        if (!item) {
            return;
        }
        item.fillEnabled = !!e.target.checked;
        syncFrameForm();
        renderFrameList();
        render();
    });
    bindColorPair('[data-ie-frame-fill-color]', '[data-ie-frame-fill-color-hex]', function (hex) {
        const item = selectedFrame();
        if (!item) {
            return;
        }
        item.fillColor = hex;
        render();
    });

    root.querySelectorAll('[data-ie-save]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            save(btn.getAttribute('data-ie-save'));
        });
    });
    textListEl.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-select-text]');
        if (!btn) {
            return;
        }
        state.selectedTextId = btn.getAttribute('data-select-text');
        const item = selectedText();
        if (item) {
            fitTextBoxToContent(item);
        }
        renderTextList();
        syncTextForm();
        render();
    });
    overlayListEl.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-select-overlay]');
        if (!btn) {
            return;
        }
        state.selectedOverlayId = btn.getAttribute('data-select-overlay');
        renderOverlayList();
        syncOverlayForm();
        updateOverlays();
    });
    if (lineListEl) {
        lineListEl.addEventListener('click', function (e) {
            const delBtn = e.target.closest('[data-delete-line]');
            if (delBtn) {
                e.preventDefault();
                e.stopPropagation();
                removeLineById(delBtn.getAttribute('data-delete-line'));
                return;
            }
            const btn = e.target.closest('[data-select-line]');
            if (!btn) {
                return;
            }
            state.selectedLineId = btn.getAttribute('data-select-line');
            state.selectedFrameId = null;
            renderLineList();
            renderFrameList();
            syncLineForm();
            syncFrameForm();
            updateOverlays();
        });
    }
    if (frameListEl) {
        frameListEl.addEventListener('click', function (e) {
            const delBtn = e.target.closest('[data-delete-frame]');
            if (delBtn) {
                e.preventDefault();
                e.stopPropagation();
                removeFrameById(delBtn.getAttribute('data-delete-frame'));
                return;
            }
            const btn = e.target.closest('[data-select-frame]');
            if (!btn) {
                return;
            }
            state.selectedFrameId = btn.getAttribute('data-select-frame');
            state.selectedLineId = null;
            renderLineList();
            renderFrameList();
            syncLineForm();
            syncFrameForm();
            updateOverlays();
        });
    }
    savedEl.addEventListener('click', function (e) {
        const loadBtn = e.target.closest('[data-ie-load]');
        if (loadBtn) {
            loadSaved(loadBtn.getAttribute('data-ie-load'));
            return;
        }
        const delBtn = e.target.closest('[data-ie-delete]');
        if (delBtn) {
            deleteSaved(delBtn.getAttribute('data-ie-delete'));
        }
    });

    stage.addEventListener('mousedown', onPointerDown);
    stage.addEventListener('touchstart', onPointerDown, { passive: false });
    window.addEventListener('mousemove', onPointerMove);
    window.addEventListener('touchmove', onPointerMove, { passive: false });
    window.addEventListener('mouseup', onPointerUp);
    window.addEventListener('touchend', onPointerUp);
    window.addEventListener('resize', updateOverlays);

    if (regionToggleBtn) {
        regionToggleBtn.addEventListener('click', function () {
            setRegionSelectMode(!regionSelectMode);
        });
    }
    if (regionCancelBtn) {
        regionCancelBtn.addEventListener('click', function () {
            setRegionSelectMode(false);
        });
    }
    if (regionSaveBtn) {
        regionSaveBtn.addEventListener('click', function () {
            saveRegionPng();
        });
    }
    if (canvasHeightEl) {
        canvasHeightEl.addEventListener('change', function () {
            setCanvasHeight(canvasHeightEl.value);
            currentFilename = null;
        });
        canvasHeightEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                setCanvasHeight(canvasHeightEl.value);
                currentFilename = null;
            }
        });
    }
    if (regionHeightEl) {
        regionHeightEl.addEventListener('input', function () {
            if (!regionSourceHeight) {
                return;
            }
            const h = clampOutputSize(regionHeightEl.value);
            regionHeightEl.value = String(h);
            if (regionWidthEl) {
                regionWidthEl.value = String(
                    Math.max(1, Math.round(regionSourceWidth * (h / regionSourceHeight)))
                );
            }
        });
    }

    if (zoomInBtn) {
        zoomInBtn.addEventListener('click', function () {
            zoomBy(ZOOM_STEP);
        });
    }
    if (zoomOutBtn) {
        zoomOutBtn.addEventListener('click', function () {
            zoomBy(-ZOOM_STEP);
        });
    }
    if (zoomResetBtn) {
        zoomResetBtn.addEventListener('click', function () {
            applyPreviewZoom(ZOOM_DEFAULT);
            if (viewport) {
                viewport.scrollLeft = 0;
                viewport.scrollTop = 0;
            }
        });
    }
    if (viewport) {
        viewport.addEventListener(
            'wheel',
            function (e) {
                if (!(e.ctrlKey || e.metaKey)) {
                    return;
                }
                e.preventDefault();
                const delta = e.deltaY < 0 ? ZOOM_STEP : -ZOOM_STEP;
                zoomBy(delta, { x: e.clientX, y: e.clientY });
            },
            { passive: false },
        );
    }

    applyAspect(state.aspect || '9:16');
    syncCanvasSizeFields();
    setBgType(state.bgType || 'color');
    setColorInputs('[data-ie-bg-color]', '[data-ie-bg-color-hex]', state.bgColor || '#1e293b');
    applyPreviewZoom(ZOOM_DEFAULT);
    renderTextList();
    renderOverlayList();
    renderLineList();
    renderFrameList();
    renderSaved(cfg.savedImages || []);
    showStep(1);

    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(function () {
            render();
        }).catch(function () {});
    }
})();
