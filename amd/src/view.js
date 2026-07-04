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
 * Task activity controller. Hydrates every [data-region="mod_task"] placeholder,
 * whether on the activity page or embedded via the {task:Name} filter.
 *
 * init() is idempotent and re-scans the DOM, and the module also listens for the
 * filterContentUpdated event, so Task placeholders brought in by Snap's
 * coursepartialrender AJAX section loading are hydrated too.
 *
 * @module     mod_task/view
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Notification from 'core/notification';
import {get_string as getString} from 'core/str';
import {eventTypes as filterEventTypes} from 'core_filters/events';
import {loadQuill, makeEditor} from 'mod_task/editor';
import {startTicker, tick as tickTimeAgo} from 'mod_task/timeago';
import {renderReactionsBarInto, openPicker, closeAllPickers} from 'mod_task/reactions';

const SEL_ROOT = '[data-region="mod_task"]';
const MAX_VISUAL_INDENT = 3;
const LAZY_ROOT_MARGIN = '100px 0px';
const EMPTY_HTML = ['', '<p><br></p>', '<p></p>'];
// Other responses are revealed four at a time; the area becomes scrollable and
// loads the next four as the viewer nears the bottom.
const OTHER_BATCH = 4;
const SCROLL_THRESHOLD = 80;

const initialised = new WeakSet();
let lazyObserver = null;
let globalsBound = false;

/**
 * Save the chosen notification preference for a Task.
 *
 * The settings panel is self-contained and rendered both inside the Task shell
 * and (on the activity page) in the activity header, so this is bound once at
 * the document level rather than per Task instance.
 *
 * @param {HTMLElement} button the clicked preference button
 */
const setNotificationPreference = (button) => {
    const panel = button.closest('[data-region="task-notify-settings"]');
    if (!panel) {
        return;
    }
    const cmid = parseInt(panel.dataset.cmid, 10);
    const preference = parseInt(button.dataset.pref, 10);
    if (Number.isNaN(cmid) || Number.isNaN(preference)) {
        return;
    }

    // Reflect the choice immediately; the save is best-effort.
    panel.querySelectorAll('[data-action="set-notify-pref"]').forEach(btn => {
        const active = btn === button;
        btn.classList.toggle('active', active);
        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    });

    Ajax.call([{
        methodname: 'mod_task_set_notification_preference',
        args: {cmid, preference},
    }])[0].catch(Notification.exception);
};

/**
 * Bind page-wide listeners exactly once: close pickers on outside click, save
 * notification preference changes, and re-scan when the filter system reports
 * new content (Snap partial render).
 */
const bindGlobals = () => {
    if (globalsBound) {
        return;
    }
    globalsBound = true;
    document.addEventListener('click', (e) => {
        if (!e.target.closest('[data-region="reactions-bar"]')) {
            closeAllPickers();
        }
        const prefButton = e.target.closest('[data-action="set-notify-pref"]');
        if (prefButton) {
            setNotificationPreference(prefButton);
        }
    });
    document.addEventListener(filterEventTypes.filterContentUpdated, () => init());
};

const getLazyObserver = () => {
    if (lazyObserver || typeof IntersectionObserver === 'undefined') {
        return lazyObserver;
    }
    lazyObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) {
                return;
            }
            observer.unobserve(entry.target);
            new TaskView(entry.target).load();
        });
    }, {rootMargin: LAZY_ROOT_MARGIN});
    return lazyObserver;
};

/**
 * Public entry point. Hydrates every Task placeholder not already handled.
 */
export const init = () => {
    bindGlobals();
    const observer = getLazyObserver();
    document.querySelectorAll(SEL_ROOT).forEach(el => {
        if (initialised.has(el)) {
            return;
        }
        initialised.add(el);
        if (observer) {
            observer.observe(el);
        } else {
            new TaskView(el).load();
        }
    });
    startTicker();
};

/**
 * Whether an editor's HTML is effectively empty.
 *
 * @param {string} html the editor HTML
 * @return {boolean}
 */
const isEmptyHtml = (html) => !html || EMPTY_HTML.includes(html);

/**
 * Controller for a single Task placeholder.
 */
class TaskView {
    /**
     * @param {HTMLElement} root the placeholder element
     */
    constructor(root) {
        this.root = root;
        this.cmid = parseInt(root.dataset.cmid, 10);
        // The activity page suppresses the description (the theme renders the
        // activity intro itself); a {task:Name} filter embed shows it.
        this.showDescription = root.dataset.showdescription !== '0';
        this.data = null;
        this.emojis = {};
        this.sortMode = 'newest';
        this.composerEditor = null;
        this.activeReply = null;
        this.activeEdit = null;
        // A flat copy of every post, plus the progressive-render bookkeeping for
        // the scrollable "Other responses" area.
        this.allPosts = [];
        this.otherTops = [];
        this.otherRendered = 0;
        this.loadingBatch = false;
        // Delegated click handling is bound once here; applyData() replaces the
        // root's contents but not the root itself, so rebinding there would
        // stack a duplicate listener on every dynamic re-render.
        this.root.addEventListener('click', this.onClick.bind(this));
    }

    /**
     * Load the Task payload and mark it as viewed.
     */
    load() {
        Ajax.call([{
            methodname: 'mod_task_get_task',
            args: {cmid: this.cmid},
        }])[0].then(data => this.applyData(data)).catch(Notification.exception);

        // Clear the course-card "new responses" badge for this user (best effort).
        Ajax.call([{
            methodname: 'mod_task_mark_viewed',
            args: {cmid: this.cmid},
        }])[0].catch(() => {
            return;
        });
    }

    /**
     * Store a fresh payload, render the shell, and wire up handlers.
     *
     * @param {object} data the view payload
     */
    async applyData(data) {
        this.data = data;
        data.showdescription = this.showDescription;
        this.emojis = {};
        (data.emojis || []).forEach(e => {
            this.emojis[e.shortcode] = e.unicode;
        });
        this.composerEditor = null;
        this.activeReply = null;
        this.activeEdit = null;

        const {html, js} = await Templates.renderForPromise('mod_task/task', data);
        await Templates.replaceNodeContents(this.root, html, js);
        await this.renderPosts();
        await this.initComposer();
    }

    /**
     * Initialise the always-visible response composer editor, if one is shown.
     *
     * The "Add your response" button has been replaced by the editor panel, so
     * the editor is created eagerly whenever the composer is present.
     */
    async initComposer() {
        const expanded = this.root.querySelector('[data-region="composer-expanded"]');
        if (!expanded) {
            return;
        }
        await loadQuill();
        const placeholder = await getString('writeresponse', 'mod_task');
        this.composerEditor = makeEditor(expanded.querySelector('[data-region="editor"]'), placeholder);
    }

    /**
     * Render the viewer's own response and everyone else's responses into their
     * respective panels.
     */
    async renderPosts() {
        this.allPosts = (this.data.posts || []).map(p => ({...p}));
        await this.renderYourResponse();
        await this.renderOtherResponses();
        tickTimeAgo();
    }

    /**
     * Render the viewer's own top-level response(s) as collapsible panel(s) in
     * the dedicated "Your response" area. Nothing is shown until the viewer has
     * a response of their own; before that, the composer occupies the area.
     */
    async renderYourResponse() {
        const region = this.root.querySelector('[data-region="your-response-posts"]');
        if (!region) {
            return;
        }
        region.innerHTML = '';
        const ownTops = this.allPosts
            .filter(p => p.parentid === 0 && p.ismine)
            .sort((a, b) => b.timecreated - a.timecreated);
        for (const top of ownTops) {
            region.appendChild(await this.buildResponsePanel(top, true));
        }
    }

    /**
     * Render everyone else's top-level responses into the scrollable area, four
     * at a time, and (re)wire the scroll handler that loads further batches.
     */
    async renderOtherResponses() {
        const container = this.root.querySelector('[data-region="posts"]');
        if (!container) {
            return;
        }
        container.innerHTML = '';
        this.otherTops = this.allPosts
            .filter(p => p.parentid === 0 && !p.ismine)
            .sort((a, b) => this.sortMode === 'newest'
                ? b.timecreated - a.timecreated
                : a.timecreated - b.timecreated);
        this.otherRendered = 0;
        await this.renderNextOtherBatch();
        this.bindScroll();

        const empty = this.root.querySelector('[data-region="empty"]');
        if (empty) {
            empty.classList.toggle('d-none', this.otherTops.length > 0);
        }
        const countEl = this.root.querySelector('[data-region="post-count"]');
        if (countEl) {
            const n = this.otherTops.length;
            countEl.textContent = await getString(n === 1 ? 'oneresponse' : 'nresponses', 'mod_task', n);
        }
    }

    /**
     * Append the next batch of other responses, and constrain the area to a
     * scrollable window once more than one batch exists.
     */
    async renderNextOtherBatch() {
        const container = this.root.querySelector('[data-region="posts"]');
        if (this.loadingBatch || !container || this.otherRendered >= this.otherTops.length) {
            return;
        }
        this.loadingBatch = true;
        const batch = this.otherTops.slice(this.otherRendered, this.otherRendered + OTHER_BATCH);
        for (const top of batch) {
            container.appendChild(await this.buildResponsePanel(top, false));
        }
        this.otherRendered += batch.length;
        this.loadingBatch = false;

        const scroll = this.root.querySelector('[data-region="responses-scroll"]');
        if (scroll) {
            scroll.classList.toggle('mod-task-scrollable', this.otherTops.length > OTHER_BATCH);
        }
        tickTimeAgo();
    }

    /**
     * Load the next batch when the scrollable area nears its bottom. Bound once
     * per rendered area (the element is recreated on every full re-render).
     */
    bindScroll() {
        const scroll = this.root.querySelector('[data-region="responses-scroll"]');
        if (!scroll || scroll.dataset.scrollBound === '1') {
            return;
        }
        scroll.dataset.scrollBound = '1';
        scroll.addEventListener('scroll', () => {
            if (this.otherRendered >= this.otherTops.length) {
                return;
            }
            if (scroll.scrollTop + scroll.clientHeight >= scroll.scrollHeight - SCROLL_THRESHOLD) {
                this.renderNextOtherBatch();
            }
        });
    }

    /**
     * Build a collapsible panel wrapping one top-level response and its replies.
     *
     * @param {object} top the top-level post object
     * @param {boolean} own whether this is the viewer's own response
     * @return {Promise<HTMLElement>} the panel element, with its tree rendered
     */
    async buildResponsePanel(top, own) {
        const [hidelabel, showlabel] = await Promise.all([
            getString(own ? 'hideyourresponse' : 'hideresponse', 'mod_task'),
            getString(own ? 'showyourresponse' : 'showresponse', 'mod_task'),
        ]);
        const {html, js} = await Templates.renderForPromise('mod_task/response_panel', {
            uid: `${this.data.taskid}_${top.id}`,
            postid: top.id,
            title: own ? '' : top.authorname,
            hidelabel,
            showlabel,
            expanded: true,
            own,
        });
        const tmp = document.createElement('div');
        tmp.innerHTML = html;
        const node = tmp.firstElementChild;
        await this.appendTree(node.querySelector('[data-region="panel-body"]'), top, 0, this.allPosts);
        Templates.runTemplateJS(js);
        return node;
    }

    /**
     * Recursively append a post and its replies.
     *
     * @param {HTMLElement} container the parent element
     * @param {object} post the post object
     * @param {number} depth the visual indent depth
     * @param {Array<object>} all the flat list of all posts
     */
    async appendTree(container, post, depth, all) {
        const ctx = {...post, indent: Math.min(depth, MAX_VISUAL_INDENT)};
        const {html, js} = await Templates.renderForPromise('mod_task/post', ctx);
        const tmp = document.createElement('div');
        tmp.innerHTML = html;
        const node = tmp.firstElementChild;
        if (!post.deleted) {
            await renderReactionsBarInto(node, post.reactions, {
                emojis: this.emojis,
                canreact: this.data.canreact,
            });
        }
        container.appendChild(node);
        Templates.runTemplateJS(js);

        const repliesContainer = node.querySelector('[data-region="replies"]');
        const children = all.filter(p => p.parentid === post.id)
            .sort((a, b) => a.timecreated - b.timecreated);
        for (const child of children) {
            await this.appendTree(repliesContainer, child, depth + 1, all);
        }
    }

    /**
     * Delegated click handler.
     *
     * @param {Event} e the click event
     */
    onClick(e) {
        const target = e.target.closest('[data-action]');
        if (!target || !this.root.contains(target)) {
            return;
        }
        switch (target.dataset.action) {
            case 'submit-compose': this.submitComposer(); break;
            case 'sort': this.changeSort(target.dataset.sort); break;
            case 'reply': this.openReply(target); break;
            case 'edit': this.openEdit(target); break;
            case 'delete': this.confirmDelete(target); break;
            case 'open-picker': openPicker(target); break;
            case 'toggle-reaction': this.toggleReaction(target); break;
            default:
        }
    }

    /**
     * Read the "respond anonymously" checkbox state within a container.
     *
     * @param {HTMLElement} scope the element to search within
     * @return {boolean}
     */
    anonChecked(scope) {
        const cb = scope.querySelector('[data-region="anonymous-toggle"]');
        return !!(cb && cb.checked);
    }

    async submitComposer() {
        if (!this.composerEditor) {
            return;
        }
        const html = this.composerEditor.root.innerHTML.trim();
        if (isEmptyHtml(html)) {
            return;
        }
        const expanded = this.root.querySelector('[data-region="composer-expanded"]');
        const anonymous = this.anonChecked(expanded);
        try {
            const data = await Ajax.call([{
                methodname: 'mod_task_create_response',
                args: {cmid: this.cmid, content: html, anonymous},
            }])[0];
            await this.applyData(data);
        } catch (e) {
            Notification.exception(e);
        }
    }

    async changeSort(sort) {
        this.sortMode = sort;
        this.root.querySelectorAll('[data-action="sort"]').forEach(btn => {
            btn.classList.toggle('mod-task-sort-active', btn.dataset.sort === sort);
        });
        // Sorting only reorders other responses; the viewer's own panel is left
        // untouched so its expand/collapse state is preserved.
        await this.renderOtherResponses();
        tickTimeAgo();
    }

    async openReply(button) {
        const postEl = button.closest('[data-region="post"]');
        if (!postEl) {
            return;
        }
        const parentId = parseInt(postEl.dataset.postid, 10);
        const repliesEl = postEl.querySelector('[data-region="replies"]');
        if (!repliesEl) {
            return;
        }
        if (this.activeReply) {
            this.closeReply();
        }
        await loadQuill();
        const wrap = await this.buildInlineComposer('reply', this.data.cananonymous);
        repliesEl.prepend(wrap.container);
        wrap.editor.focus();
        this.activeReply = {parentId, ...wrap};
        wrap.container.querySelector('[data-action="cancel-inline"]').addEventListener('click', () => this.closeReply());
        wrap.container.querySelector('[data-action="submit-inline"]').addEventListener('click', () => this.submitReply());
    }

    closeReply() {
        if (this.activeReply) {
            this.activeReply.container.remove();
            this.activeReply = null;
        }
    }

    async submitReply() {
        if (!this.activeReply) {
            return;
        }
        const html = this.activeReply.editor.root.innerHTML.trim();
        if (isEmptyHtml(html)) {
            return;
        }
        const anonymous = this.anonChecked(this.activeReply.container);
        try {
            const data = await Ajax.call([{
                methodname: 'mod_task_create_reply',
                args: {cmid: this.cmid, parentid: this.activeReply.parentId, content: html, anonymous},
            }])[0];
            await this.applyData(data);
        } catch (e) {
            Notification.exception(e);
        }
    }

    async openEdit(button) {
        const postEl = button.closest('[data-region="post"]');
        if (!postEl) {
            return;
        }
        const postId = parseInt(postEl.dataset.postid, 10);
        const contentEl = postEl.querySelector('[data-region="post-content"]');
        if (!contentEl) {
            return;
        }
        if (this.activeEdit) {
            this.closeEdit();
        }
        await loadQuill();
        const wrap = await this.buildInlineComposer('edit', false);
        const original = contentEl.innerHTML;
        contentEl.replaceWith(wrap.container);
        wrap.editor.root.innerHTML = original;
        wrap.editor.focus();
        this.activeEdit = {postId, originalContent: original, ...wrap};
        wrap.container.querySelector('[data-action="cancel-inline"]').addEventListener('click', () => this.closeEdit());
        wrap.container.querySelector('[data-action="submit-inline"]').addEventListener('click', () => this.submitEdit());
    }

    closeEdit() {
        if (!this.activeEdit) {
            return;
        }
        const restore = document.createElement('div');
        restore.className = 'mod-task-post-content';
        restore.dataset.region = 'post-content';
        restore.innerHTML = this.activeEdit.originalContent;
        this.activeEdit.container.replaceWith(restore);
        this.activeEdit = null;
    }

    async submitEdit() {
        if (!this.activeEdit) {
            return;
        }
        const html = this.activeEdit.editor.root.innerHTML.trim();
        if (isEmptyHtml(html)) {
            return;
        }
        try {
            const data = await Ajax.call([{
                methodname: 'mod_task_edit_post',
                args: {postid: this.activeEdit.postId, content: html},
            }])[0];
            await this.applyData(data);
        } catch (e) {
            Notification.exception(e);
        }
    }

    /**
     * Build an inline reply/edit composer (editor + optional anon checkbox + actions).
     *
     * @param {string} kind 'reply' or 'edit'
     * @param {boolean} withAnon whether to include the anonymous checkbox
     * @return {Promise<{container: HTMLElement, editor: object}>}
     */
    async buildInlineComposer(kind, withAnon) {
        const cancelStr = await getString('cancel', 'mod_task');
        const submitStr = await getString(kind === 'edit' ? 'save' : 'post', 'mod_task');
        const anonStr = await getString('respondanonymously', 'mod_task');
        const placeholder = await getString('writeresponse', 'mod_task');
        const wrap = document.createElement('div');
        wrap.className = 'mod-task-inline-composer';
        wrap.innerHTML =
            '<div class="mod-task-editor" data-region="inline-editor"></div>' +
            (withAnon
                ? '<div class="form-check mod-task-anon-check">' +
                  '<input class="form-check-input" type="checkbox" data-region="anonymous-toggle">' +
                  `<label class="form-check-label">${anonStr}</label></div>`
                : '') +
            '<div class="mod-task-composer-actions">' +
            `<button type="button" class="btn btn-link" data-action="cancel-inline">${cancelStr}</button>` +
            `<button type="button" class="btn btn-primary" data-action="submit-inline">${submitStr}</button>` +
            '</div>';
        const editor = makeEditor(wrap.querySelector('[data-region="inline-editor"]'), placeholder);
        return {container: wrap, editor};
    }

    async confirmDelete(button) {
        const postEl = button.closest('[data-region="post"]');
        if (!postEl) {
            return;
        }
        const postId = parseInt(postEl.dataset.postid, 10);
        try {
            await Notification.deleteCancelPromise(
                await getString('delete', 'mod_task'),
                await getString('deleteconfirm', 'mod_task')
            );
        } catch {
            return;
        }
        try {
            const data = await Ajax.call([{
                methodname: 'mod_task_delete_post',
                args: {postid: postId},
            }])[0];
            await this.applyData(data);
        } catch (e) {
            Notification.exception(e);
        }
    }

    /**
     * Toggle an emoji reaction and re-render the post's reactions bar in place.
     *
     * @param {HTMLElement} button the clicked toggle-reaction control
     */
    async toggleReaction(button) {
        const postEl = button.closest('[data-region="post"]');
        if (!postEl) {
            return;
        }
        const postId = parseInt(postEl.dataset.postid, 10);
        const emoji = button.dataset.emoji;
        closeAllPickers();
        try {
            const result = await Ajax.call([{
                methodname: 'mod_task_react_post',
                args: {postid: postId, emoji},
            }])[0];
            const reactions = {counts: result.counts, userreactions: result.userreactions};
            const post = this.allPosts.find(p => p.id === postId);
            if (post) {
                post.reactions = reactions;
            }
            await renderReactionsBarInto(postEl, reactions, {
                emojis: this.emojis,
                canreact: this.data.canreact,
            });
        } catch (e) {
            Notification.exception(e);
        }
    }
}
