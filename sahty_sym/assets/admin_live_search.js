function buildUrlWithForm(form) {
    const url = new URL(form.action || window.location.href, window.location.origin);
    const params = new URLSearchParams();
    const formData = new FormData(form);

    for (const [key, value] of formData.entries()) {
        if (value === '' || value === null) {
            continue;
        }
        params.append(key, value.toString());
    }

    url.search = params.toString();
    return url.toString();
}

let activeRequest = null;

function getMainContentRoot(doc) {
    return (
        doc.querySelector('main.content') ||
        doc.querySelector('#main-content') ||
        doc.querySelector('main')
    );
}

function replaceMainContent(html) {
    const parser = new DOMParser();
    const newDoc = parser.parseFromString(html, 'text/html');
    const currentMain = getMainContentRoot(document);
    const newMain = getMainContentRoot(newDoc);

    if (!currentMain || !newMain) {
        return false;
    }

    const currentInput = document.querySelector('input[type="search"][name="query"], input[name="query"]');
    const currentValue = currentInput ? currentInput.value : '';
    const hadFocus = currentInput && document.activeElement === currentInput;

    currentMain.innerHTML = newMain.innerHTML;

    const newInput = document.querySelector('input[type="search"][name="query"], input[name="query"]');
    if (newInput) {
        newInput.value = currentValue;
        if (hadFocus) {
            newInput.focus();
            newInput.setSelectionRange(newInput.value.length, newInput.value.length);
        }
    }

    return true;
}

function submitWithAjax(form) {
    if (!form) {
        return;
    }

    const url = buildUrlWithForm(form);

    if (activeRequest) {
        activeRequest.abort();
    }

    const controller = new AbortController();
    activeRequest = controller;

    fetch(url, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        },
        signal: controller.signal,
    })
        .then((response) => response.text())
        .then((html) => {
            if (replaceMainContent(html)) {
                window.history.replaceState({}, '', url);
                initAdminLiveSearch();
                return;
            }
            window.location.assign(url);
        })
        .catch((error) => {
            if (error && error.name === 'AbortError') {
                return;
            }
            window.location.assign(url);
        })
        .finally(() => {
            if (activeRequest === controller) {
                activeRequest = null;
            }
        });
}

function debounce(fn, delay) {
    let timeoutId = null;
    return function (...args) {
        if (timeoutId) {
            window.clearTimeout(timeoutId);
        }
        timeoutId = window.setTimeout(() => fn.apply(this, args), delay);
    };
}

function initAdminLiveSearch() {
    if (!document.body.classList.contains('ea')) {
        return;
    }

    const searchInput = document.querySelector(
        '[data-ea-search-input], input[type="search"][name="query"], input[name="query"]'
    );
    const searchForm = searchInput ? searchInput.closest('form') : null;

    if (searchInput && searchForm) {
        const debounced = debounce(() => submitWithAjax(searchForm), 300);
        searchInput.addEventListener('input', debounced);
    }

    const filtersForm = document.querySelector('form[data-ea-filters-form]');
    if (filtersForm) {
        filtersForm.addEventListener('change', () => submitWithAjax(filtersForm));
    }
}

document.addEventListener('DOMContentLoaded', initAdminLiveSearch);
