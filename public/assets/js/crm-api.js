/**
 * Build REST URLs that work on stock multihost nginx (try_files + .php to FPM only).
 * Logical paths are under /api/v1/ — pass the part after v1, e.g. "contacts", "deals/3".
 */
(function (global) {
    function crmApiUrl(rel) {
        if (rel === undefined || rel === null) {
            rel = '';
        }
        var s = String(rel).replace(/^\/+/, '');
        var q = s.indexOf('?');
        var pathPart = q >= 0 ? s.slice(0, q) : s;
        var qs = q >= 0 ? s.slice(q + 1) : '';
        var segments = pathPart.split('/').filter(Boolean);
        var url = '/api/v1/index.php?path=/' + segments.map(encodeURIComponent).join('/');
        if (qs) {
            url += '&' + qs;
        }
        return url;
    }
    global.crmApiUrl = crmApiUrl;
})(typeof window !== 'undefined' ? window : this);
