/* Refresh Orange's existing indicators without replacing links or interrupting the current page. */
(function() {
    const init = function() {
        const nav = document.querySelector('.orange-personal-updates[data-updates-url]');
        if (!nav || nav.dataset.liveUpdates === '1') { return; }
        nav.dataset.liveUpdates = '1';
        const interval = 30000;
        let timer;
        let controller;
        let stopped = false;
        let inFlight = false;

        const schedule = function() {
            clearTimeout(timer);
            if (!stopped && !document.hidden) { timer = setTimeout(refresh, interval); }
        };
        const refresh = async function() {
            if (stopped || document.hidden || inFlight) { return; }
            clearTimeout(timer);
            inFlight = true;
            controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 10000);
            try {
                const response = await fetch(nav.dataset.updatesUrl, {
                    method: 'POST', credentials: 'same-origin', cache: 'no-store', redirect: 'error',
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                    body: new URLSearchParams({sesskey: M.cfg.sesskey}), signal: controller.signal
                });
                if (response.status === 401 || response.status === 403) { stopped = true; return; }
                if (!response.ok) { return; }
                const data = await response.json();
                if (!Array.isArray(data.indicators)) { return; }
                for (const item of data.indicators) {
                    if (!['notifications', 'incidents', 'courses', 'library'].includes(item.key)) { continue; }
                    if (![item.count, item.total, item.critical].every(n => Number.isInteger(n) && n >= 0)) { continue; }
                    const link = nav.querySelector('[data-update="' + item.key + '"]');
                    const href = new URL(item.href, location.href);
                    if (!link || href.origin !== location.origin || typeof item.description !== 'string') { continue; }
                    link.href = href.href;
                    link.dataset.count = item.count;
                    link.dataset.total = item.total;
                    link.dataset.critical = item.critical;
                    link.classList.toggle('has-updates', item.count > 0);
                    link.classList.toggle('is-critical', item.critical > 0);
                    link.setAttribute('title', item.description);
                    link.setAttribute('aria-label', item.description);
                    const badge = link.querySelector('.orange-update-count');
                    if (badge && badge.textContent !== String(item.count)) { badge.textContent = item.count; }
                }
            } catch (error) {
                // Keep the current indicators during timeouts, offline periods or transient failures.
            } finally {
                clearTimeout(timeout);
                controller = null;
                inFlight = false;
                schedule();
            }
        };
        document.addEventListener('visibilitychange', function() {
            clearTimeout(timer);
            if (document.hidden) { controller?.abort(); }
            else { refresh(); }
        });
        window.addEventListener('focus', refresh);
        window.addEventListener('pagehide', function() { clearTimeout(timer); controller?.abort(); });
        window.addEventListener('pageshow', function(event) { if (event.persisted) { refresh(); } });
        schedule();
    };
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init, {once: true}); }
    else { init(); }
})();
