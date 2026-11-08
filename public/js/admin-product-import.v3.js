(function () {
    'use strict';

    /**
     * Full attribute option catalog (value/text/name/categoryId).
     * Seeded from the first attribute <select> that still has all options.
     */
    let attributeCatalog = null;

    function isParamValueAttributeSelect(el) {
        return !!(
            el
            && el.tagName === 'SELECT'
            && el.name
            && el.name.indexOf('[paramValueReplacementGroups]') !== -1
            && el.name.indexOf('[attribute_id]') !== -1
        );
    }

    function isParamValueCategorySelect(el) {
        return !!(
            el
            && el.tagName === 'SELECT'
            && el.name
            && el.name.indexOf('[paramValueReplacementGroups]') !== -1
            && /\[category_id]$/.test(el.name)
        );
    }

    function groupIndexFromName(name) {
        const match = String(name || '').match(/\[paramValueReplacementGroups\]\[([^\]]+)\]/);
        return match ? match[1] : null;
    }

    function categorySelectForAttributeSelect(attributeSelect) {
        // Walk up to the category group collection item and take its own category select.
        let node = attributeSelect ? attributeSelect.parentElement : null;
        while (node && node !== document.body) {
            if (node.classList && node.classList.contains('field-collection-item')) {
                const selects = node.querySelectorAll(
                    'select[name*="[paramValueReplacementGroups]"][name$="[category_id]"]'
                );
                for (let i = 0; i < selects.length; i += 1) {
                    const candidate = selects[i];
                    // Must belong to THIS collection item, not a nested child item.
                    if (candidate.closest('.field-collection-item') === node) {
                        return candidate;
                    }
                }
            }
            node = node.parentElement;
        }

        // Fallback by group index in the field name.
        const groupIndex = groupIndexFromName(attributeSelect && attributeSelect.name);
        if (groupIndex === null) {
            return null;
        }
        const suffix = '[paramValueReplacementGroups][' + groupIndex + '][category_id]';
        const all = document.querySelectorAll('select');
        for (let i = 0; i < all.length; i += 1) {
            const name = all[i].name || '';
            if (name === 'ProductImport' + suffix || name.endsWith(suffix)) {
                return all[i];
            }
        }

        return null;
    }

    function readCategoryId(categorySelect) {
        if (!categorySelect) {
            return '';
        }
        if (categorySelect.tomselect) {
            const value = categorySelect.tomselect.getValue();
            return String(Array.isArray(value) ? (value[0] || '') : (value || ''));
        }

        return String(categorySelect.value || '');
    }

    function readOptionsCatalog(select) {
        return Array.from(select.options)
            .filter(function (option) {
                return option.value !== '';
            })
            .map(function (option) {
                return {
                    value: String(option.value),
                    text: String(option.textContent || '').trim(),
                    name: option.getAttribute('data-attribute-name') || String(option.textContent || '').trim(),
                    categoryId: String(option.getAttribute('data-category-id') || ''),
                };
            });
    }

    function rememberCatalog(options) {
        if (!options || options.length === 0) {
            return;
        }
        if (!attributeCatalog || options.length > attributeCatalog.length) {
            attributeCatalog = options;
        }
    }

    function ensureCatalog(select) {
        const fromSelect = readOptionsCatalog(select);
        rememberCatalog(fromSelect);
        if (attributeCatalog && attributeCatalog.length > 0) {
            return attributeCatalog;
        }

        // Fallback: dig through any existing attribute select / Tom Select instance.
        document.querySelectorAll('select[name*="[paramValueReplacementGroups]"][name$="[attribute_id]"]').forEach(function (other) {
            if (other === select) {
                return;
            }
            rememberCatalog(readOptionsCatalog(other));
            if (other.tomselect && other.tomselect.options) {
                const fromTom = Object.keys(other.tomselect.options).map(function (value) {
                    const opt = other.tomselect.options[value];
                    return {
                        value: String(value),
                        text: String(opt.text || ''),
                        name: String(opt.text || ''),
                        categoryId: '',
                    };
                }).filter(function (row) {
                    return row.value !== '';
                });
                // Tom options may lack categoryId — only use if we have nothing else.
                if (!attributeCatalog || attributeCatalog.length === 0) {
                    rememberCatalog(fromTom);
                }
            }
        });

        return attributeCatalog || [];
    }

    function placeholderOptionHtml(select) {
        const existing = select.querySelector('option[value=""]');
        if (existing) {
            return existing.outerHTML;
        }

        return '<option value=""></option>';
    }

    function writeSelectOptions(select, allowed, categoryId) {
        const selected = select.tomselect
            ? String(select.tomselect.getValue() || '')
            : String(select.value || '');
        const keep = allowed.some(function (row) {
            return row.value === selected;
        })
            ? selected
            : '';

        select.innerHTML = placeholderOptionHtml(select);
        allowed.forEach(function (row) {
            const option = document.createElement('option');
            option.value = row.value;
            option.textContent = categoryId === '' ? row.text : row.name;
            option.setAttribute('data-category-id', row.categoryId);
            option.setAttribute('data-attribute-name', row.name);
            if (row.value === keep) {
                option.selected = true;
            }
            select.appendChild(option);
        });

        return keep;
    }

    function allowedOptionsFor(select) {
        const categorySelect = categorySelectForAttributeSelect(select);
        const categoryId = readCategoryId(categorySelect);
        const catalog = ensureCatalog(select);
        const allowed = catalog.filter(function (row) {
            return categoryId === '' || row.categoryId === categoryId;
        });

        return { categoryId: categoryId, allowed: allowed };
    }

    /**
     * BEFORE Tom Select starts: shrink the native <select> options.
     * This is the only reliable way for newly added collection items.
     */
    document.addEventListener('ea.autocomplete.pre-connect', function (event) {
        const select = event.target;
        if (!isParamValueAttributeSelect(select)) {
            return;
        }

        // Capture full list first (prototype still has every option).
        rememberCatalog(readOptionsCatalog(select));
        const filtered = allowedOptionsFor(select);
        writeSelectOptions(select, filtered.allowed, filtered.categoryId);
    });

    function applyFilterToConnectedSelect(select) {
        if (!isParamValueAttributeSelect(select)) {
            return;
        }

        const filtered = allowedOptionsFor(select);
        const tom = select.tomselect;

        if (!tom) {
            writeSelectOptions(select, filtered.allowed, filtered.categoryId);
            return;
        }

        const current = String(tom.getValue() || '');
        const keep = filtered.allowed.some(function (row) {
            return row.value === current;
        })
            ? current
            : '';

        tom.clear(true);
        tom.clearOptions();
        filtered.allowed.forEach(function (row) {
            tom.addOption({
                value: row.value,
                text: filtered.categoryId === '' ? row.text : row.name,
            });
        });
        if (keep !== '') {
            tom.setValue(keep, true);
        }
        tom.refreshOptions(false);
    }

    function filterAttributeSelectsInScope(scope) {
        const root = scope && scope.querySelectorAll ? scope : document;
        root.querySelectorAll('select[name*="[paramValueReplacementGroups]"][name$="[attribute_id]"]').forEach(applyFilterToConnectedSelect);
    }

    function selectedCategoryLabel(select) {
        if (!select) {
            return '';
        }
        if (select.tomselect) {
            const value = readCategoryId(select);
            if (value === '') {
                const placeholder = select.querySelector('option[value=""]');
                return placeholder ? String(placeholder.textContent || '').trim() : '';
            }
            const option = select.tomselect.options[value];
            if (option && option.text) {
                return String(option.text).trim();
            }
        }
        const option = select.options[select.selectedIndex];
        return option ? String(option.textContent || '').trim() : '';
    }

    function updateGroupTitleForCategorySelect(categorySelect) {
        if (!categorySelect) {
            return;
        }
        const groupItem = categorySelect.closest('.field-collection-item');
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
        const label = selectedCategoryLabel(categorySelect);
        if (label !== '') {
            title.textContent = label;
        }
    }

    function updateAllGroupTitles() {
        document.querySelectorAll('select[name*="[paramValueReplacementGroups]"][name$="[category_id]"]').forEach(updateGroupTitleForCategorySelect);
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

    function onCategoryChanged(categorySelect) {
        updateGroupTitleForCategorySelect(categorySelect);
        const groupIndex = groupIndexFromName(categorySelect.name);
        if (groupIndex === null) {
            filterAttributeSelectsInScope(document);
            return;
        }
        document.querySelectorAll(
            'select[name^="ProductImport[paramValueReplacementGroups][' + groupIndex + '][attributes]"][name$="[attribute_id]"]'
        ).forEach(applyFilterToConnectedSelect);
    }

    // Seed catalog as early as possible from any rendered attribute select.
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('select[name*="[paramValueReplacementGroups]"][name$="[attribute_id]"]').forEach(function (select) {
            rememberCatalog(readOptionsCatalog(select));
        });
        syncDisabledSlugsVisibility();
        updateAllGroupTitles();
        customizeAddButtons();
        filterAttributeSelectsInScope(document);

        document.querySelectorAll('[name="ProductImport[disableCategoriesAfterImport]"]').forEach(function (el) {
            el.addEventListener('change', syncDisabledSlugsVisibility);
        });
    });

    document.addEventListener('ea.collection.item-added', function () {
        customizeAddButtons();
        // Tom Select for the new item is created in another item-added listener;
        // pre-connect handles filtering. Re-run titles/buttons afterwards.
        window.setTimeout(function () {
            updateAllGroupTitles();
            customizeAddButtons();
            filterAttributeSelectsInScope(document);
        }, 0);
    });

    document.addEventListener('change', function (event) {
        const target = event.target;
        if (!isParamValueCategorySelect(target)) {
            return;
        }
        onCategoryChanged(target);
    });

    document.addEventListener('ea.autocomplete.connect', function (event) {
        const select = event.target;
        if (isParamValueAttributeSelect(select)) {
            // Safety net if pre-connect did not run or catalog was empty then.
            applyFilterToConnectedSelect(select);
            return;
        }
        if (!isParamValueCategorySelect(select) || !event.detail || !event.detail.tomSelect) {
            return;
        }
        updateGroupTitleForCategorySelect(select);
        event.detail.tomSelect.on('change', function () {
            onCategoryChanged(select);
        });
    });
})();
