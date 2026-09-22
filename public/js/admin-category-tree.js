(function () {
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
    });
})();
