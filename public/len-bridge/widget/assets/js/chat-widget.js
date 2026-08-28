/**
 * Sanctum Chat Widget - Main JavaScript Library
 * 
 * A lightweight, embeddable chat widget that can be integrated
 * into any website or application.
 */

(function(window, document) {
    'use strict';

    // Default configuration
    let config = {
        apiKey: null,
        apiBase: '/len-bridge/api/v1/',
        useSessionAuth: false,
        position: 'bottom-right',
        theme: 'light',
        title: 'Chat with us',
        chatterUsername: '',
        primaryColor: '#007bff',
        greeting: 'Hello! How can I help you today?',
        language: 'en',
        autoOpen: false,
        notifications: true,
        sound: true,
        persistSession: false,
        historyLimit: 6,
        pageContext: null,
        pollIntervalActiveMs: 3000,
        pollIntervalIdleMs: 12000,
        pollIntervalHiddenMs: 45000
    };

    // Widget state
    let state = {
        isInitialized: false,
        isOpen: false,
        isTyping: false,
        sessionId: null,
        uid: null,
        messageCount: 0,
        eventListeners: {},
        typingTimeout: null,
        lastResponseSince: null,
        seenMessageIds: {},
        historyLoading: false,
        sessionBootstrapError: null,
        waitingForReply: false,
        pollTimer: null,
        pollBackoffMs: 3000,
        pollRateLimitedUntil: 0,
        pollErrorShown: false
    };

    // Widget HTML template
    const widgetTemplate = `
        <div class="sanctum-chat-widget" data-position="${config.position}" data-theme="${config.theme}">
            <!-- Chat Window (above bubble in layout) -->
            <div class="sanctum-chat-window" id="sanctum-chat-window">
                <!-- Chat Header -->
                <div class="sanctum-chat-header">
                    <div class="sanctum-chat-header-titles">
                        <h3 id="sanctum-chat-title">${config.title}</h3>
                        <p class="sanctum-chat-chatter" id="sanctum-chat-chatter"></p>
                    </div>
                    <button class="close-btn" id="sanctum-chat-close">
                        <svg viewBox="0 0 24 24" width="20" height="20">
                            <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" fill="currentColor"/>
                        </svg>
                    </button>
                </div>
                
                <!-- Chat Messages -->
                <div class="sanctum-chat-messages" id="sanctum-chat-messages">
                    <div class="sanctum-message">
                        <div class="sanctum-message-avatar">S</div>
                        <div class="sanctum-message-content">
                            ${config.greeting}
                            <div class="sanctum-message-time">${new Date().toLocaleTimeString()}</div>
                        </div>
                    </div>
                </div>
                
                <!-- Chat Input -->
                <div class="sanctum-chat-input">
                    <div class="sanctum-composer-chips sanctum-hidden" id="sanctum-composer-chips" aria-live="polite"></div>
                    <textarea 
                        id="sanctum-chat-input" 
                        placeholder="Type your message..."
                        rows="1"
                    ></textarea>
                    <button class="sanctum-chat-send" id="sanctum-chat-send" disabled>
                        <svg viewBox="0 0 24 24">
                            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Chat Bubble — always bottom-right anchor -->
            <div class="sanctum-chat-bubble" id="sanctum-chat-bubble">
                <svg viewBox="0 0 24 24">
                    <path d="M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h4l4 4 4-4h4c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
                </svg>
                <div class="notification-badge sanctum-hidden" id="sanctum-notification-badge">0</div>
            </div>
        </div>
    `;

    // Utility functions
    const utils = {
        // Generate unique session ID
        generateSessionId: function() {
            return 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        },

        // Generate unique UID
        generateUid: function() {
            return Math.random().toString(36).substr(2, 15);
        },

        // Format timestamp
        formatTime: function(timestamp) {
            return new Date(timestamp).toLocaleTimeString();
        },

        // Plain-text escape (legacy)
        sanitizeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /** Render message body: decode entities + safe markdown when available. */
        formatMessageHtml: function(text) {
            if (!text) {
                return '';
            }
            if (typeof window.SanctumMarkdownLite !== 'undefined' && window.SanctumMarkdownLite.toHtml) {
                return window.SanctumMarkdownLite.toHtml(text);
            }
            return utils.sanitizeHtml(text);
        },

        // Debounce function
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        // Check if element is in viewport
        isInViewport: function(element) {
            const rect = element.getBoundingClientRect();
            return (
                rect.top >= 0 &&
                rect.left >= 0 &&
                rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
                rect.right <= (window.innerWidth || document.documentElement.clientWidth)
            );
        }
    };

    // API communication
    const SESSION_CACHE_TTL_MS = 10 * 60 * 1000;
    // Must not share Ask Q's cache keys — same browser would reuse a Tasks session id
    // and Ask Len looks "down" (early Q-class bridge ghost).
    const SESSION_CACHE_KEY = 'sanctum_len_user_session_cache';
    const SESSION_LOCAL_KEY = 'sanctum_len_chat_session_id';

    const api = {
        url(action, query) {
            const base = (config.apiBase || '/len-bridge/api/v1/').replace(/\/?$/, '/');
            let u = base + '?action=' + encodeURIComponent(action);
            if (query) {
                Object.keys(query).forEach(function (k) {
                    u += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(query[k]);
                });
            }
            return u;
        },

        headers() {
            const h = { 'Content-Type': 'application/json' };
            if (!config.useSessionAuth && config.apiKey) {
                h['Authorization'] = 'Bearer ' + config.apiKey;
            }
            return h;
        },

        readSessionCache() {
            if (!config.useSessionAuth) {
                return null;
            }
            try {
                const raw = sessionStorage.getItem(SESSION_CACHE_KEY);
                if (!raw) {
                    return null;
                }
                const parsed = JSON.parse(raw);
                if (!parsed || !parsed.session_id || !parsed.fetchedAt) {
                    return null;
                }
                if (Date.now() - parsed.fetchedAt > SESSION_CACHE_TTL_MS) {
                    return null;
                }
                return parsed;
            } catch (e) {
                return null;
            }
        },

        writeSessionCache(data) {
            if (!config.useSessionAuth || !data || !data.session_id) {
                return;
            }
            try {
                sessionStorage.setItem(SESSION_CACHE_KEY, JSON.stringify({
                    session_id: data.session_id,
                    crm_user_id: data.crm_user_id || null,
                    fetchedAt: Date.now()
                }));
            } catch (e) { /* ignore */ }
        },

        applySessionData(data) {
            if (!data || !data.session_id) {
                return false;
            }
            state.sessionId = data.session_id;
            if (data.crm_user_id) {
                config.crmUserId = data.crm_user_id;
            }
            try {
                localStorage.setItem(SESSION_LOCAL_KEY, state.sessionId);
                const uid = data.crm_user_id ? String(data.crm_user_id) : '0';
                state.lastResponseSince = localStorage.getItem('sanctum_len_since_u_' + uid) || null;
            } catch (e) { /* ignore */ }
            return true;
        },

        formatRequestError(error, response, body) {
            if (response && response.status === 429) {
                const retry = body && body.retry_after ? Math.ceil(body.retry_after / 60) : 60;
                return 'Ask Len is temporarily busy (rate limit). Wait about ' + retry + ' min and refresh the page.';
            }
            if (response && response.status === 401) {
                return 'CRM session expired. Refresh the page and log in again.';
            }
            if (body && body.error) {
                return body.error;
            }
            if (error && error.message) {
                return error.message;
            }
            return 'Request failed';
        },

        async request(action, data, method) {
            method = method || 'POST';
            let response = null;
            let body = null;
            try {
                const opts = {
                    method: method,
                    headers: this.headers(),
                    credentials: config.useSessionAuth ? 'include' : 'same-origin'
                };
                if (method === 'POST' && data) {
                    opts.body = JSON.stringify(data);
                }
                response = await fetch(this.url(action, method === 'GET' ? data : null), opts);
                try {
                    body = await response.json();
                } catch (parseErr) {
                    body = null;
                }
                if (!response.ok) {
                    const err = new Error(this.formatRequestError(null, response, body));
                    err.httpStatus = response.status;
                    err.apiBody = body;
                    throw err;
                }
                if (!body || !body.success) {
                    const err = new Error(this.formatRequestError(null, response, body));
                    err.httpStatus = response.status;
                    err.apiBody = body;
                    throw err;
                }
                return body.data;
            } catch (error) {
                if (!error.httpStatus) {
                    error.message = this.formatRequestError(error, response, body);
                }
                console.error('Sanctum Chat Widget API Error:', error);
                throw error;
            }
        },

        parsePageContextFromLocation() {
            var path = window.location.pathname || '';
            var params = new URLSearchParams(window.location.search || '');
            var out = {};
            var intParam = function (key) {
                var v = parseInt(params.get(key), 10);
                return v > 0 ? v : 0;
            };

            if (path.indexOf('/admin/view.php') >= 0) {
                out.surface = intParam('id') ? 'task' : 'tasks';
                if (intParam('id')) {
                    out.task_id = intParam('id');
                }
            } else if (path.indexOf('/admin/doc.php') >= 0 || path.indexOf('/admin/document.php') >= 0) {
                out.surface = intParam('id') ? 'document' : 'docs';
                if (intParam('id')) {
                    out.document_id = intParam('id');
                }
            } else if (path.indexOf('/admin/project.php') >= 0 || path.indexOf('/admin/workspace-project.php') >= 0) {
                out.surface = intParam('id') ? 'project' : 'projects';
                if (intParam('id')) {
                    out.project_id = intParam('id');
                }
                if (params.get('tab')) {
                    out.tab = String(params.get('tab')).slice(0, 32);
                }
            } else if (path.indexOf('/admin/docs.php') >= 0) {
                out.surface = 'docs';
                if (intParam('project_id')) {
                    out.project_id = intParam('project_id');
                }
                if (params.get('dir')) {
                    out.directory_path = String(params.get('dir')).slice(0, 256);
                }
            } else if (path.indexOf('/admin/doc-create.php') >= 0 || path.indexOf('/admin/doc-update.php') >= 0) {
                if (path.indexOf('/admin/doc-update.php') >= 0 && intParam('id')) {
                    out.surface = 'document';
                    out.document_id = intParam('id');
                } else {
                    out.surface = 'doc_create';
                    if (intParam('project_id')) {
                        out.project_id = intParam('project_id');
                    }
                }
            } else if (path.indexOf('/admin/create.php') >= 0) {
                out.surface = 'task_create';
                if (intParam('project_id')) {
                    out.project_id = intParam('project_id');
                }
                if (intParam('list_id')) {
                    out.list_id = intParam('list_id');
                }
            } else if (path === '/admin/' || path === '/admin' || path.indexOf('/admin/index.php') >= 0) {
                if (intParam('project_id')) {
                    out.surface = 'project';
                    out.project_id = intParam('project_id');
                } else {
                    out.surface = 'home';
                }
            } else if (path.indexOf('/admin/activity.php') >= 0) {
                out.surface = 'activity';
            } else if (path.indexOf('/admin/settings.php') >= 0) {
                out.surface = 'settings';
            } else if (path.indexOf('/admin/workspace-projects.php') >= 0) {
                out.surface = 'projects';
            } else if (path.indexOf('/admin/') === 0) {
                var page = path.replace(/^.*\//, '').replace(/\.php$/, '');
                out.surface = 'admin';
                out.admin_page = page || 'admin';
            }
            return out;
        },

        mergePageContextField(base, fromUrl, key) {
            if (fromUrl[key] !== undefined && fromUrl[key] !== null && fromUrl[key] !== '') {
                base[key] = fromUrl[key];
            }
        },

        collectPageContext() {
            var base = {};
            if (config.pageContext && typeof config.pageContext === 'object') {
                base = Object.assign({}, config.pageContext);
            } else if (typeof window.CRM_ASK_LEN_PAGE === 'object' && window.CRM_ASK_LEN_PAGE) {
                base = Object.assign({}, window.CRM_ASK_LEN_PAGE);
            }
            // Live URL wins for route-derived ids (fixes stale project_id from another board).
            var fromUrl = this.parsePageContextFromLocation();
            var mergeField = this.mergePageContextField.bind(this);
            ['surface', 'project_id', 'task_id', 'document_id', 'list_id', 'directory_path', 'tab', 'admin_page'].forEach(function (key) {
                mergeField(base, fromUrl, key);
            });
            var titleEl = document.querySelector('h1');
            if (titleEl && titleEl.textContent) {
                base.page_title = titleEl.textContent.trim().slice(0, 200);
            }
            base.url = (window.location.pathname || '') + (window.location.search || '');
            if (!base.surface && base.url.indexOf('/admin/') === 0) {
                base.surface = 'admin';
            }
            return base;
        },

        async sendMessage(payload) {
            await this.ensureActiveSession();
            if (!state.sessionId) {
                throw new Error('No active session');
            }
            var body;
            if (typeof payload === 'string') {
                body = {
                    session_id: state.sessionId,
                    message: payload,
                    timestamp: new Date().toISOString(),
                    uid: state.uid,
                    page_context: this.collectPageContext()
                };
            } else {
                body = Object.assign({
                    session_id: state.sessionId,
                    timestamp: new Date().toISOString(),
                    uid: state.uid,
                    page_context: this.collectPageContext()
                }, payload || {});
                if (!body.session_id) {
                    body.session_id = state.sessionId;
                }
            }
            return await this.request('messages', body, 'POST');
        },

        async getMessages(since) {
            if (!state.sessionId) {
                throw new Error('No active session');
            }
            const data = await this.request('responses', {
                session_id: state.sessionId,
                since: since || ''
            }, 'GET');
            return (data && data.responses) ? data.responses : [];
        },

        async getUserSession() {
            return await this.request('user_session', null, 'GET');
        },

        async getHistory(limit) {
            const data = await this.request('history', {
                limit: String(limit || config.historyLimit || 6)
            }, 'GET');
            if (data && data.session_id) {
                state.sessionId = data.session_id;
                try {
                    localStorage.setItem(SESSION_LOCAL_KEY, state.sessionId);
                } catch (e) { /* ignore */ }
            }
            return data || { items: [], latest_response_at: null };
        },

        async ensureActiveSession(forceRefresh) {
            if (!config.useSessionAuth) {
                return !!state.sessionId;
            }
            if (!forceRefresh) {
                const cached = this.readSessionCache();
                if (cached && this.applySessionData(cached)) {
                    return true;
                }
                if (state.sessionId) {
                    return true;
                }
            }
            return this.bootstrapUserSession(forceRefresh);
        },

        async bootstrapUserSession(forceRefresh) {
            if (!config.useSessionAuth) {
                return false;
            }
            if (!forceRefresh) {
                const cached = this.readSessionCache();
                if (cached && this.applySessionData(cached)) {
                    return true;
                }
            }
            try {
                const data = await this.getUserSession();
                if (data && data.session_id) {
                    this.applySessionData(data);
                    this.writeSessionCache(data);
                    return true;
                }
            } catch (err) {
                const cached = this.readSessionCache();
                if (cached && this.applySessionData(cached)) {
                    console.warn('Ask Len: using cached session after bootstrap failure', err);
                    return true;
                }
                console.warn('Ask Len: could not resolve user session', err);
                state.sessionBootstrapError = err.message || 'Could not start Ask Len session';
            }
            return false;
        },

        // Update session
        async updateSession() {
            if (!state.sessionId) {
                throw new Error('No active session');
            }

            const data = {
                session_id: state.sessionId,
                uid: state.uid
            };

            return await this.request('session_response', data);
        }
    };

    // UI management
    const ui = {
        // Initialize UI
        init() {
            // Insert widget HTML
            document.body.insertAdjacentHTML('beforeend', widgetTemplate);
            
            // Get DOM elements
            this.elements = {
                widget: document.querySelector('.sanctum-chat-widget'),
                bubble: document.getElementById('sanctum-chat-bubble'),
                window: document.getElementById('sanctum-chat-window'),
                messages: document.getElementById('sanctum-chat-messages'),
                input: document.getElementById('sanctum-chat-input'),
                send: document.getElementById('sanctum-chat-send'),
                close: document.getElementById('sanctum-chat-close'),
                title: document.getElementById('sanctum-chat-title'),
                chatter: document.getElementById('sanctum-chat-chatter'),
                notificationBadge: document.getElementById('sanctum-notification-badge'),
                composerChips: document.getElementById('sanctum-composer-chips')
            };

            if (typeof window.ComposerPasteManager !== 'undefined' && this.elements.input && this.elements.composerChips) {
                state.composer = new window.ComposerPasteManager({
                    textarea: this.elements.input,
                    chipContainer: this.elements.composerChips,
                    onChange: () => {
                        this.updateSendButton();
                        this.autoResizeTextarea();
                    }
                });
            }

            this.applyChatterLabel();

            // Apply custom styles
            this.applyCustomStyles();
            
            // Bind events
            this.bindEvents();
            this.setupVisualViewport();
            
            // Set initial theme
            this.setTheme(config.theme);
            
            const greetBlock = this.elements.messages.querySelector('.sanctum-message-content');
            if (greetBlock && config.greeting) {
                const timeEl = greetBlock.querySelector('.sanctum-message-time');
                greetBlock.textContent = config.greeting + ' ';
                if (timeEl) {
                    greetBlock.appendChild(timeEl);
                }
            }

            if (config.autoOpen) {
                setTimeout(() => this.open(), 1000);
            }
        },

        applyChatterLabel() {
            const name = (config.chatterUsername || '').trim();
            if (!this.elements.chatter) {
                return;
            }
            if (name) {
                this.elements.chatter.textContent = 'You: ' + name;
                this.elements.chatter.classList.remove('sanctum-hidden');
            } else {
                this.elements.chatter.textContent = '';
                this.elements.chatter.classList.add('sanctum-hidden');
            }
        },

        // Apply custom styles
        applyCustomStyles() {
            const style = document.createElement('style');
            style.textContent = `
                .sanctum-chat-widget {
                    --primary-color: ${config.primaryColor};
                    --primary-hover: ${this.adjustColor(config.primaryColor, -20)};
                }
            `;
            document.head.appendChild(style);
        },

        // Adjust color brightness
        adjustColor(color, amount) {
            const hex = color.replace('#', '');
            const num = parseInt(hex, 16);
            const r = Math.min(255, Math.max(0, (num >> 16) + amount));
            const g = Math.min(255, Math.max(0, ((num >> 8) & 0x00FF) + amount));
            const b = Math.min(255, Math.max(0, (num & 0x0000FF) + amount));
            return '#' + (r << 16 | g << 8 | b).toString(16).padStart(6, '0');
        },

        // Bind event listeners
        bindEvents() {
            // Chat bubble click
            this.elements.bubble.addEventListener('click', () => this.toggle());
            
            // Close button click
            this.elements.close.addEventListener('click', () => this.close());
            
            // Input events
            this.elements.input.addEventListener('input', () => this.updateSendButton());
            this.elements.input.addEventListener('keydown', (e) => this.handleKeydown(e));
            this.elements.input.addEventListener('input', utils.debounce(() => this.autoResizeTextarea(), 100));
            
            // Send button click
            this.elements.send.addEventListener('click', () => this.sendMessage());

            this.elements.messages.addEventListener('click', (e) => {
                const btn = e.target.closest('.sanctum-composer-preview-btn');
                if (!btn) return;
                const name = btn.getAttribute('data-preview-name') || 'attachment.txt';
                const text = btn.getAttribute('data-preview-text') || '';
                this.showAttachmentPreview(name, text);
            });
            
            // Click outside to close
            document.addEventListener('click', (e) => this.handleOutsideClick(e));
            
            // Escape key to close
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && state.isOpen) {
                    this.close();
                }
            });

            const refocusViewport = () => {
                setTimeout(() => this.syncVisualViewport(), 50);
                setTimeout(() => this.syncVisualViewport(), 320);
            };
            this.elements.input.addEventListener('focus', refocusViewport);
            this.elements.input.addEventListener('blur', refocusViewport);
        },

        /** Mobile on-screen keyboard: shrink panel to visualViewport (not layout 100vh). */
        setupVisualViewport() {
            const vv = window.visualViewport;
            if (!vv) {
                return;
            }
            const onChange = () => this.syncVisualViewport();
            vv.addEventListener('resize', onChange);
            vv.addEventListener('scroll', onChange);
            window.addEventListener('orientationchange', onChange);
            window.addEventListener('resize', onChange);
            this._visualViewportTeardown = function () {
                vv.removeEventListener('resize', onChange);
                vv.removeEventListener('scroll', onChange);
                window.removeEventListener('orientationchange', onChange);
                window.removeEventListener('resize', onChange);
            };
            this.syncVisualViewport();
        },

        syncVisualViewport() {
            const widget = this.elements.widget;
            const win = this.elements.window;
            if (!widget || !win) {
                return;
            }
            const mobileMq = window.matchMedia('(max-width: 768px)');
            const isMobile = mobileMq.matches;
            const vv = window.visualViewport;
            const margin = isMobile ? 15 : 20;

            if (!isMobile || !vv) {
                widget.classList.remove('sanctum-keyboard-adjust');
                widget.style.removeProperty('bottom');
                widget.style.removeProperty('right');
                widget.style.removeProperty('--sanctum-vvh');
                win.style.removeProperty('height');
                win.style.removeProperty('max-height');
                return;
            }

            // Pin to the *visual* viewport. Wide tables expand the layout viewport on
            // mobile, so fixed right/bottom against layoutWidth shove the FAB off-screen.
            const insetBottom = Math.max(
                0,
                Math.round(window.innerHeight - vv.offsetTop - vv.height)
            );
            const insetRight = Math.max(
                0,
                Math.round(window.innerWidth - vv.offsetLeft - vv.width)
            );
            const inputFocused = this.elements.input === document.activeElement;
            // Only treat as keyboard when the panel is open (or composer focused).
            // Layout/visual mismatch alone is common on phones and is NOT a keyboard.
            const keyboardUp = state.isOpen && inputFocused && (
                insetBottom > 80 || vv.height < window.innerHeight * 0.75
            );
            const visibleH = Math.round(vv.height);

            widget.style.setProperty('--sanctum-vvh', visibleH + 'px');
            widget.style.right = (insetRight + margin) + 'px';
            widget.style.bottom = (insetBottom + margin) + 'px';

            if (state.isOpen) {
                widget.classList.toggle('sanctum-keyboard-adjust', keyboardUp);
                const panelMax = Math.max(220, visibleH - margin * 2);
                win.style.maxHeight = panelMax + 'px';
                win.style.height = Math.min(500, panelMax) + 'px';
                if (keyboardUp) {
                    requestAnimationFrame(() => {
                        this.elements.messages.scrollTop = this.elements.messages.scrollHeight;
                    });
                }
            } else {
                widget.classList.remove('sanctum-keyboard-adjust');
                win.style.removeProperty('height');
                win.style.removeProperty('max-height');
            }
        },

        // Handle keydown events
        handleKeydown(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        },

        showAttachmentPreview(filename, text) {
            let modal = document.getElementById('sanctum-composer-preview-modal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'sanctum-composer-preview-modal';
                modal.className = 'sanctum-composer-preview-modal';
                modal.innerHTML = '<div class="sanctum-composer-preview-modal__panel">'
                    + '<header class="sanctum-composer-preview-modal__head">'
                    + '<strong id="sanctum-composer-preview-title"></strong>'
                    + '<button type="button" id="sanctum-composer-preview-close" aria-label="Close">×</button>'
                    + '</header>'
                    + '<pre id="sanctum-composer-preview-body" class="sanctum-composer-preview-modal__body"></pre>'
                    + '</div>';
                document.body.appendChild(modal);
                modal.addEventListener('click', (ev) => {
                    if (ev.target === modal || ev.target.id === 'sanctum-composer-preview-close') {
                        modal.classList.remove('open');
                    }
                });
            }
            document.getElementById('sanctum-composer-preview-title').textContent = filename;
            document.getElementById('sanctum-composer-preview-body').textContent = text;
            modal.classList.add('open');
        },

        // Handle outside clicks
        handleOutsideClick(e) {
            if (state.isOpen && !this.elements.widget.contains(e.target)) {
                this.close();
            }
        },

        // Toggle chat window
        toggle() {
            if (state.isOpen) {
                this.close();
            } else {
                this.open().catch(function (err) {
                    console.warn('Ask Len open failed', err);
                });
            }
        },

        // Open chat window
        async open() {
            if (state.isOpen) return;

            if (config.useSessionAuth && !state.sessionId) {
                const ok = await api.ensureActiveSession(false);
                if (!ok) {
                    const msg = state.sessionBootstrapError
                        || 'Could not connect Ask Len. Refresh the page or wait a minute and try again.';
                    this.addMessage(msg, 'error');
                    return;
                }
            }

            state.isOpen = true;
            this.elements.widget.classList.add('is-open');
            this.elements.window.classList.add('open');
            this.syncVisualViewport();
            this.elements.input.focus();
            
            // Emit open event
            this.emit('open');
            
            // Clear notification badge
            this.clearNotifications();

            await this.loadRecentHistory();
            this.schedulePoll(800);
        },

        // Close chat window
        close() {
            if (!state.isOpen) return;
            
            state.isOpen = false;
            this.clearPollTimer();
            this.elements.widget.classList.remove('is-open');
            this.elements.window.classList.remove('open');
            this.syncVisualViewport();
            this.elements.input.blur();
            
            // Emit close event
            this.emit('close');
        },

        // Send message
        async sendMessage() {
            let payload;
            let displayPayload = null;

            if (state.composer) {
                if (!state.composer.canSend()) {
                    return;
                }
                payload = state.composer.buildPayload();
                displayPayload = {
                    caption: payload.caption,
                    attachments: payload.attachments.map(function (a) {
                        return {
                            id: a.id,
                            kind: a.kind,
                            filename: a.filename,
                            mime_type: a.mime_type,
                            size_bytes: a.size_bytes,
                            text: a.text,
                        };
                    }),
                };
            } else {
                const message = this.elements.input.value.trim();
                if (!message) return;
                payload = { message: message, caption: message, attachments: [] };
            }

            try {
                this.addMessage(payload.message, 'user', {
                    display: displayPayload,
                });

                this.elements.input.value = '';
                if (state.composer) {
                    state.composer.clear();
                }
                this.autoResizeTextarea();
                this.updateSendButton();

                await api.sendMessage(payload);

                this.emit('message', { message: payload.message, type: 'user' });

                state.waitingForReply = true;
                state.pollErrorShown = false;
                this.showTypingIndicator();
                this.schedulePoll(500);

            } catch (error) {
                console.error('Failed to send message:', error);
                const detail = (error && error.message) ? error.message : 'Failed to send message. Please try again.';
                this.addMessage(detail, 'error');
            }
        },

        renderGreeting() {
            const greeting = config.greeting || 'Hello! How can I help you today?';
            const messageDiv = document.createElement('div');
            messageDiv.className = 'sanctum-message';
            const avatar = document.createElement('div');
            avatar.className = 'sanctum-message-avatar';
            avatar.textContent = 'Q';
            const messageContent = document.createElement('div');
            messageContent.className = 'sanctum-message-content';
            const body = document.createElement('div');
            body.className = 'sanctum-message-body sanctum-markdown';
            body.innerHTML = utils.formatMessageHtml(greeting);
            messageContent.appendChild(body);
            const time = document.createElement('div');
            time.className = 'sanctum-message-time';
            time.textContent = utils.formatTime(new Date());
            messageContent.appendChild(time);
            messageDiv.appendChild(avatar);
            messageDiv.appendChild(messageContent);
            this.elements.messages.appendChild(messageDiv);
        },

        clearMessagePane() {
            if (!this.elements.messages) {
                return;
            }
            this.elements.messages.innerHTML = '';
            state.seenMessageIds = {};
            state.messageCount = 0;
        },

        async loadRecentHistory() {
            if (!state.sessionId || state.historyLoading) {
                return;
            }
            state.historyLoading = true;
            try {
                const limit = config.historyLimit || 6;
                const data = await api.getHistory(limit);
                const items = (data && data.items) ? data.items : [];

                this.clearMessagePane();

                if (items.length === 0) {
                    this.renderGreeting();
                } else {
                    items.forEach((item) => {
                        const role = item.role === 'user' ? 'user' : 'bot';
                        const ts = item.timestamp ? new Date(item.timestamp) : new Date();
                        const opts = {
                            messageId: item.id,
                            timestamp: ts,
                            skipSeenCheck: true,
                        };
                        if (item.display && role === 'user') {
                            opts.display = item.display;
                        }
                        this.addMessage(item.text, role, opts);
                    });
                }

                if (data && data.crm_user_id) {
                    config.crmUserId = data.crm_user_id;
                }
                if (data && data.latest_response_at) {
                    state.lastResponseSince = data.latest_response_at;
                }
                try {
                    const uid = (data && data.crm_user_id) ? String(data.crm_user_id) : '0';
                    const sinceKey = 'sanctum_len_since_u_' + uid;
                    if (state.lastResponseSince) {
                        localStorage.setItem(sinceKey, state.lastResponseSince);
                    }
                } catch (e) { /* ignore */ }
            } catch (error) {
                console.warn('Could not load Q chat history:', error);
                if (this.elements.messages && this.elements.messages.childElementCount === 0) {
                    this.renderGreeting();
                }
            } finally {
                state.historyLoading = false;
            }
        },

        // Add message to chat
        addMessage(content, type = 'bot', options) {
            options = options || {};
            if (options.messageId) {
                if (state.seenMessageIds[options.messageId]) {
                    return;
                }
                state.seenMessageIds[options.messageId] = true;
            } else if (!options.skipSeenCheck && type === 'user') {
                options.messageId = 'local-u-' + Date.now();
                state.seenMessageIds[options.messageId] = true;
            }

            const messageDiv = document.createElement('div');
            messageDiv.className = `sanctum-message ${type}`;
            
            const avatar = document.createElement('div');
            avatar.className = 'sanctum-message-avatar';
            const chatter = (config.chatterUsername || '').trim();
            avatar.textContent = type === 'user'
                ? (chatter ? chatter.charAt(0).toUpperCase() : 'U')
                : 'Q';
            
            const messageContent = document.createElement('div');
            messageContent.className = 'sanctum-message-content';
            const body = document.createElement('div');
            const hasComposerDisplay = type === 'user' && options.display
                && (options.display.caption || (options.display.attachments && options.display.attachments.length));
            if (hasComposerDisplay && state.composer) {
                body.className = 'sanctum-message-body sanctum-composer-bubble';
                body.innerHTML = state.composer.renderBubbleHtml(options.display);
            } else if (hasComposerDisplay) {
                body.className = 'sanctum-message-body sanctum-composer-bubble';
                body.innerHTML = this.renderComposerDisplayFallback(options.display);
            } else {
                body.className = 'sanctum-message-body sanctum-markdown';
                body.innerHTML = utils.formatMessageHtml(content);
            }
            messageContent.appendChild(body);

            const time = document.createElement('div');
            time.className = 'sanctum-message-time';
            time.textContent = utils.formatTime(options.timestamp || new Date());

            messageContent.appendChild(time);
            messageDiv.appendChild(avatar);
            messageDiv.appendChild(messageContent);
            
            this.elements.messages.appendChild(messageDiv);
            
            if (!options.skipScroll) {
                this.elements.messages.scrollTop = this.elements.messages.scrollHeight;
            }
            
            state.messageCount++;
            
            if (!state.isOpen && type === 'bot') {
                this.showNotification();
            }
        },

        // Show typing indicator
        showTypingIndicator() {
            if (this.elements.typingIndicator) {
                this.elements.typingIndicator.remove();
            }
            
            const typingDiv = document.createElement('div');
            typingDiv.className = 'sanctum-typing-indicator';
            typingDiv.id = 'sanctum-typing-indicator';
            
            for (let i = 0; i < 3; i++) {
                const dot = document.createElement('div');
                dot.className = 'sanctum-typing-dot';
                typingDiv.appendChild(dot);
            }
            
            this.elements.messages.appendChild(typingDiv);
            this.elements.typingIndicator = typingDiv;
            this.elements.messages.scrollTop = this.elements.messages.scrollHeight;
        },

        // Hide typing indicator
        hideTypingIndicator() {
            if (this.elements.typingIndicator) {
                this.elements.typingIndicator.remove();
                this.elements.typingIndicator = null;
            }
        },

        // Show notification
        showNotification() {
            if (!config.notifications) return;
            
            // Update badge
            const currentCount = parseInt(this.elements.notificationBadge.textContent) || 0;
            this.elements.notificationBadge.textContent = currentCount + 1;
            this.elements.notificationBadge.classList.remove('sanctum-hidden');
            
            // Browser notification
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification(config.title, {
                    body: 'You have a new message',
                    icon: '/widget/static/assets/icons/chat-icon.svg'
                });
            }
            
            // Sound notification
            if (config.sound) {
                this.playNotificationSound();
            }
        },

        // Clear notifications
        clearNotifications() {
            this.elements.notificationBadge.classList.add('sanctum-hidden');
            this.elements.notificationBadge.textContent = '0';
        },

        // Play notification sound
        playNotificationSound() {
            try {
                const audio = new Audio('/widget/static/assets/sounds/notification.mp3');
                audio.volume = 0.5;
                audio.play().catch(() => {
                    // Fallback: create a simple beep
                    const context = new (window.AudioContext || window.webkitAudioContext)();
                    const oscillator = context.createOscillator();
                    const gainNode = context.createGain();
                    
                    oscillator.connect(gainNode);
                    gainNode.connect(context.destination);
                    
                    oscillator.frequency.value = 800;
                    gainNode.gain.value = 0.1;
                    
                    oscillator.start();
                    setTimeout(() => oscillator.stop(), 200);
                });
            } catch (error) {
                console.warn('Could not play notification sound:', error);
            }
        },

        renderComposerDisplayFallback(display) {
            const esc = (window.AskQComposerUtils && window.AskQComposerUtils.escapeHtml)
                ? window.AskQComposerUtils.escapeHtml
                : function (s) {
                    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                };
            const fmt = (window.AskQComposerUtils && window.AskQComposerUtils.formatBytes)
                ? window.AskQComposerUtils.formatBytes
                : function (n) { return n + ' B'; };
            let html = '';
            if (display.caption) {
                html += '<div class="sanctum-composer-caption">' + esc(display.caption) + '</div>';
            }
            (display.attachments || []).forEach(function (a) {
                html += '<div class="sanctum-composer-bubble-attach">'
                    + '<span class="sanctum-composer-bubble-attach__icon">📄</span>'
                    + '<span class="sanctum-composer-bubble-attach__label"><strong>'
                    + esc(a.filename || 'attachment.txt') + '</strong> · '
                    + fmt(a.size_bytes || 0) + '</span></div>';
            });
            return html;
        },

        // Update send button state
        updateSendButton() {
            const canSend = state.composer
                ? state.composer.canSend()
                : this.elements.input.value.trim().length > 0;
            this.elements.send.disabled = !canSend;
        },

        // Auto-resize textarea
        autoResizeTextarea() {
            const textarea = this.elements.input;
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
        },

        // Set theme
        setTheme(theme) {
            this.elements.widget.setAttribute('data-theme', theme);
            config.theme = theme;
        },

        // Update configuration
        updateConfig(newConfig) {
            Object.assign(config, newConfig);
            
            // Update theme
            if (newConfig.theme) {
                this.setTheme(newConfig.theme);
            }
            
            // Update title
            if (newConfig.title) {
                this.elements.title.textContent = newConfig.title;
            }
            
            // Update position
            if (newConfig.position) {
                this.elements.widget.setAttribute('data-position', newConfig.position);
            }
            
            // Update primary color
            if (newConfig.primaryColor) {
                this.applyCustomStyles();
            }
        },

        clearPollTimer() {
            if (state.pollTimer) {
                clearTimeout(state.pollTimer);
                state.pollTimer = null;
            }
        },

        nextPollDelayMs() {
            const now = Date.now();
            if (state.pollRateLimitedUntil > now) {
                return Math.max(5000, state.pollRateLimitedUntil - now);
            }
            if (document.visibilityState === 'hidden') {
                return config.pollIntervalHiddenMs || 45000;
            }
            if (state.waitingForReply) {
                return config.pollIntervalActiveMs || 3000;
            }
            return config.pollIntervalIdleMs || 12000;
        },

        schedulePoll(delayMs) {
            this.clearPollTimer();
            if (!state.isOpen || !state.sessionId) {
                return;
            }
            const delay = typeof delayMs === 'number' ? delayMs : this.nextPollDelayMs();
            state.pollTimer = setTimeout(() => this.pollForResponses(), delay);
        },

        // Poll for responses
        async pollForResponses() {
            if (!state.sessionId) return;
            state.pollTimer = null;

            try {
                const since = state.lastResponseSince || '';
                const responses = await api.getMessages(since);

                if (responses && responses.length > 0) {
                    this.hideTypingIndicator();
                    state.waitingForReply = false;
                    state.pollErrorShown = false;

                    responses.forEach(response => {
                        const rid = response.id ? ('r-' + response.id) : null;
                        this.addMessage(response.response, 'bot', { messageId: rid });
                        this.emit('message', { message: response.response, type: 'bot' });
                        if (response.timestamp) {
                            state.lastResponseSince = response.timestamp;
                            try {
                                const sinceUid = config.crmUserId || config.crmUserId;
                                const sinceKey = sinceUid
                                    ? ('sanctum_len_since_u_' + sinceUid)
                                    : ('sanctum_len_since_' + state.sessionId);
                                localStorage.setItem(sinceKey, state.lastResponseSince);
                            } catch (e) { /* ignore */ }
                        }
                    });
                    if (responses.length) {
                        this.elements.messages.scrollTop = this.elements.messages.scrollHeight;
                    }
                }

                if (state.isOpen) {
                    this.schedulePoll();
                }

            } catch (error) {
                console.error('Failed to poll for responses:', error);
                const status = error && error.httpStatus;
                if (status === 429) {
                    const retrySec = (error.apiBody && error.apiBody.retry_after)
                        ? parseInt(error.apiBody.retry_after, 10)
                        : 60;
                    state.pollRateLimitedUntil = Date.now() + (retrySec * 1000);
                    if (!state.pollErrorShown) {
                        state.pollErrorShown = true;
                        const mins = Math.max(1, Math.ceil(retrySec / 60));
                        this.addMessage(
                            'Ask Len is rate-limited on updates — still waiting for Q. Retrying in ~'
                            + mins + ' min (refreshing the page also helps).',
                            'error'
                        );
                    }
                    // Keep typing dots while we are waiting for a reply
                    if (state.waitingForReply) {
                        this.showTypingIndicator();
                    }
                } else {
                    this.hideTypingIndicator();
                }

                if (state.isOpen) {
                    this.schedulePoll(status === 429 ? null : 10000);
                }
            }
        },

        // Emit events
        emit(event, data) {
            if (state.eventListeners[event]) {
                state.eventListeners[event].forEach(callback => {
                    try {
                        callback(data);
                    } catch (error) {
                        console.error('Event listener error:', error);
                    }
                });
            }
        }
    };

    // Public API
    const SanctumChat = {
        // Initialize widget
        init(options = {}) {
            Object.assign(config, options);
            if (!config.useSessionAuth && !config.apiKey) {
                throw new Error('API key is required unless useSessionAuth is true');
            }

            if (!state.isInitialized) {
                state.uid = utils.generateUid();
                ui.init();
                if (config.title && ui.elements && ui.elements.title) {
                    ui.elements.title.textContent = config.title;
                }
                ui.applyChatterLabel();
                state.isInitialized = true;

                document.addEventListener('visibilitychange', function () {
                    if (document.visibilityState === 'visible' && state.isOpen) {
                        ui.schedulePoll(500);
                    }
                });

                if (config.persistSession && config.useSessionAuth) {
                    api.bootstrapUserSession().catch(function () { /* logged in widget */ });
                } else if (config.persistSession) {
                    try {
                        const storageKey = SESSION_LOCAL_KEY;
                        state.sessionId = localStorage.getItem(storageKey) || utils.generateSessionId();
                        localStorage.setItem(storageKey, state.sessionId);
                    } catch (e) {
                        state.sessionId = utils.generateSessionId();
                    }
                } else {
                    state.sessionId = utils.generateSessionId();
                }
            }

            return this;
        },

        // Open chat
        open() {
            if (state.isInitialized) {
                ui.open();
            }
        },

        // Close chat
        close() {
            if (state.isInitialized) {
                ui.close();
            }
        },

        // Toggle chat
        toggle() {
            if (state.isInitialized) {
                ui.toggle();
            }
        },

        // Send message programmatically
        sendMessage(message) {
            if (state.isInitialized && message) {
                ui.elements.input.value = message;
                ui.sendMessage();
            }
        },

        // Update configuration
        updateConfig(newConfig) {
            if (state.isInitialized) {
                ui.updateConfig(newConfig);
            }
        },

        // Event listeners
        on(event, callback) {
            if (!state.eventListeners[event]) {
                state.eventListeners[event] = [];
            }
            state.eventListeners[event].push(callback);
        },

        off(event, callback) {
            if (state.eventListeners[event]) {
                const index = state.eventListeners[event].indexOf(callback);
                if (index > -1) {
                    state.eventListeners[event].splice(index, 1);
                }
            }
        },

        // Destroy widget
        destroy() {
            if (state.isInitialized) {
                const widget = document.querySelector('.sanctum-chat-widget');
                if (widget) {
                    widget.remove();
                }
                state.isInitialized = false;
                state.eventListeners = {};
            }
        },

        // Get widget state
        getState() {
            return { ...state };
        },

        // Get configuration
        getConfig() {
            return { ...config };
        },

        // Simulate typing (for demo purposes)
        simulateTyping() {
            if (state.isInitialized) {
                ui.showTypingIndicator();
                setTimeout(() => ui.hideTypingIndicator(), 3000);
            }
        },

        // Clear chat
        clearChat() {
            if (state.isInitialized) {
                ui.elements.messages.innerHTML = `
                    <div class="sanctum-message">
                        <div class="sanctum-message-avatar">S</div>
                        <div class="sanctum-message-content">
                            Chat cleared. How can I help you?
                            <div class="sanctum-message-time">${utils.formatTime(new Date())}</div>
                        </div>
                    </div>
                `;
                state.messageCount = 1;
            }
        }
    };

    // Expose to global scope
    window.SanctumChat = SanctumChat;

    // Auto-initialize from data attributes
    document.addEventListener('DOMContentLoaded', () => {
        const script = document.currentScript || document.querySelector('script[src*="chat-widget.js"]');
        if (script) {
            const apiKey = script.getAttribute('data-api-key');
            const position = script.getAttribute('data-position');
            const theme = script.getAttribute('data-theme');
            const title = script.getAttribute('data-title');
            
            if (apiKey) {
                SanctumChat.init({
                    apiKey,
                    position: position || 'bottom-right',
                    theme: theme || 'light',
                    title: title || 'Chat with us'
                });
            }
        }
    });

})(window, document);
