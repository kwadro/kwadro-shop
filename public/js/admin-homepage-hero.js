(() => {
    const clampPosition = (value) => {
        const n = Number.parseInt(String(value ?? ''), 10);
        if (Number.isNaN(n)) {
            return 70;
        }
        return Math.max(0, Math.min(100, n));
    };

    const emptyLabel = (preview) => preview?.dataset.emptyLabel || 'No image';

    const applyPosition = (slide) => {
        if (!(slide instanceof Element)) {
            return;
        }
        const positionInput = slide.querySelector('input[data-homepage-hero-image-position]');
        const img = slide.querySelector('[data-homepage-hero-preview-img]');
        if (!(img instanceof HTMLImageElement)) {
            return;
        }
        img.style.objectPosition = `${clampPosition(positionInput?.value)}% center`;
    };

    const setRemoveRowVisible = (slide, visible) => {
        const row = slide.querySelector('[data-homepage-hero-remove-row]');
        if (!(row instanceof HTMLElement)) {
            return;
        }
        row.classList.toggle('is-hidden', !visible);
    };

    const showEmptyPreview = (slide) => {
        const preview = slide.querySelector('[data-homepage-hero-preview]');
        if (!(preview instanceof HTMLElement)) {
            return;
        }
        const prevImg = preview.querySelector('[data-homepage-hero-preview-img]');
        if (prevImg instanceof HTMLImageElement && prevImg.dataset.objectUrl) {
            URL.revokeObjectURL(prevImg.dataset.objectUrl);
        }
        preview.classList.add('homepage-hero-slide__preview--empty');
        preview.innerHTML = `<span data-homepage-hero-preview-empty>${emptyLabel(preview)}</span>`;
        setRemoveRowVisible(slide, false);
    };

    const showFilePreview = (slide, file) => {
        const preview = slide.querySelector('[data-homepage-hero-preview]');
        if (!(preview instanceof HTMLElement) || !(file instanceof File)) {
            return;
        }

        const url = URL.createObjectURL(file);
        let img = preview.querySelector('[data-homepage-hero-preview-img]');
        if (!(img instanceof HTMLImageElement)) {
            preview.innerHTML = '';
            img = document.createElement('img');
            img.setAttribute('data-homepage-hero-preview-img', '');
            img.alt = '';
            preview.appendChild(img);
        }

        const previous = img.dataset.objectUrl;
        if (previous) {
            URL.revokeObjectURL(previous);
        }

        // Force a paint cycle so a second selection always refreshes.
        img.removeAttribute('src');
        img.dataset.objectUrl = url;
        img.src = url;
        preview.classList.remove('homepage-hero-slide__preview--empty');
        setRemoveRowVisible(slide, true);
        applyPosition(slide);

        const removeCheckbox = slide.querySelector('input[data-homepage-hero-remove]');
        if (removeCheckbox instanceof HTMLInputElement) {
            removeCheckbox.checked = false;
        }
    };

    const clearFileInput = (input) => {
        if (!(input instanceof HTMLInputElement)) {
            return;
        }
        input.value = '';
    };

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }

        const fileInput = target.closest('input[type="file"][data-homepage-hero-file]');
        if (fileInput instanceof HTMLInputElement) {
            // Allow re-selecting the same file to fire "change" again.
            clearFileInput(fileInput);
            return;
        }

        const removeBtn = target.closest('[data-homepage-hero-remove-btn]');
        if (!(removeBtn instanceof Element)) {
            return;
        }
        const slide = removeBtn.closest('[data-homepage-hero-slide]');
        if (!(slide instanceof Element)) {
            return;
        }

        event.preventDefault();
        const removeCheckbox = slide.querySelector('input[data-homepage-hero-remove]');
        if (removeCheckbox instanceof HTMLInputElement) {
            removeCheckbox.checked = true;
        }
        clearFileInput(slide.querySelector('input[type="file"][data-homepage-hero-file]'));
        showEmptyPreview(slide);
    });

    document.addEventListener('change', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement)) {
            return;
        }

        if (target.matches('input[type="file"][data-homepage-hero-file]')) {
            const slide = target.closest('[data-homepage-hero-slide]');
            if (!(slide instanceof Element)) {
                return;
            }
            const file = target.files && target.files[0] ? target.files[0] : null;
            if (!file || !file.type.startsWith('image/')) {
                return;
            }
            showFilePreview(slide, file);
            return;
        }

        if (target.matches('input[data-homepage-hero-image-position]')) {
            applyPosition(target.closest('[data-homepage-hero-slide]'));
            return;
        }

        if (target.matches('input[data-homepage-hero-remove]')) {
            const slide = target.closest('[data-homepage-hero-slide]');
            if (!(slide instanceof Element)) {
                return;
            }
            if (target.checked) {
                clearFileInput(slide.querySelector('input[type="file"][data-homepage-hero-file]'));
                showEmptyPreview(slide);
            }
        }
    });

    document.addEventListener('input', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement)) {
            return;
        }
        if (target.matches('input[data-homepage-hero-image-position]')) {
            applyPosition(target.closest('[data-homepage-hero-slide]'));
        }
    });

    const boot = () => {
        document.querySelectorAll('[data-homepage-hero-slide]').forEach((slide) => {
            applyPosition(slide);
            const hasImage = !!slide.querySelector('[data-homepage-hero-preview-img]');
            setRemoveRowVisible(slide, hasImage);
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
