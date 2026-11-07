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
        const slugsRow = findFieldRow('disabledCategorySlugs');
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

    function cacheAttributeOptions(select) {
        if (select._paramValueAttributes) {
            return;
        }

        select._paramValueAttributes = Array.from(select.options).filter(function (option) {
            return option.value !== '';
        }).map(function (option) {
            return {
                value: option.value,
                text: option.text,
                name: option.getAttribute('data-attribute-name') || option.text,
                categoryId: option.getAttribute('data-category-id') || '',
            };
        });
    }

    function applyAttributeFilter(select) {
        if (!select || !select.name || select.name.indexOf('[attribute_id]') === -1) {
            return;
        }

        cacheAttributeOptions(select);
        const item = select.closest('.field-collection-item');
        const category = item ? item.querySelector('[name$="[category_id]"]') : null;
        const categoryId = category ? String(category.value || '') : '';
        const current = String(select.value || '');
        const allowed = select._paramValueAttributes.filter(function (option) {
            return categoryId === '' || option.categoryId === categoryId;
        });
        const keepCurrent = allowed.some(function (option) {
            return option.value === current;
        });
        const tom = select.tomselect;

        if (!tom) {
            Array.from(select.options).forEach(function (option) {
                if (option.value === '') {
                    return;
                }
                const visible = categoryId === '' || option.getAttribute('data-category-id') === categoryId;
                option.hidden = !visible;
                option.disabled = !visible;
            });
            if (current !== '' && !keepCurrent) {
                select.value = '';
            }

            return;
        }

        tom.clear(true);
        tom.clearOptions();
        allowed.forEach(function (option) {
            tom.addOption({
                value: option.value,
                text: categoryId === '' ? option.text : option.name,
            });
        });
        if (keepCurrent) {
            tom.setValue(current, true);
        }
        tom.refreshOptions(false);
    }

    function filterAllAttributeSelects(root) {
        (root || document).querySelectorAll('[name$="[attribute_id]"]').forEach(applyAttributeFilter);
    }

    function init() {
        syncDisabledSlugsVisibility();
        filterAllAttributeSelects(document);

        document.querySelectorAll('[name="ProductImport[disableCategoriesAfterImport]"]').forEach(function (el) {
            el.addEventListener('change', syncDisabledSlugsVisibility);
        });
    }

    document.addEventListener('DOMContentLoaded', init);
    document.addEventListener('ea.collection.item-added', function (event) {
        init();
        filterAllAttributeSelects(event.target || document);
    });
    document.addEventListener('change', function (event) {
        const target = event.target;
        if (!target || !target.name || target.name.indexOf('[category_id]') === -1) {
            return;
        }
        const item = target.closest('.field-collection-item');
        if (!item) {
            return;
        }
        applyAttributeFilter(item.querySelector('[name$="[attribute_id]"]'));
    });
    document.addEventListener('ea.autocomplete.connect', function (event) {
        const select = event.target;
        if (!select || !select.name || select.name.indexOf('[paramValueReplacements]') === -1) {
            return;
        }
        if (select.name.indexOf('[attribute_id]') !== -1) {
            cacheAttributeOptions(select);
            applyAttributeFilter(select);

            return;
        }
        if (select.name.indexOf('[category_id]') === -1 || !event.detail || !event.detail.tomSelect) {
            return;
        }
        event.detail.tomSelect.on('change', function () {
            const item = select.closest('.field-collection-item');
            if (!item) {
                return;
            }
            applyAttributeFilter(item.querySelector('[name$="[attribute_id]"]'));
        });
    });
})();
