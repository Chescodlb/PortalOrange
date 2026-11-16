/* Apply filters with normal GET navigation; wait for a pause when typing a search. */
(function() {
    const init = function() {
        document.querySelectorAll('form[data-orange-auto-filter="1"]').forEach(form => {
            if (form.dataset.orangeFiltersReady) { return; }
            form.dataset.orangeFiltersReady = '1';
            let submitting = false;
            let searchTimer;
            form.addEventListener('submit', event => {
                clearTimeout(searchTimer);
                if (submitting) { event.preventDefault(); return; }
                submitting = true;
            });
            window.addEventListener('pageshow', () => { submitting = false; });
            const scheduleSearch = event => {
                if (!event.target.matches('input[type="search"]')) { return; }
                clearTimeout(searchTimer);
                if (event.isComposing || submitting) { return; }
                searchTimer = setTimeout(() => {
                    if (!submitting && form.checkValidity()) { form.requestSubmit(); }
                }, 700);
            };
            form.addEventListener('input', scheduleSearch);
            form.addEventListener('compositionend', scheduleSearch);
            form.addEventListener('keydown', event => {
                if (event.key === 'Enter' && !event.isComposing && event.target.matches('input[type="search"]')) {
                    event.preventDefault();
                    if (!submitting) { form.requestSubmit(); }
                }
            });
            form.addEventListener('change', event => {
                if (!event.target.matches('select,input[type="checkbox"],input[type="date"]') || submitting) { return; }
                const period = form.querySelector('[name="period"]');
                if (period && period.value === 'custom') {
                    const from = form.querySelector('[name="report_from"]');
                    const to = form.querySelector('[name="report_to"]');
                    // Let users finish both dates before asking the server for a custom period.
                    if (!from || !to || !from.value || !to.value || from.value > to.value) { return; }
                }
                if (!form.checkValidity()) { return; }
                form.requestSubmit();
            });
        });
    };
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init, {once: true}); }
    else { init(); }
})();
