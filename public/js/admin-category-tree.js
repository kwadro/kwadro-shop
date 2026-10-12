(function () {
    'use strict';

    var STORAGE_KEY = 'admin.category.tree.expanded';

    function getTreeRoot() {
        return document.querySelector('.mage-category__tree');
    }

    function readExpandedIds() {
        try {
            var raw = sessionStorage.getItem(STORAGE_KEY);
            if (!raw) {
                return null;
            }
            var parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed.map(String) : null;
        } catch (e) {
            return null;
        }
    }

    function writeExpandedIds(ids) {
        try {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
        } catch (e) {
            // ignore quota / private mode
        }
    }

    function collectExpandedIds(tree) {
        return Array.prototype.map.call(
            tree.querySelectorAll('.mage-category__node.has-children:not(.is-collapsed)'),
            function (node) {
                return String(node.getAttribute('data-id'));
            }
        );
    }

    function applyExpandedIds(tree, expandedIds) {
        var expanded = {};
        expandedIds.forEach(function (id) {
            expanded[String(id)] = true;
        });

        tree.querySelectorAll('.mage-category__node.has-children').forEach(function (node) {
            var id = String(node.getAttribute('data-id'));
            var isExpanded = !!expanded[id];
            node.classList.toggle('is-collapsed', !isExpanded);
            node.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
        });
    }

    function persistTreeState(tree) {
        writeExpandedIds(collectExpandedIds(tree));
    }

    function init() {
        var tree = getTreeRoot();
        if (!tree) {
            return;
        }

        var saved = readExpandedIds();
        if (saved !== null) {
            applyExpandedIds(tree, saved);
        } else {
            // First load in this browser tab: keep server defaults, then remember them.
            persistTreeState(tree);
        }
    }

    document.addEventListener('DOMContentLoaded', init);

    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('[data-mage-toggle]');
        if (!toggle) {
            return;
        }

        event.preventDefault();
        var node = toggle.closest('.mage-category__node');
        if (!node) {
            return;
        }

        node.classList.toggle('is-collapsed');
        node.setAttribute('aria-expanded', node.classList.contains('is-collapsed') ? 'false' : 'true');

        var tree = getTreeRoot();
        if (tree) {
            persistTreeState(tree);
        }
    });
})();
