(function () {
    'use strict';

    const config = window.PromoImageEditorConfig;
    if (!config || !config.defaults) {
        return;
    }

    const root = document.getElementById('promo-image-editor');
    const canvas = document.getElementById('promo-canvas');
    if (!root || !canvas) {
        return;
    }

    const ctx = canvas.getContext('2d');
    const size = config.canvasSize || 526;
    canvas.width = size;
    canvas.height = size;

    const state = structuredClone(config.defaults);
    const images = {
        background: null,
        product: null,
    };

    const statusEl = root.querySelector('[data-promo-status]');
    const saveBtn = root.querySelector('[data-promo-save]');
    const saveNewBtn = root.querySelector('[data-promo-save-new]');
    const resetBtn = root.querySelector('[data-promo-reset]');
    const editingBadge = root.querySelector('[data-promo-editing-badge]');
    const savedGrid = root.querySelector('[data-promo-saved-grid]');

    let editingFilename = null;

    function setStatus(message, ok) {
        if (!statusEl) {
            return;
        }
        statusEl.hidden = !message;
        statusEl.textContent = message || '';
        statusEl.classList.toggle('is-ok', !!ok);
        statusEl.classList.toggle('is-error', !!message && !ok);
    }

    function updateEditingUi() {
        root.querySelectorAll('[data-promo-saved-item]').forEach((el) => {
            el.classList.toggle('is-active', el.getAttribute('data-filename') === editingFilename);
        });
        if (editingBadge) {
            if (editingFilename) {
                editingBadge.hidden = false;
                editingBadge.textContent = (config.i18n.editing || 'Editing') + ': ' + editingFilename;
            } else {
                editingBadge.hidden = true;
                editingBadge.textContent = '';
            }
        }
        if (saveBtn) {
            saveBtn.textContent = editingFilename
                ? (config.i18n.saveUpdate || 'Update')
                : (config.i18n.save || 'Save');
        }
        if (saveNewBtn) {
            saveNewBtn.hidden = !editingFilename;
        }
    }

    function applyContent(content) {
        const next = Object.assign(structuredClone(config.defaults), content || {});
        if (!Array.isArray(next.features)) {
            next.features = config.defaults.features.slice();
        }
        while (next.features.length < 5) {
            next.features.push('');
        }
        next.features = next.features.slice(0, 5);
        next.featuresOffsetY = Math.max(0, Math.min(100, Math.round(Number(next.featuresOffsetY) || 0)));
        Object.keys(state).forEach((key) => {
            delete state[key];
        });
        Object.assign(state, next);
        fillForm();
    }

    function fillForm() {
        root.querySelectorAll('[data-promo-field]').forEach((input) => {
            const key = input.getAttribute('data-promo-field');
            input.value = state[key] ?? '';
        });
        root.querySelectorAll('[data-promo-feature]').forEach((input) => {
            const idx = Number(input.getAttribute('data-promo-feature'));
            input.value = (state.features && state.features[idx]) || '';
        });
        root.querySelectorAll('[data-promo-toggle]').forEach((input) => {
            const key = input.getAttribute('data-promo-toggle');
            input.checked = !!state[key];
        });
        root.querySelectorAll('[data-promo-number]').forEach((input) => {
            const key = input.getAttribute('data-promo-number');
            const value = Number(state[key] ?? 0);
            input.value = String(Number.isFinite(value) ? Math.max(0, Math.min(100, Math.round(value))) : 0);
        });
    }

    function readForm() {
        root.querySelectorAll('[data-promo-field]').forEach((input) => {
            const key = input.getAttribute('data-promo-field');
            state[key] = input.value;
        });
        state.features = [];
        root.querySelectorAll('[data-promo-feature]').forEach((input) => {
            state.features.push(input.value.trim());
        });
        root.querySelectorAll('[data-promo-toggle]').forEach((input) => {
            const key = input.getAttribute('data-promo-toggle');
            state[key] = input.checked;
        });
        root.querySelectorAll('[data-promo-number]').forEach((input) => {
            const key = input.getAttribute('data-promo-number');
            let value = parseInt(input.value, 10);
            if (!Number.isFinite(value)) {
                value = 0;
            }
            value = Math.max(0, Math.min(100, value));
            state[key] = value;
            input.value = String(value);
        });
    }

    function loadImage(url) {
        return new Promise((resolve, reject) => {
            if (!url) {
                resolve(null);
                return;
            }
            const img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = () => resolve(img);
            img.onerror = () => reject(new Error('image load failed: ' + url));
            img.src = url + (url.includes('?') ? '&' : '?') + 't=' + Date.now();
        });
    }

    /** Make near-white / light-gray studio backdrop transparent. */
    function removeNearWhiteBackground(img, threshold) {
        if (!img) {
            return null;
        }
        const limit = typeof threshold === 'number' ? threshold : 235;
        const off = document.createElement('canvas');
        off.width = img.width || img.naturalWidth;
        off.height = img.height || img.naturalHeight;
        const octx = off.getContext('2d', { willReadFrequently: true });
        if (!octx || !off.width || !off.height) {
            return img;
        }
        octx.drawImage(img, 0, 0);
        let data;
        try {
            data = octx.getImageData(0, 0, off.width, off.height);
        } catch (e) {
            return img;
        }
        const px = data.data;
        for (let i = 0; i < px.length; i += 4) {
            const r = px[i];
            const g = px[i + 1];
            const b = px[i + 2];
            const max = Math.max(r, g, b);
            const min = Math.min(r, g, b);
            const chroma = max - min;
            // Near-white / light gray with low color saturation
            if (min >= limit && chroma <= 18) {
                px[i + 3] = 0;
            } else if (min >= limit - 20 && chroma <= 12) {
                px[i + 3] = Math.min(px[i + 3], Math.round(((limit - min) / 20) * 255));
            }
        }
        octx.putImageData(data, 0, 0);
        return off;
    }

    async function reloadImages() {
        try {
            images.background = await loadImage(state.backgroundUrl);
        } catch (e) {
            images.background = null;
        }
        try {
            const productImg = await loadImage(state.productUrl);
            images.product = removeNearWhiteBackground(productImg);
        } catch (e) {
            images.product = null;
        }
        draw();
    }

    function coverImage(img, dx, dy, dw, dh) {
        if (!img) {
            return;
        }
        const iw = img.width || img.naturalWidth;
        const ih = img.height || img.naturalHeight;
        const ir = iw / ih;
        const tr = dw / dh;
        let sw = iw;
        let sh = ih;
        let sx = 0;
        let sy = 0;
        if (ir > tr) {
            sw = ih * tr;
            sx = (iw - sw) / 2;
        } else {
            sh = iw / tr;
            sy = (ih - sh) / 2;
        }
        ctx.drawImage(img, sx, sy, sw, sh, dx, dy, dw, dh);
    }

    function containImage(img, dx, dy, dw, dh) {
        if (!img) {
            return;
        }
        const iw = img.width || img.naturalWidth;
        const ih = img.height || img.naturalHeight;
        const scale = Math.min(dw / iw, dh / ih);
        const tw = iw * scale;
        const th = ih * scale;
        const tx = dx + (dw - tw) / 2;
        const ty = dy + (dh - th) / 2;
        ctx.drawImage(img, tx, ty, tw, th);
    }

    function roundRect(x, y, w, h, r) {
        const radius = Math.min(r, w / 2, h / 2);
        ctx.beginPath();
        ctx.moveTo(x + radius, y);
        ctx.arcTo(x + w, y, x + w, y + h, radius);
        ctx.arcTo(x + w, y + h, x, y + h, radius);
        ctx.arcTo(x, y + h, x, y, radius);
        ctx.arcTo(x, y, x + w, y, radius);
        ctx.closePath();
    }

    function drawBurst(cx, cy, outerR, spikes, color) {
        const innerR = outerR * 0.72;
        ctx.beginPath();
        for (let i = 0; i < spikes * 2; i += 1) {
            const r = i % 2 === 0 ? outerR : innerR;
            const a = (Math.PI * i) / spikes - Math.PI / 2;
            const x = cx + Math.cos(a) * r;
            const y = cy + Math.sin(a) * r;
            if (i === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
        }
        ctx.closePath();
        ctx.fillStyle = color;
        ctx.fill();
    }

    function drawBrush(x, y, w, h, color) {
        ctx.save();
        ctx.fillStyle = color;
        ctx.beginPath();
        ctx.moveTo(x + 8, y);
        ctx.quadraticCurveTo(x + w * 0.5, y - 4, x + w - 6, y + 2);
        ctx.quadraticCurveTo(x + w + 4, y + h * 0.5, x + w - 8, y + h);
        ctx.quadraticCurveTo(x + w * 0.45, y + h + 5, x + 4, y + h - 2);
        ctx.quadraticCurveTo(x - 4, y + h * 0.45, x + 8, y);
        ctx.fill();
        ctx.restore();
    }

    function drawLogoMark(x, y) {
        const s = 10;
        const gap = 3;
        const colors = ['#2f6fed', '#f5c518', '#8a8a8a', '#f0a020'];
        colors.forEach((color, i) => {
            const col = i % 2;
            const row = Math.floor(i / 2);
            ctx.fillStyle = color;
            ctx.fillRect(x + col * (s + gap), y + row * (s + gap), s, s);
        });
    }

    function drawFeatureIcon(cx, cy, index) {
        const r = 11;
        ctx.beginPath();
        ctx.arc(cx, cy, r, 0, Math.PI * 2);
        ctx.fillStyle = '#f5c518';
        ctx.fill();
        ctx.strokeStyle = '#1a1a1a';
        ctx.lineWidth = 1.4;
        ctx.beginPath();
        if (index === 0) {
            ctx.arc(cx, cy, 4.5, 0, Math.PI * 2);
            ctx.moveTo(cx + 5, cy);
            ctx.arc(cx, cy, 7.2, -0.6, 0.6);
        } else if (index === 1) {
            ctx.strokeRect(cx - 5, cy - 3.5, 10, 7);
            ctx.moveTo(cx - 2, cy + 4.5);
            ctx.lineTo(cx + 2, cy + 4.5);
        } else if (index === 2) {
            ctx.arc(cx, cy, 3.2, 0, Math.PI * 2);
            for (let i = 0; i < 6; i += 1) {
                const a = (Math.PI * 2 * i) / 6;
                ctx.moveTo(cx + Math.cos(a) * 4.2, cy + Math.sin(a) * 4.2);
                ctx.lineTo(cx + Math.cos(a) * 7, cy + Math.sin(a) * 7);
            }
        } else if (index === 3) {
            ctx.strokeRect(cx - 5, cy - 4, 10, 8);
            ctx.moveTo(cx - 5, cy);
            ctx.lineTo(cx + 5, cy);
            ctx.moveTo(cx, cy - 4);
            ctx.lineTo(cx, cy + 4);
        } else if (index === 4) {
            ctx.arc(cx - 1, cy, 4.5, 0.2, Math.PI * 1.7);
            ctx.moveTo(cx + 3, cy - 3);
            ctx.lineTo(cx + 6, cy - 5);
        } else {
            ctx.moveTo(cx, cy - 6);
            ctx.lineTo(cx + 5, cy - 3);
            ctx.lineTo(cx + 5, cy + 2);
            ctx.lineTo(cx, cy + 6);
            ctx.lineTo(cx - 5, cy + 2);
            ctx.lineTo(cx - 5, cy - 3);
            ctx.closePath();
            ctx.moveTo(cx - 2, cy);
            ctx.lineTo(cx - 0.2, cy + 2);
            ctx.lineTo(cx + 3, cy - 2);
        }
        ctx.stroke();
    }

    function draw() {
        ctx.clearRect(0, 0, size, size);

        if (images.background) {
            coverImage(images.background, 0, 0, size, size);
        } else {
            const g = ctx.createLinearGradient(0, 0, size, size);
            g.addColorStop(0, '#d9c3a2');
            g.addColorStop(1, '#b7c4c8');
            ctx.fillStyle = g;
            ctx.fillRect(0, 0, size, size);
        }

        // Product under text overlays (transparent studio background), shifted right
        if (images.product) {
            const pw = 250;
            const ph = 280;
            const px = 250;
            const py = 120;
            containImage(images.product, px, py, pw, ph);
        }

        // Soft light panels — features offset expands height; whole block re-centers
        const headerBottom = 85;
        const footerTop = 480;
        const featuresOffset = Math.max(0, Math.min(100, Number(state.featuresOffsetY) || 0));
        const features = (state.features || []).filter(Boolean).slice(0, 5);
        const featureStep = 32;
        const padTop = 22;
        const titleBlockH = 48;
        const modelRelY = padTop + titleBlockH; // 70
        const modelH = 24;
        const featureRelBase = modelRelY + modelH + 26; // 120
        const featureRelStart = featureRelBase + featuresOffset;
        const lastFeatureRel = features.length > 0
            ? featureRelStart + (features.length - 1) * featureStep
            : modelRelY + modelH;
        const panelHeight = Math.max(160, lastFeatureRel + 28);
        const showStock = !!(state.showStock && state.stockText);
        const stockGap = showStock ? 12 : 0;
        const stockH = showStock ? 32 : 0;
        const totalBlockH = panelHeight + stockGap + stockH;
        const available = footerTop - headerBottom;
        let panelTop = headerBottom + Math.max(8, Math.round((available - totalBlockH) / 2));
        panelTop = Math.max(headerBottom + 6, Math.min(panelTop, footerTop - totalBlockH - 6));

        const titleY = panelTop + padTop + 16;
        const modelY = panelTop + modelRelY;
        const featureStartY = panelTop + featureRelStart;
        const stockY = panelTop + panelHeight + stockGap;

        ctx.fillStyle = 'rgba(255,255,255,0.18)';
        ctx.fillRect(0, 0, size, headerBottom);
        ctx.fillStyle = 'rgba(255,255,255,0.55)';
        roundRect(12, panelTop, 230, panelHeight, 10);
        ctx.fill();

        // Brand
        drawLogoMark(16, 18);
        ctx.fillStyle = '#1a1a1a';
        ctx.font = '800 30px Montserrat, Arial, sans-serif';
        ctx.fillText(state.brandName || '', 48, 38);
        ctx.fillStyle = '#666';
        ctx.font = '600 8px Montserrat, Arial, sans-serif';
        ctx.letterSpacing = '0.5px';
        ctx.fillText((state.brandTagline || '').toUpperCase(), 48, 54);

        // Slogan — moves with features block
        ctx.save();
        ctx.translate(360, featureStartY);
        ctx.rotate(-0.08);
        ctx.fillStyle = '#1f2a44';
        ctx.font = '700 22px Caveat, cursive';
        ctx.textAlign = 'center';
        wrapText(state.slogan || '', 0, 0, 170, 22);
        ctx.restore();

        // Title + model relative to panel; features shifted by offset inside panel
        ctx.textAlign = 'left';
        ctx.fillStyle = '#111';
        ctx.font = '800 22px Montserrat, Arial, sans-serif';
        wrapText(state.title || '', 22, titleY, 200, 24);

        const model = state.model || '';
        ctx.font = '700 14px Montserrat, Arial, sans-serif';
        const modelW = Math.max(88, ctx.measureText(model).width + 18);
        ctx.fillStyle = '#f5c518';
        roundRect(22, modelY, modelW, modelH, 6);
        ctx.fill();
        ctx.fillStyle = '#111';
        ctx.fillText(model, 31, modelY + 17);

        features.forEach((text, i) => {
            const y = featureStartY + i * featureStep;
            drawFeatureIcon(34, y, i);
            ctx.fillStyle = '#111';
            ctx.font = '600 12px Montserrat, Arial, sans-serif';
            ctx.fillText(text, 52, y + 4);
        });

        if (showStock) {
            drawBrush(18, stockY, 148, stockH, '#2e9b3a');
            ctx.fillStyle = '#fff';
            ctx.font = '700 17px Montserrat, Arial, sans-serif';
            ctx.textAlign = 'left';
            ctx.fillText(state.stockText, 28, stockY + 22);
        }

        // Price badges
        if (state.showDiscount && state.discount) {
            drawBurst(400, 175, 34, 10, '#2e9b3a');
            ctx.fillStyle = '#fff';
            ctx.font = '800 16px Montserrat, Arial, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(state.discount, 400, 181);
        }

        if (state.price) {
            drawBurst(445, 235, 48, 12, '#f5c518');
            ctx.fillStyle = '#111';
            ctx.font = '800 22px Montserrat, Arial, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(state.price, 445, 242);
        }

        if (state.showOldPrice && state.oldPrice) {
            drawBrush(390, 285, 100, 28, 'rgba(255,255,255,0.92)');
            ctx.fillStyle = '#333';
            ctx.font = '700 16px Montserrat, Arial, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(state.oldPrice, 440, 305);
            ctx.strokeStyle = '#d62828';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.moveTo(405, 310);
            ctx.lineTo(475, 298);
            ctx.stroke();
        }

        // Footer bar
        ctx.fillStyle = 'rgba(255,255,255,0.82)';
        ctx.fillRect(0, 480, size, 46);
        ctx.fillStyle = '#222';
        ctx.font = '600 11px Montserrat, Arial, sans-serif';
        ctx.textAlign = 'left';
        ctx.fillText(state.footerLeft || '', 14, 508);
        ctx.textAlign = 'right';
        ctx.fillText('🌐  ' + (state.footerRight || '') + '  ›', size - 14, 508);
        ctx.textAlign = 'left';
    }

    function wrapText(text, x, y, maxWidth, lineHeight) {
        const words = String(text).split(/\s+/);
        let line = '';
        let yy = y;
        for (let n = 0; n < words.length; n += 1) {
            const test = line ? line + ' ' + words[n] : words[n];
            if (ctx.measureText(test).width > maxWidth && line) {
                ctx.fillText(line, x, yy);
                line = words[n];
                yy += lineHeight;
            } else {
                line = test;
            }
        }
        if (line) {
            ctx.fillText(line, x, yy);
        }
    }

    async function uploadFile(file, kind) {
        const body = new FormData();
        body.append('file', file);
        body.append('kind', kind);
        const res = await fetch(config.uploadUrl, {
            method: 'POST',
            body,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();
        if (!res.ok || !data.ok) {
            throw new Error((data && data.error) || config.i18n.uploadError);
        }
        return data.url;
    }

    async function saveImage(asNew) {
        readForm();
        draw();
        setStatus(config.i18n.saving, true);
        if (saveBtn) saveBtn.disabled = true;
        if (saveNewBtn) saveNewBtn.disabled = true;
        try {
            const dataUrl = canvas.toDataURL('image/jpeg', 0.92);
            const payload = { image: dataUrl, content: state };
            if (!asNew && editingFilename) {
                payload.overwrite = editingFilename;
            }
            const res = await fetch(config.saveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (!res.ok || !data.ok) {
                throw new Error((data && data.error) || config.i18n.saveError);
            }
            setStatus(data.message || (data.overwritten ? config.i18n.updated : config.i18n.saved), true);

            if (data.filename) {
                editingFilename = data.filename;
                upsertSavedItem(data.filename, data.url, true);
                updateEditingUi();
            }
        } catch (e) {
            setStatus(e.message || config.i18n.saveError, false);
        } finally {
            if (saveBtn) saveBtn.disabled = false;
            if (saveNewBtn) saveNewBtn.disabled = false;
        }
    }

    function upsertSavedItem(filename, url, hasContent) {
        if (!savedGrid) {
            return;
        }
        const empty = savedGrid.querySelector('[data-promo-saved-empty]');
        if (empty) {
            empty.remove();
        }

        let item = savedGrid.querySelector('[data-filename="' + CSS.escape(filename) + '"]');
        if (!item) {
            item = document.createElement('div');
            item.className = 'promo-editor__saved-item';
            item.setAttribute('data-promo-saved-item', '');
            item.setAttribute('data-filename', filename);
            item.innerHTML =
                '<a href="' + url + '" target="_blank" rel="noopener">' +
                '<img src="' + url + '" alt="' + filename + '">' +
                '</a>' +
                '<span class="promo-editor__saved-name">' + filename + '</span>' +
                '<div class="promo-editor__saved-actions"></div>';
            savedGrid.prepend(item);
        } else {
            const img = item.querySelector('img');
            if (img) {
                img.src = url + '?t=' + Date.now();
            }
            const link = item.querySelector('a');
            if (link) {
                link.href = url;
            }
            savedGrid.prepend(item);
        }

        const actions = item.querySelector('.promo-editor__saved-actions');
        if (actions) {
            actions.innerHTML = '';
            if (hasContent) {
                const editBtn = document.createElement('button');
                editBtn.type = 'button';
                editBtn.className = 'btn btn-sm btn-primary';
                editBtn.setAttribute('data-promo-edit', filename);
                editBtn.textContent = config.i18n.edit || 'Edit';
                actions.append(editBtn);
            }
            const open = document.createElement('a');
            open.className = 'btn btn-sm btn-outline-secondary';
            open.href = url;
            open.target = '_blank';
            open.rel = 'noopener';
            open.textContent = config.i18n.open || 'Open';
            actions.append(open);

            const del = document.createElement('button');
            del.type = 'button';
            del.className = 'btn btn-sm btn-outline-danger';
            del.setAttribute('data-promo-delete', filename);
            del.textContent = config.i18n.delete || 'Delete';
            actions.append(del);
        }
    }

    async function loadSaved(filename) {
        if (!filename || !config.loadUrlTemplate) {
            return;
        }
        setStatus('', true);
        try {
            const url = config.loadUrlTemplate.replace('__FILENAME__', encodeURIComponent(filename));
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            if (!res.ok || !data.ok) {
                throw new Error((data && data.error) || config.i18n.loadError);
            }
            editingFilename = data.filename || filename;
            applyContent(data.content || {});
            await reloadImages();
            updateEditingUi();
            setStatus((config.i18n.editing || 'Editing') + ': ' + editingFilename, true);
            root.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch (e) {
            setStatus(e.message || config.i18n.loadError, false);
        }
    }

    async function deleteSaved(filename) {
        if (!filename || !config.deleteUrlTemplate) {
            return;
        }
        const confirmText = config.i18n.deleteConfirm || 'Delete?';
        if (!window.confirm(confirmText)) {
            return;
        }
        try {
            const url = config.deleteUrlTemplate.replace('__FILENAME__', encodeURIComponent(filename));
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json();
            if (!res.ok || !data.ok) {
                throw new Error((data && data.error) || config.i18n.deleteError);
            }

            const item = savedGrid && savedGrid.querySelector('[data-filename="' + CSS.escape(filename) + '"]');
            if (item) {
                item.remove();
            }
            if (savedGrid && !savedGrid.querySelector('[data-promo-saved-item]')) {
                const empty = document.createElement('p');
                empty.className = 'text-muted mb-0';
                empty.setAttribute('data-promo-saved-empty', '');
                empty.textContent = config.i18n.savedEmpty || '';
                savedGrid.append(empty);
            }

            if (editingFilename === filename) {
                editingFilename = null;
                applyContent(config.defaults);
                await reloadImages();
                updateEditingUi();
            }

            setStatus(data.message || config.i18n.deleted, true);
        } catch (e) {
            setStatus(e.message || config.i18n.deleteError, false);
        }
    }

    root.addEventListener('input', (event) => {
        const numberInput = event.target.closest('[data-promo-number]');
        if (numberInput) {
            const key = numberInput.getAttribute('data-promo-number');
            let value = parseInt(numberInput.value, 10);
            if (!Number.isFinite(value)) {
                value = 0;
            }
            value = Math.max(0, Math.min(100, value));
            state[key] = value;
            root.querySelectorAll('[data-promo-number="' + key + '"]').forEach((el) => {
                if (el !== numberInput) {
                    el.value = String(value);
                }
            });
            draw();
            return;
        }
        readForm();
        draw();
    });
    root.addEventListener('change', () => {
        readForm();
        draw();
    });

    root.querySelectorAll('[data-promo-upload]').forEach((input) => {
        input.addEventListener('change', async () => {
            const file = input.files && input.files[0];
            const kind = input.getAttribute('data-promo-upload');
            if (!file) {
                return;
            }
            try {
                const url = await uploadFile(file, kind);
                if (kind === 'product') {
                    state.productUrl = url;
                } else {
                    state.backgroundUrl = url;
                }
                await reloadImages();
                setStatus('', true);
            } catch (e) {
                setStatus(e.message || config.i18n.uploadError, false);
            }
        });
    });

    if (saveBtn) {
        saveBtn.addEventListener('click', () => saveImage(false));
    }
    if (saveNewBtn) {
        saveNewBtn.addEventListener('click', () => saveImage(true));
    }
    if (resetBtn) {
        resetBtn.addEventListener('click', async () => {
            editingFilename = null;
            applyContent(config.defaults);
            await reloadImages();
            updateEditingUi();
            setStatus('', true);
        });
    }

    root.addEventListener('click', (event) => {
        const editBtn = event.target.closest('[data-promo-edit]');
        if (editBtn && root.contains(editBtn)) {
            event.preventDefault();
            loadSaved(editBtn.getAttribute('data-promo-edit'));
            return;
        }
        const deleteBtn = event.target.closest('[data-promo-delete]');
        if (deleteBtn && root.contains(deleteBtn)) {
            event.preventDefault();
            deleteSaved(deleteBtn.getAttribute('data-promo-delete'));
        }
    });

    // Wait for fonts then draw
    fillForm();
    updateEditingUi();
    const fontsReady = document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve();
    fontsReady.then(() => reloadImages());
})();
