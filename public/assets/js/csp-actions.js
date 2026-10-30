/*
 * Declarative replacements for inline event handlers, which the Content-Security-Policy blocks.
 *   data-click="fnName" data-click-args='[1,"a"]'   -> window.fnName(1, "a") on click
 *   data-change="fnName" data-change-value          -> window.fnName(element.value) on change
 *   data-change="fnName" data-change-args='[1]'     -> window.fnName(1) on change
 *   data-autosubmit                                 -> submits the element's form on change
 *   form[data-confirm="Pesan"]                      -> asks before submitting
 *   data-dismiss-parent                             -> removes the parent element on click
 */
(() => {
    const call = (el, kind, value) => {
        const fn = window[el.dataset[kind]];
        if (typeof fn !== 'function') return;
        let args = [];
        try { args = JSON.parse(el.dataset[kind + 'Args'] || '[]'); } catch (error) { return; }
        if (value !== undefined) args.push(value);
        fn.apply(el, args);
    };
    document.addEventListener('click', event => {
        const target = event.target.closest('[data-click]');
        if (target) call(target, 'click');
        const dismiss = event.target.closest('[data-dismiss-parent]');
        if (dismiss && dismiss.parentElement) dismiss.parentElement.remove();
    });
    document.addEventListener('change', event => {
        const target = event.target.closest('[data-change]');
        if (target) call(target, 'change', 'changeValue' in target.dataset ? target.value : undefined);
        if (event.target.matches('[data-autosubmit]') && event.target.form) event.target.form.submit();
    });
    document.addEventListener('submit', event => {
        const form = event.target.closest('form[data-confirm]');
        if (form && !window.confirm(form.dataset.confirm)) { event.preventDefault(); event.stopImmediatePropagation(); }
    }, true);
})();
