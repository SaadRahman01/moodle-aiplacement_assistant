// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Floating button and side panel for the Moodle Assistant.
 *
 * @module     aiplacement_assistant/chat
 * @copyright  2026 Saad Rahman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Notification from 'core/notification';
import Policy from 'core_ai/policy';
import * as FocusLock from 'core/local/aria/focuslock';
import {getString} from 'core/str';

/** @type {number} Maximum length of page help text sent to the server. */
const PAGEHELP_MAX = 8000;

/** @type {string[]} Error codes returned by the server that have their own string. */
const KNOWN_ERRORS = ['notavailable', 'policynotaccepted', 'messagetoolong', 'messageempty', 'ratelimited', 'aifailed'];

/** @type {string} Help icon popovers on Bootstrap 5 (Moodle 5.x) and Bootstrap 4 (Moodle 4.5). */
const HELP_SELECTOR = 'a.btn[data-bs-toggle="popover"], a.btn[data-toggle="popover"]';

const SELECTORS = {
    ROOT: '[data-region="aiplacement_assistant-panel"]',
    OPEN: '[data-action="aiplacement_assistant-open"]',
    CLOSE: '[data-action="aiplacement_assistant-close"]',
    DRAWER: '[data-region="aiplacement_assistant-drawer"]',
    BODY: '[data-region="aiplacement_assistant-body"]',
    POLICY: '[data-region="aiplacement_assistant-policy"]',
    MESSAGES: '[data-region="aiplacement_assistant-messages"]',
    THINKING: '[data-region="aiplacement_assistant-thinking"]',
    FORM: '[data-region="aiplacement_assistant-form"]',
    INPUT: '[data-region="aiplacement_assistant-input"]',
    SEND: '[data-action="aiplacement_assistant-send"]',
    INTENT: '[data-action="aiplacement_assistant-intent"]',
    ACCEPT: '[data-action="accept"]',
    DECLINE: '[data-action="decline"]',
};

/**
 * Escape plain text the same way the server formats FORMAT_PLAIN text (escaped, newlines as <br>).
 *
 * @param {string} text Raw text.
 * @return {string} HTML.
 */
const plainToHtml = (text) => {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML.replace(/\r?\n/g, '<br>');
};

/**
 * Collect the text of the help popovers on the page.
 *
 * @param {HTMLElement} root The assistant root, whose own content is ignored.
 * @return {string} Help text, at most PAGEHELP_MAX characters.
 */
const collectPageHelp = (root) => {
    const parser = new DOMParser();
    const seen = new Set();
    document.querySelectorAll(HELP_SELECTOR).forEach((el) => {
        if (root.contains(el)) {
            return;
        }
        const html = el.getAttribute('data-bs-content') || el.getAttribute('data-content') || '';
        // DOMParser does not run scripts or load resources; we only want the text.
        const text = (parser.parseFromString(html, 'text/html').body.textContent || '').replace(/\s+/g, ' ').trim();
        if (text !== '') {
            seen.add(text);
        }
    });
    return Array.from(seen).join('\n').slice(0, PAGEHELP_MAX);
};

class Chat {
    /**
     * Constructor.
     *
     * @param {HTMLElement} root The panel root element.
     * @param {number} contextId Context id.
     * @param {string} docsUrl Documentation URL for this page.
     * @param {number} userId Current user id.
     */
    constructor(root, contextId, docsUrl, userId) {
        this.root = root;
        this.contextId = contextId;
        this.docsUrl = docsUrl;
        this.userId = userId;
        this.openButton = root.querySelector(SELECTORS.OPEN);
        this.drawer = root.querySelector(SELECTORS.DRAWER);
        this.body = root.querySelector(SELECTORS.BODY);
        this.policy = root.querySelector(SELECTORS.POLICY);
        this.messages = root.querySelector(SELECTORS.MESSAGES);
        this.thinking = root.querySelector(SELECTORS.THINKING);
        this.form = root.querySelector(SELECTORS.FORM);
        this.input = root.querySelector(SELECTORS.INPUT);
        this.sendButton = root.querySelector(SELECTORS.SEND);
        this.started = false;
        this.preparing = false;
        this.busy = false;
        this.registerEventListeners();
    }

    /**
     * Register event listeners.
     */
    registerEventListeners() {
        this.openButton.addEventListener('click', () => {
            if (this.isOpen()) {
                this.close();
            } else {
                this.open();
            }
        });
        this.root.querySelector(SELECTORS.CLOSE).addEventListener('click', () => this.close());
        this.drawer.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                this.close();
            }
        });
        this.form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.sendFromInput();
        });
        this.input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendFromInput();
            }
        });
        this.messages.addEventListener('click', (e) => {
            const button = e.target.closest(SELECTORS.INTENT);
            if (button) {
                e.preventDefault();
                this.send('', button.dataset.intent, button.textContent.trim());
            }
        });
    }

    /**
     * Whether the panel is open.
     *
     * @return {boolean}
     */
    isOpen() {
        return !this.drawer.hidden;
    }

    /**
     * Open the panel, checking the AI policy on first use.
     */
    async open() {
        this.drawer.hidden = false;
        this.openButton.setAttribute('aria-expanded', 'true');
        FocusLock.trapFocus(this.drawer);
        if (this.started) {
            this.input.focus();
            return;
        }
        if (this.preparing) {
            return;
        }
        this.preparing = true;
        try {
            const accepted = await Policy.getPolicyStatus(this.userId);
            if (accepted) {
                await this.start();
            } else {
                await this.displayPolicy();
            }
        } catch (error) {
            Notification.exception(error);
        } finally {
            this.preparing = false;
        }
    }

    /**
     * Close the panel and return focus to the button.
     */
    close() {
        if (!this.isOpen()) {
            return;
        }
        FocusLock.untrapFocus();
        this.drawer.hidden = true;
        this.openButton.setAttribute('aria-expanded', 'false');
        this.openButton.focus();
    }

    /**
     * Show the AI policy, as aiplacement_courseassist does.
     */
    async displayPolicy() {
        const {html, js} = await Templates.renderForPromise('core_ai/policyblock', {});
        Templates.replaceNodeContents(this.policy, html, js);
        this.policy.querySelector(SELECTORS.ACCEPT)?.addEventListener('click', (e) => {
            e.preventDefault();
            Policy.acceptPolicy().then(() => {
                this.policy.innerHTML = '';
                return this.start();
            }).catch(Notification.exception);
        });
        this.policy.querySelector(SELECTORS.DECLINE)?.addEventListener('click', (e) => {
            e.preventDefault();
            this.close();
        });
        this.policy.querySelector(SELECTORS.ACCEPT)?.focus();
    }

    /**
     * Show the conversation form and load history.
     */
    async start() {
        if (this.started) {
            return;
        }
        this.started = true;
        try {
            const response = await Ajax.call([{
                methodname: 'aiplacement_assistant_get_history',
                args: {contextid: this.contextId},
            }])[0];
            // History text is already formatted (escaped) by the server. Render in parallel, append in order.
            const rendered = await Promise.all(response.messages.map((message) =>
                Templates.renderForPromise('aiplacement_assistant/message', {isuser: message.role === 'user', text: message.text})
            ));
            for (const {html, js} of rendered) {
                Templates.appendNodeContents(this.messages, html, js);
            }
            this.body.scrollTop = this.body.scrollHeight;
        } finally {
            // Show the form only once history is in place, so a new message is never rendered above it.
            this.form.hidden = false;
            this.input.focus();
        }
    }

    /**
     * Send the text typed in the input box.
     */
    sendFromInput() {
        const message = this.input.value.trim();
        if (message === '') {
            this.displayError('messageempty');
            return;
        }
        this.input.value = '';
        this.send(message, '', message);
    }

    /**
     * Send a message to the assistant and show the reply.
     *
     * @param {string} message The message text (may be empty when an intent is picked).
     * @param {string} intent The picked intent, or ''.
     * @param {string} display The text to show as the user's message.
     */
    async send(message, intent, display) {
        if (this.busy) {
            return;
        }
        this.setBusy(true);
        try {
            await this.appendMessage({isuser: true, text: plainToHtml(display)});
            const response = await Ajax.call([{
                methodname: 'aiplacement_assistant_send_message',
                args: {
                    contextid: this.contextId,
                    message: message,
                    intent: intent,
                    pagehelp: collectPageHelp(this.root),
                    docsurl: this.docsUrl,
                },
            }])[0];
            if (!response.success) {
                await this.displayError(response.error);
            } else {
                // The reply text is already formatted (escaped) by the server; other fields are escaped by the template.
                await this.appendMessage({
                    isuser: false,
                    text: response.text,
                    rows: response.rows,
                    links: response.links,
                    menu: response.menu,
                });
            }
        } catch (error) {
            window.console.error(error);
            await this.displayError('');
        } finally {
            this.setBusy(false);
        }
    }

    /**
     * Toggle the busy state.
     *
     * @param {boolean} busy Whether a request is in progress.
     */
    setBusy(busy) {
        this.busy = busy;
        this.thinking.hidden = !busy;
        this.sendButton.disabled = busy;
        this.messages.setAttribute('aria-busy', busy ? 'true' : 'false');
    }

    /**
     * Show an error from its code.
     *
     * @param {string} code Error code from the server, or '' for an unexpected failure.
     */
    async displayError(code) {
        const key = KNOWN_ERRORS.includes(code) ? 'error_' + code : 'error_generic';
        const text = await getString(key, 'aiplacement_assistant');
        await this.appendMessage({isuser: false, iserror: true, text: plainToHtml(text)});
    }

    /**
     * Render and append a message.
     *
     * @param {Object} context Template context for aiplacement_assistant/message.
     */
    async appendMessage(context) {
        const {html, js} = await Templates.renderForPromise('aiplacement_assistant/message', context);
        Templates.appendNodeContents(this.messages, html, js);
        this.body.scrollTop = this.body.scrollHeight;
    }
}

/**
 * Initialise the assistant.
 *
 * @param {number} contextId Context id of the current page.
 * @param {string} docsUrl Documentation URL for the current page.
 * @param {number} userId Current user id.
 */
export const init = (contextId, docsUrl, userId) => {
    const root = document.querySelector(SELECTORS.ROOT);
    if (!root || root.dataset.initialised) {
        return;
    }
    root.dataset.initialised = '1';
    new Chat(root, contextId, docsUrl, userId);
};
