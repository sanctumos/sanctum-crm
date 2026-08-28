/**
 * Ask Len composer — large paste → text attachment chip (Discord-style).
 * Prod defaults per ASK-Q-PHASE-3-COMPOSER-UPGRADE-PRD.md
 */
(function (global) {
    'use strict';

    const DEFAULTS = {
        pasteThresholdChars: 800,
        maxAttachmentBytes: 512 * 1024,
        maxAttachmentsPerSend: 3,
        maxCaptionChars: 2000,
    };

    function formatBytes(n) {
        if (n < 1024) return n + ' B';
        if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' KB';
        return (n / (1024 * 1024)).toFixed(2) + ' MB';
    }

    function stampName() {
        const d = new Date();
        const p = (x) => String(x).padStart(2, '0');
        return 'pasted-' + d.getFullYear() + p(d.getMonth() + 1) + p(d.getDate()) + '-'
            + p(d.getHours()) + p(d.getMinutes()) + p(d.getSeconds()) + '.txt';
    }

    function makeId() {
        return 'att-' + Math.random().toString(36).slice(2, 10);
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function ComposerPasteManager(options) {
        this.textarea = options.textarea;
        this.chipContainer = options.chipContainer;
        this.onChange = options.onChange || function () {};
        this.config = Object.assign({}, DEFAULTS, options.config || {});
        this.pending = [];
        this._onPaste = this._onPaste.bind(this);
        this.textarea.addEventListener('paste', this._onPaste);
    }

    ComposerPasteManager.prototype._onPaste = function (e) {
        const clip = (e.clipboardData || window.clipboardData);
        if (!clip) return;

        const plain = clip.getData('text/plain');
        if (!plain || plain.length < this.config.pasteThresholdChars) {
            return;
        }

        e.preventDefault();

        const bytes = new TextEncoder().encode(plain).length;
        if (bytes > this.config.maxAttachmentBytes) {
            window.alert('Paste is ' + formatBytes(bytes) + '; max per attachment is '
                + formatBytes(this.config.maxAttachmentBytes) + '.');
            return;
        }

        if (this.pending.length >= this.config.maxAttachmentsPerSend) {
            window.alert('Max ' + this.config.maxAttachmentsPerSend + ' text attachments per message.');
            return;
        }

        this.pending.push({
            id: makeId(),
            kind: 'text',
            filename: stampName(),
            mime_type: 'text/plain',
            size_bytes: bytes,
            text: plain,
        });
        this._renderChips();
        this.onChange();
    };

    ComposerPasteManager.prototype._renderChips = function () {
        const self = this;
        this.chipContainer.innerHTML = '';
        this.pending.forEach(function (att) {
            const chip = document.createElement('div');
            chip.className = 'sanctum-composer-chip';
            chip.dataset.id = att.id;

            const icon = document.createElement('span');
            icon.className = 'sanctum-composer-chip__icon';
            icon.textContent = '📄';

            const name = document.createElement('span');
            name.className = 'sanctum-composer-chip__name';
            name.title = att.filename;
            name.textContent = att.filename;

            const meta = document.createElement('span');
            meta.className = 'sanctum-composer-chip__meta';
            meta.textContent = formatBytes(att.size_bytes);

            const rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'sanctum-composer-chip__remove';
            rm.setAttribute('aria-label', 'Remove attachment');
            rm.textContent = '×';
            rm.addEventListener('click', function () {
                self.remove(att.id);
            });

            chip.appendChild(icon);
            chip.appendChild(name);
            chip.appendChild(meta);
            chip.appendChild(rm);
            self.chipContainer.appendChild(chip);
        });
        this.chipContainer.classList.toggle('sanctum-hidden', this.pending.length === 0);
    };

    ComposerPasteManager.prototype.remove = function (id) {
        this.pending = this.pending.filter(function (a) { return a.id !== id; });
        this._renderChips();
        this.onChange();
    };

    ComposerPasteManager.prototype.clear = function () {
        this.pending = [];
        this._renderChips();
        this.onChange();
    };

    ComposerPasteManager.prototype.getCaption = function () {
        return (this.textarea.value || '').trim().slice(0, this.config.maxCaptionChars);
    };

    ComposerPasteManager.prototype.canSend = function () {
        return this.getCaption().length > 0 || this.pending.length > 0;
    };

    ComposerPasteManager.prototype.buildPayload = function () {
        const caption = this.getCaption();
        const attachments = this.pending.map(function (a) {
            return {
                id: a.id,
                kind: a.kind,
                filename: a.filename,
                mime_type: a.mime_type,
                size_bytes: a.size_bytes,
                text: a.text,
            };
        });

        let message = caption;
        if (attachments.length) {
            const blocks = attachments.map(function (a, i) {
                return '[Attached text ' + (i + 1) + ': ' + a.filename + ' (' + formatBytes(a.size_bytes) + ')]\n'
                    + a.text;
            });
            message = (caption ? caption + '\n\n' : '') + blocks.join('\n\n');
        }

        return {
            message: message,
            caption: caption,
            attachments: attachments,
            attachment_count: attachments.length,
            message_bytes: new TextEncoder().encode(message).length,
        };
    };

    ComposerPasteManager.prototype.renderBubbleHtml = function (payload) {
        let html = '';
        if (payload.caption) {
            html += '<div class="sanctum-composer-caption">' + escapeHtml(payload.caption) + '</div>';
        }
        (payload.attachments || []).forEach(function (a) {
            html += '<div class="sanctum-composer-bubble-attach" data-att-id="' + escapeHtml(a.id) + '">'
                + '<span class="sanctum-composer-bubble-attach__icon">📄</span>'
                + '<span class="sanctum-composer-bubble-attach__label">'
                + '<strong>' + escapeHtml(a.filename) + '</strong> · ' + formatBytes(a.size_bytes || 0)
                + '</span>';
            if (a.text) {
                html += '<button type="button" class="sanctum-composer-preview-btn" data-preview-text="'
                    + escapeHtml(a.text) + '" data-preview-name="' + escapeHtml(a.filename) + '">Preview</button>';
            }
            html += '</div>';
        });
        return html;
    };

    global.ComposerPasteManager = ComposerPasteManager;
    global.AskQComposerUtils = { formatBytes: formatBytes, escapeHtml: escapeHtml };
})(window);
