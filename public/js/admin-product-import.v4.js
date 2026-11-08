(function () {
    'use strict';

    function isParamValueAttributeMulti(el) {
        return !!(
            el
            && el.tagName === 'SELECT'
            && el.name
            && el.name.indexOf('[paramValueReplacementGroups]') !== -1
            && el.name.indexOf('[attribute_ids]') !== -1
        );
    }

    function selectedAttributeLabels(select) {
        if (!select) {
            return [];
        }
        const labels = [];
        if (select.tomselect) {
            const values = select.tomselect.getValue();
            const list = Array.isArray(values) ? values : (values ? [values] : []);
            list.forEach(function (value) {
                const option = select.tomselect.options[value];
                if (option && option.text) {
                    labels.push(String(option.text).trim());
                }
            });
            return labels;
        }
        Array.from(select.selectedOptions || []).forEach(function (option) {
            if (option.value) {
                labels.push(String(option.textContent || '').trim());
            }
        });

        return labels;
    }

    function updateGroupTitle(groupItem) {
        if (!groupItem) {
            return;
        }
        const button = groupItem.querySelector(':scope > .accordion-item > .accordion-header > .accordion-button');
        if (!button) {
            return;
        }
        let title = button.querySelector('.param-value-group-title');
        if (!title) {
            title = document.createElement('span');
            title.className = 'param-value-group-title';
            Array.from(button.childNodes).forEach(function (node) {
                if (node.nodeType === Node.TEXT_NODE) {
                    node.textContent = '';
                }
            });
            button.appendChild(title);
        }
        const select = groupItem.querySelector('select[name*="[paramValueReplacementGroups]"][name$="[attribute_ids][]"], select[name*="[paramValueReplacementGroups]"][name*="[attribute_ids]"]');
        const labels = selectedAttributeLabels(select);
        const firstLabel = labels.length > 0 ? String(labels[0]).split(' / ').pop().trim() : '';
        const fallback = select
            ? (document.documentElement.getAttribute('lang') === 'uk' ? 'Усі атрибути' : 'All attributes')
            : '';
        title.textContent = firstLabel !== '' ? firstLabel : (title.textContent || fallback || '—');
    }

    function updateAllGroupTitles() {
        document.querySelectorAll('.field-collection-item > .accordion-item').forEach(function (accordion) {
            const groupItem = accordion.parentElement;
            if (!groupItem) {
                return;
            }
            if (!groupItem.querySelector('select[name*="[paramValueReplacementGroups]"][name*="[attribute_ids]"]')) {
                return;
            }
            updateGroupTitle(groupItem);
        });
    }

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
        return raw === true || raw === 1 || raw === '1' || raw === 'true' || raw === 'yes' || raw === 'on';
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

    function setAddButtonLabel(button, label) {
        if (!button || !label) {
            return;
        }
        const icon = button.querySelector('svg, i, .icon');
        button.textContent = '';
        if (icon) {
            button.appendChild(icon);
        }
        button.appendChild(document.createTextNode(' ' + label));
    }

    function customizeAddButtons() {
        document.querySelectorAll('[data-ea-collection-field][data-param-value-add-label]').forEach(function (collection) {
            const label = collection.getAttribute('data-param-value-add-label');
            const button = collection.querySelector(':scope > .field-collection-add-button');
            setAddButtonLabel(button, label);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        syncDisabledSlugsVisibility();
        updateAllGroupTitles();
        customizeAddButtons();
        document.querySelectorAll('[name="ProductImport[disableCategoriesAfterImport]"]').forEach(function (el) {
            el.addEventListener('change', syncDisabledSlugsVisibility);
        });
    });

    document.addEventListener('ea.collection.item-added', function () {
        window.setTimeout(function () {
            updateAllGroupTitles();
            customizeAddButtons();
        }, 0);
    });

    document.addEventListener('change', function (event) {
        const target = event.target;
        if (!isParamValueAttributeMulti(target) && !(target && target.name && target.name.indexOf('[attribute_ids]') !== -1)) {
            return;
        }
        const groupItem = target.closest('.field-collection-item');
        updateGroupTitle(groupItem);
    });

    document.addEventListener('ea.autocomplete.connect', function (event) {
        const select = event.target;
        if (!isParamValueAttributeMulti(select) || !event.detail || !event.detail.tomSelect) {
            return;
        }
        const groupItem = select.closest('.field-collection-item');
        updateGroupTitle(groupItem);
        event.detail.tomSelect.on('change', function () {
            updateGroupTitle(groupItem);
        });
    });
})();
