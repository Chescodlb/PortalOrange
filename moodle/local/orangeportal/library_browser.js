/* Progressive enhancement: links/search work without JavaScript; reveal the next column on narrow screens. */
(function() {
    const reveal = function() {
        const board = document.querySelector('.orange-library-browser');
        const params = new URL(window.location.href).searchParams;
        if (!board || !['category', 'document', 'course', 'q'].some(key => params.get(key))) { return; }
        let target = board.querySelector('.orange-library-results');
        if (board.dataset.focus === 'detail') {
            target = board.querySelector('.orange-library-detail');
        } else if (board.dataset.focus === 'branch') {
            const branches = board.querySelectorAll('.orange-library-column:not(.orange-library-results)');
            target = branches[branches.length - 1];
        }
        if (!target) { return; }
        const heading = target.querySelector('h3, h4') || target;
        heading.setAttribute('tabindex', '-1');
        heading.focus({preventScroll: true});
        target.scrollIntoView({block: 'nearest', inline: 'nearest'});
    };
    const init = function() {
        const order = document.getElementById('orange-library-order');
        if (order) { order.addEventListener('change', function() { order.form.requestSubmit(); }); }
        reveal();
    };
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init, {once: true}); }
    else { init(); }
})();
