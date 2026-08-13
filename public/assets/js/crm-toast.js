/**
 * CRM toast host — non-blocking feedback for inline edits / actions.
 * Usage: crmToast('Saved'); crmToast('Failed', { variant: 'err' });
 */
(function (global) {
    function host() {
        let el = document.getElementById('crmToastHost');
        if (!el) {
            el = document.createElement('div');
            el.id = 'crmToastHost';
            el.className = 'crm-toast-host';
            el.setAttribute('aria-live', 'polite');
            document.body.appendChild(el);
        }
        return el;
    }

    function crmToast(message, opts) {
        opts = opts || {};
        const variant = opts.variant || 'ok';
        const ms = typeof opts.ms === 'number' ? opts.ms : 2800;
        const toast = document.createElement('div');
        toast.className = 'crm-toast' + (variant === 'err' ? ' crm-toast--err' : variant === 'ok' ? ' crm-toast--ok' : '');
        toast.setAttribute('role', 'status');
        const icon = variant === 'err' ? 'exclamation-triangle' : 'check-circle';
        toast.innerHTML = '<i class="bi bi-' + icon + '" aria-hidden="true"></i><span></span>';
        toast.querySelector('span').textContent = String(message || '');
        host().appendChild(toast);
        requestAnimationFrame(function () { toast.classList.add('is-visible'); });
        setTimeout(function () {
            toast.classList.remove('is-visible');
            setTimeout(function () { toast.remove(); }, 200);
        }, ms);
    }

    global.crmToast = crmToast;
})(window);
