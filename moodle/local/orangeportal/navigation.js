/* Dynamic category trees reuse the server-side Moodle permission checks. */
window.OrangePortalNavigation = {
    init: function() {
        let current = null;
        let trigger = null;
        const selector = '.primary-navigation a.nav-link, .drawer-primary a.list-group-item';
        const close = function(restoreFocus) {
            if (!current) { return; }
            current.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            trigger.classList.remove('orange-nav-open');
            if (restoreFocus) { trigger.focus(); }
            current = null;
            trigger = null;
        };
        const position = function() {
            if (!current) { return; }
            const rect = trigger.getBoundingClientRect();
            const width = Math.min(560, window.innerWidth - 32);
            const top = Math.min(rect.bottom + 8, window.innerHeight - 160);
            current.style.width = width + 'px';
            current.style.left = Math.max(16, Math.min(rect.left, window.innerWidth - width - 16)) + 'px';
            current.style.top = Math.max(64, top) + 'px';
            current.style.maxHeight = Math.max(120, window.innerHeight - Math.max(64, top) - 16) + 'px';
        };
        document.querySelectorAll(selector).forEach(function(link) {
            if (!link.getAttribute('href') || link.getAttribute('href').startsWith('#')) { return; }
            const url = new URL(link.href, window.location.href);
            if (!url.pathname.endsWith('/local/orangeportal/index.php')) { return; }
            const panel = document.getElementById('orange-menu-' + url.searchParams.get('section'));
            if (!panel) { return; }
            link.classList.add('orange-nav-trigger');
            link.setAttribute('aria-controls', panel.id);
            link.setAttribute('aria-expanded', 'false');
            link.addEventListener('click', function(event) {
                if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) { return; }
                event.preventDefault();
                const wasOpen = current === panel && trigger === link;
                close(false);
                if (wasOpen) { return; }
                current = panel;
                trigger = link;
                panel.hidden = false;
                link.setAttribute('aria-expanded', 'true');
                link.classList.add('orange-nav-open');
                position();
            });
            link.addEventListener('keydown', function(event) {
                if (event.key === 'ArrowDown' || event.key === ' ') {
                    event.preventDefault();
                    event.stopPropagation();
                    if (current !== panel) { link.click(); }
                    panel.querySelector('a').focus();
                }
            });
        });
        document.addEventListener('click', function(event) {
            if (current && !current.contains(event.target) && !trigger.contains(event.target)) { close(false); }
        });
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && current) { event.preventDefault(); close(true); }
        });
        document.addEventListener('focusin', function(event) {
            if (current && !current.contains(event.target) && event.target !== trigger) { close(false); }
        });
        window.addEventListener('resize', function() { close(false); });
        window.addEventListener('scroll', position, true);
    }
};
