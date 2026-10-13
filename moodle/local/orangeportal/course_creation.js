// The native course page prints its heading after the output hook; adapt only that heading.
window.OrangePortalCreation = {
    init: function(Y, title, nativeTitle) {
        document.querySelectorAll('h1, h2, h3').forEach(function(heading) {
            if (heading.textContent.trim() === nativeTitle) {
                heading.textContent = title;
            }
        });
    }
};
