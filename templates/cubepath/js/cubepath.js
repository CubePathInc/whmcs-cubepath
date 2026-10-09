/*
 * CubePath client area: mobile menu and the active entry of the sidebar.
 */
(function () {
    var body = document.body;

    document.addEventListener('click', function (event) {
        if (event.target.closest('[data-cp-menu-open]')) {
            body.classList.add('cp-menu-open');
        } else if (event.target.closest('[data-cp-menu-close]')) {
            body.classList.remove('cp-menu-open');
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            body.classList.remove('cp-menu-open');
        }
    });

    // WHMCS does not mark the current menu entry, so pick the link that best
    // matches the address: same page and the most query parameters in common.
    function score(link) {
        var url;
        try {
            url = new URL(link.getAttribute('href'), window.location.href);
        } catch (e) {
            return -1;
        }
        if (url.origin !== window.location.origin || url.pathname !== window.location.pathname) {
            return -1;
        }

        var here = new URLSearchParams(window.location.search);
        // A VPS's page belongs to the VPS list.
        if (here.get('action') === 'productdetails') {
            here = new URLSearchParams('action=services');
        }
        // index.php?rp=/store/... and index.php?rp=/login are different pages.
        if (here.get('rp') !== url.searchParams.get('rp')) {
            return -1;
        }
        // clientarea.php is the overview, not every clientarea.php?action=... page.
        if (!url.search && here.toString() !== '') {
            return -1;
        }

        var points = 1;
        var mismatch = false;
        url.searchParams.forEach(function (value, key) {
            if (here.get(key) === value) {
                points += 2;
            } else {
                mismatch = true;
            }
        });

        return mismatch ? -1 : points;
    }

    var best = null;
    var bestScore = 0;
    document.querySelectorAll('.cp-nav a[href]').forEach(function (link) {
        var s = score(link);
        // On a tie the later link wins: a sub-entry over its section ("Invoices" over "Billing").
        if (s > 0 && s >= bestScore) {
            best = link;
            bestScore = s;
        }
    });

    if (best) {
        best.classList.add('active');
        var item = best.closest('.cp-nav-item');
        if (item) {
            item.classList.add('open');
        }
    }
})();
