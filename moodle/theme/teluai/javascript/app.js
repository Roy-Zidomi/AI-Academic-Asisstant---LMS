(function() {
    'use strict';

    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
            return;
        }

        callback();
    }

    function cleanName(value) {
        if (!value) {
            return '';
        }

        return value
            .replace(/^\s*(logged\s+in\s+as|user\s+picture\s+of|picture\s+of|avatar\s+of)\s*/i, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function textFrom(selector) {
        var node = document.querySelector(selector);
        if (!node) {
            return '';
        }

        return cleanName(node.getAttribute('data-userfullname') ||
            node.getAttribute('title') ||
            node.getAttribute('alt') ||
            node.textContent);
    }

    function currentUserName() {
        var selectors = [
            '[data-userfullname]',
            '.usermenu .usertext',
            '.userbutton .usertext',
            '.usermenu img.userpicture',
            '.userbutton img.userpicture',
            '.usermenu .userinitials',
            '.userbutton .userinitials'
        ];

        for (var i = 0; i < selectors.length; i++) {
            var value = textFrom(selectors[i]);
            if (value) {
                return value;
            }
        }

        return 'User';
    }

    function addDashboardGreeting() {
        if (!document.body.classList.contains('pagelayout-mydashboard')) {
            return;
        }

        var header = document.querySelector('#page-header');
        if (!header || header.querySelector('.telu-dashboard-greeting')) {
            return;
        }

        var greeting = document.createElement('div');
        greeting.className = 'telu-dashboard-greeting';
        greeting.textContent = 'HAI ' + currentUserName();
        header.insertBefore(greeting, header.firstChild);
    }

    function aiToolFromLink(link) {
        var href = link.href || link.getAttribute('href') || '';

        if (href.indexOf('/local/aiacademic/summarizer.php') !== -1) {
            return {
                label: 'AI Summarize',
                href: href,
                key: 'summarize'
            };
        }

        if (href.indexOf('/local/aiacademic/chat.php') !== -1) {
            return {
                label: 'AI Chat Assistant',
                href: href,
                key: 'assistant'
            };
        }

        if (href.indexOf('/local/aiacademic/quiz_generator.php') !== -1) {
            return {
                label: 'AI Quiz Generator',
                href: href,
                key: 'quiz'
            };
        }

        return null;
    }

    function createAiToolsDropdown(items) {
        var wrapper = document.createElement('li');
        var toggle = document.createElement('button');
        var menu = document.createElement('div');
        var id = 'telu-ai-tools-menu';

        wrapper.className = 'nav-item dropdown telu-ai-tools-menu';

        toggle.className = 'nav-link dropdown-toggle';
        toggle.type = 'button';
        toggle.id = id;
        toggle.setAttribute('data-toggle', 'dropdown');
        toggle.setAttribute('aria-haspopup', 'true');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.textContent = 'AI Tools';

        menu.className = 'dropdown-menu telu-ai-tools-dropdown';
        menu.setAttribute('aria-labelledby', id);

        items.forEach(function(item) {
            var entry = document.createElement('a');
            entry.className = 'dropdown-item';
            entry.href = item.href;
            entry.textContent = item.label;
            menu.appendChild(entry);
        });

        toggle.addEventListener('click', function(event) {
            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.dropdown) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            var open = !menu.classList.contains('show');
            menu.classList.toggle('show', open);
            wrapper.classList.toggle('show', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        document.addEventListener('click', function(event) {
            if (wrapper.contains(event.target)) {
                return;
            }

            menu.classList.remove('show');
            wrapper.classList.remove('show');
            toggle.setAttribute('aria-expanded', 'false');
        });

        wrapper.appendChild(toggle);
        wrapper.appendChild(menu);
        return wrapper;
    }

    function groupAiCourseNavigation() {
        var nav = document.querySelector('.secondary-navigation .moremenu .nav-tabs') ||
            document.querySelector('.secondary-navigation .nav-tabs') ||
            document.querySelector('.secondary-navigation .nav');

        if (!nav || nav.querySelector('.telu-ai-tools-menu')) {
            return;
        }

        var links = Array.prototype.slice.call(document.querySelectorAll([
            '.secondary-navigation a[href*="/local/aiacademic/summarizer.php"]',
            '.secondary-navigation a[href*="/local/aiacademic/chat.php"]',
            '.secondary-navigation a[href*="/local/aiacademic/quiz_generator.php"]'
        ].join(',')));

        var seen = {};
        var items = [];
        var sourceNodes = [];

        links.forEach(function(link) {
            var item = aiToolFromLink(link);
            if (!item || seen[item.key]) {
                return;
            }

            seen[item.key] = true;
            items.push(item);

            var source = link.closest ? (link.closest('.nav-item') || link) : link;
            sourceNodes.push(source);
        });

        if (items.length < 2 || !sourceNodes.length) {
            return;
        }

        nav.insertBefore(createAiToolsDropdown(items), sourceNodes[0]);
        sourceNodes.forEach(function(source) {
            source.classList.add('telu-ai-source-hidden');
        });
    }

    function initLandingNavigation() {
        var nav = document.querySelector('.telu-landing-nav');
        var toggle = document.querySelector('.telu-landing-nav__toggle');
        var links = document.querySelector('.telu-landing-nav__links');

        if (!nav || !toggle || !links) {
            return;
        }

        toggle.addEventListener('click', function() {
            var isOpen = nav.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        links.addEventListener('click', function(event) {
            if (event.target && event.target.tagName === 'A') {
                nav.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    ready(function() {
        addDashboardGreeting();
        groupAiCourseNavigation();
        initLandingNavigation();
    });
})();
