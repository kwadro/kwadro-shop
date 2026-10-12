(function () {
    'use strict';

    function findFieldRow(property) {
        const input = document.querySelector(
            '[name="ProductImport[' + property + ']"], [name="ProductImport[' + property + '][]"]'
        );
        if (!input) {
            return null;
        }

        return input.closest('.form-group, .field-boolean, .field-choice, .field-textarea, .ea-form-field, .form-widget')
            || input.parentElement;
    }

    function isYesValue(raw) {
        if (raw === true || raw === 1 || raw === '1' || raw === 'true' || raw === 'yes' || raw === 'on') {
            return true;
        }

        return false;
    }

    function syncDisabledSlugsVisibility() {
        const inputs = document.querySelectorAll('[name="ProductImport[disableCategoriesAfterImport]"]');
        const slugsRow = findFieldRow('disabledCategorySlugsText');
        if (!inputs.length || !slugsRow) {
            return;
        }

        const toggle = inputs[0];
        let yes = false;
        if (toggle.type === 'checkbox') {
            yes = toggle.checked;
        } else if (toggle.tagName === 'SELECT') {
            yes = isYesValue(toggle.value);
        } else {
            const checked = document.querySelector('[name="ProductImport[disableCategoriesAfterImport]"]:checked');
            yes = checked ? isYesValue(checked.value) : isYesValue(toggle.value);
        }

        slugsRow.style.display = yes ? '' : 'none';
    }

    function init() {
        syncDisabledSlugsVisibility();

        document.querySelectorAll('[name="ProductImport[disableCategoriesAfterImport]"]').forEach(function (el) {
            el.addEventListener('change', syncDisabledSlugsVisibility);
        });
    }

    document.addEventListener('DOMContentLoaded', init);
    document.addEventListener('ea.collection.item-added', init);
})();
