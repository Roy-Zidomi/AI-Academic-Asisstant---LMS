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

    ready(addDashboardGreeting);
})();
