/**
 * Lightweight safe markdown for Ask Len chat bubbles (no external deps).
 */
(function (window) {
    'use strict';

    function decodeEntities(text) {
        const ta = document.createElement('textarea');
        ta.innerHTML = text;
        return ta.value;
    }

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function escapeAttr(text) {
        return escapeHtml(text).replace(/'/g, '&#39;');
    }

    function safeUrl(url) {
        const u = String(url || '').trim();
        if (/^https?:\/\//i.test(u) || /^mailto:/i.test(u)) {
            return u;
        }
        return null;
    }

    function sanitizeHtml(html) {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const blocked = new Set(['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button']);
        const walk = (node) => {
            if (node.nodeType !== 1) {
                return;
            }
            const el = node;
            const tag = el.tagName.toLowerCase();
            if (blocked.has(tag)) {
                el.remove();
                return;
            }
            [...el.attributes].forEach((attr) => {
                const name = attr.name.toLowerCase();
                const val = attr.value;
                if (name.startsWith('on') || name === 'style') {
                    el.removeAttribute(attr.name);
                    return;
                }
                if ((name === 'href' || name === 'src') && !safeUrl(val)) {
                    el.removeAttribute(attr.name);
                }
            });
            [...el.childNodes].forEach(walk);
        };
        [...doc.body.childNodes].forEach(walk);
        return doc.body.innerHTML;
    }

    function formatInline(text) {
        let s = text;
        s = s.replace(/`([^`\n]+)`/g, (_, code) => '<code>' + escapeHtml(code) + '</code>');
        s = s.replace(/\*\*([^*\n]+)\*\*/g, (_, t) => '<strong>' + escapeHtml(t) + '</strong>');
        s = s.replace(/\*([^*\n]+)\*/g, (_, t) => '<em>' + escapeHtml(t) + '</em>');
        s = s.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, (_, label, url) => {
            const href = safeUrl(url);
            if (!href) {
                return escapeHtml('[' + label + '](' + url + ')');
            }
            return '<a href="' + escapeAttr(href) + '" target="_blank" rel="noopener noreferrer">'
                + escapeHtml(label) + '</a>';
        });
        s = s.replace(
            /(?<![\"'=])(https?:\/\/[^\s<]+[^\s<.,;:!?)\]\"'])/gi,
            (url) => {
                const href = safeUrl(url);
                if (!href) {
                    return url;
                }
                return '<a href="' + escapeAttr(href) + '" target="_blank" rel="noopener noreferrer">'
                    + escapeHtml(url) + '</a>';
            }
        );
        return s;
    }

    function formatBlocks(raw) {
        const lines = raw.replace(/\r\n/g, '\n').split('\n');
        const out = [];
        let i = 0;

        const flushParagraph = (buf) => {
            const text = buf.join('\n').trim();
            if (!text) {
                return;
            }
            const parts = text.split(/\n{2,}/).map((p) => p.trim()).filter(Boolean);
            parts.forEach((p) => {
                const inner = formatInline(escapeHtml(p)).replace(/\n/g, '<br>');
                out.push('<p>' + inner + '</p>');
            });
        };

        while (i < lines.length) {
            const line = lines[i];

            if (/^```/.test(line)) {
                const buf = [];
                i += 1;
                while (i < lines.length && !/^```/.test(lines[i])) {
                    buf.push(lines[i]);
                    i += 1;
                }
                i += 1;
                out.push('<pre><code>' + escapeHtml(buf.join('\n')) + '</code></pre>');
                continue;
            }

            if (/^#{1,6}\s+/.test(line)) {
                const m = line.match(/^(#{1,6})\s+(.*)$/);
                const level = Math.min(6, m[1].length);
                out.push('<h' + level + '>' + formatInline(escapeHtml(m[2].trim())) + '</h' + level + '>');
                i += 1;
                continue;
            }

            if (/^>\s?/.test(line)) {
                const buf = [];
                while (i < lines.length && /^>\s?/.test(lines[i])) {
                    buf.push(lines[i].replace(/^>\s?/, ''));
                    i += 1;
                }
                const inner = formatInline(escapeHtml(buf.join('\n').trim())).replace(/\n/g, '<br>');
                out.push('<blockquote>' + inner + '</blockquote>');
                continue;
            }

            if (/^[-*]\s+/.test(line)) {
                const buf = [];
                while (i < lines.length && /^[-*]\s+/.test(lines[i])) {
                    buf.push(lines[i].replace(/^[-*]\s+/, ''));
                    i += 1;
                }
                out.push('<ul>' + buf.map((li) => '<li>' + formatInline(escapeHtml(li)) + '</li>').join('') + '</ul>');
                continue;
            }

            if (/^\d+\.\s+/.test(line)) {
                const buf = [];
                while (i < lines.length && /^\d+\.\s+/.test(lines[i])) {
                    buf.push(lines[i].replace(/^\d+\.\s+/, ''));
                    i += 1;
                }
                out.push('<ol>' + buf.map((li) => '<li>' + formatInline(escapeHtml(li)) + '</li>').join('') + '</ol>');
                continue;
            }

            if (/^(-{3,}|\*{3,}|_{3,})$/.test(line.trim())) {
                out.push('<hr>');
                i += 1;
                continue;
            }

            if (line.trim() === '') {
                i += 1;
                continue;
            }

            const buf = [];
            while (i < lines.length && lines[i].trim() !== '' && !/^#{1,6}\s/.test(lines[i])
                && !/^```/.test(lines[i]) && !/^>\s?/.test(lines[i]) && !/^[-*]\s+/.test(lines[i])
                && !/^\d+\.\s+/.test(lines[i])) {
                buf.push(lines[i]);
                i += 1;
            }
            flushParagraph(buf);
        }

        return out.join('');
    }

    function toHtml(text) {
        if (!text) {
            return '';
        }
        const decoded = decodeEntities(String(text));
        const html = formatBlocks(decoded);
        return sanitizeHtml(html || formatInline(escapeHtml(decoded)));
    }

    window.SanctumMarkdownLite = { toHtml: toHtml };
})(window);
