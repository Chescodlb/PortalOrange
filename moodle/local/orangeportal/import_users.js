// Progressive enhancement: each authenticated POST creates at most one small batch.
(() => {
    const init = () => {
        const runner = document.getElementById('orange-import-runner');
        if (!runner) { return; }
        const form = Array.from(runner.querySelectorAll('form')).find(item =>
            item.querySelector('input[name="action"]')?.value === 'process');
        if (!form) { return; }
        const status = document.getElementById('orange-import-status');
        const progress = document.getElementById('orange-import-progress');
        const error = document.getElementById('orange-import-error');
        const endpoint = form.getAttribute('action');
        let active = false;
        const text = (pattern, result) => pattern.replace('{created}', result.created).replace('{total}', result.total);
        const run = async () => {
            if (active) { return; }
            active = true;
            error.hidden = true;
            runner.querySelectorAll('button,input[type="submit"]').forEach(button => button.disabled = true);
            try {
                while (true) {
                    const body = new FormData(form);
                    body.set('ajax', '1');
                    const response = await fetch(endpoint, {method: 'POST', body, credentials: 'same-origin'});
                    const result = await response.json().catch(() => ({error: runner.dataset.failed}));
                    if (!response.ok || result.error) { throw new Error(result.error || runner.dataset.failed); }
                    progress.value = result.created;
                    status.textContent = text(result.done ? runner.dataset.finished : runner.dataset.progress, result);
                    if (result.done) {
                        runner.hidden = true;
                        // Show the final server-side summary and a fresh upload form.
                        window.location.replace(endpoint);
                        return;
                    }
                    runner.querySelectorAll('input[name="token"]').forEach(input => input.value = result.token);
                }
            } catch (failure) {
                error.textContent = failure instanceof TypeError ? runner.dataset.failed : (failure.message || runner.dataset.failed);
                error.hidden = false;
            } finally {
                active = false;
                runner.querySelectorAll('button,input[type="submit"]').forEach(button => button.disabled = false);
            }
        };
        form.addEventListener('submit', event => { event.preventDefault(); run(); });
        if (runner.dataset.auto === '1') { run(); }
    };
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
