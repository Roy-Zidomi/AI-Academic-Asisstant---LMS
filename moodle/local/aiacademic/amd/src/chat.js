/**
 * Moodle local_aiacademic — Chat Assistant JS Module
 * AMD RequireJS module managing AJAX calls and dynamic UI updates
 */
define(['jquery', 'core/ajax', 'core/notification', 'core/templates'], function($, Ajax, Notification, Templates) {
    'use strict';

    var Chat = {
        sesskey: '',
        courseid: 0,
        activeSessionId: 0,
        sessions: [],

        /**
         * Initialize the chat page events.
         *
         * @param {Object} params Params passed from PHP
         */
        init: function(params) {
            this.sesskey = params.sesskey;
            this.courseid = params.courseid;

            this.registerEvents();
            this.loadSessions();
        },

        registerEvents: function() {
            var self = this;

            // Form submit: send message
            $('#chat-form').on('submit', function(e) {
                e.preventDefault();
                self.sendMessage();
            });

            // New session button
            $('#btn-new-session').on('click', function() {
                self.startNewSession();
            });

            // Delete session button
            $('#btn-delete-session').on('click', function() {
                self.deleteActiveSession();
            });

            // Session item click handler (delegated)
            $('#session-list').on('click', '.session-item', function(e) {
                e.preventDefault();
                var sid = $(this).data('id');
                self.loadSession(sid);
            });
        },

        loadSessions: function() {
            var self = this;
            var promises = Ajax.call([{
                methodname: 'local_aiacademic_chat_get_sessions',
                args: {
                    courseid: self.courseid,
                    page: 1,
                    perpage: 20
                }
            }]);

            promises[0].done(function(data) {
                self.sessions = data;
                self.renderSessions();
            }).fail(Notification.exception);
        },

        renderSessions: function() {
            var listEl = $('#session-list');
            listEl.empty();

            if (this.sessions.length === 0) {
                listEl.append('<div class="text-muted text-center py-3">No history conversations</div>');
                return;
            }

            var self = this;
            this.sessions.forEach(function(s) {
                var activeClass = (s.id === self.activeSessionId) ? 'active' : '';
                var title = s.title || 'New Conversation';
                var courseBadge = s.coursename ? ' [' + s.coursename + ']' : '';
                listEl.append(
                    '<a href="#" class="session-item ' + activeClass + '" data-id="' + s.id + '" title="' + title + '">' +
                        '<i class="fa fa-comment-o mr-2"></i> ' + title + courseBadge +
                    '</a>'
                );
            });
        },

        startNewSession: function() {
            this.activeSessionId = 0;
            $('.session-item').removeClass('active');
            $('#active-session-title').text('New Conversation');
            $('#btn-delete-session').hide();
            
            // Empty messages grid with welcome message
            var messagesEl = $('#chat-messages');
            messagesEl.html(
                '<div class="message assistant welcome_msg">' +
                    '<div class="avatar">🤖</div>' +
                    '<div class="msg_content">' +
                        '<p>Welcome! I am your AI Academic Assistant. Ask me any questions related to your courses.</p>' +
                    '</div>' +
                '</div>'
            );
        },

        loadSession: function(sid) {
            this.activeSessionId = sid;
            $('.session-item').removeClass('active');
            $('.session-item[data-id="' + sid + '"]').addClass('active');

            // Find session details
            var session = this.sessions.find(function(s) { return s.id === sid; });
            if (session) {
                $('#active-session-title').text(session.title);
            }
            $('#btn-delete-session').show();

            var messagesEl = $('#chat-messages');
            messagesEl.html('<div class="text-center py-4"><i class="fa fa-spinner fa-spin fa-2x"></i> Loading messages...</div>');

            var self = this;
            var promises = Ajax.call([{
                methodname: 'local_aiacademic_chat_get_history',
                args: {
                    sessionid: sid,
                    page: 1,
                    perpage: 50
                }
            }]);

            promises[0].done(function(data) {
                messagesEl.empty();
                if (data.length === 0) {
                    messagesEl.html('<div class="text-center py-4 text-muted">No messages found.</div>');
                    return;
                }

                data.forEach(function(msg) {
                    var roleClass = (msg.role === 'user') ? 'user' : 'assistant';
                    var avatar = (msg.role === 'user') ? '🧑‍🎓' : '🤖';
                    // Render message
                    messagesEl.append(
                        '<div class="message ' + roleClass + '">' +
                            '<div class="avatar">' + avatar + '</div>' +
                            '<div class="msg_content">' +
                                '<p>' + self.escapeHtml(msg.content).replace(/\n/g, '<br>') + '</p>' +
                            '</div>' +
                        '</div>'
                    );
                });
                self.scrollToBottom();
            }).fail(Notification.exception);
        },

        sendMessage: function() {
            var inputEl = $('#chat-input');
            var message = inputEl.val().trim();
            if (message.length === 0) {
                return;
            }

            inputEl.val('').prop('disabled', true);
            $('#btn-send').prop('disabled', true);

            var messagesEl = $('#chat-messages');
            
            // 1. Append User Message
            messagesEl.append(
                '<div class="message user">' +
                    '<div class="avatar">🧑‍🎓</div>' +
                    '<div class="msg_content">' +
                        '<p>' + this.escapeHtml(message).replace(/\n/g, '<br>') + '</p>' +
                    '</div>' +
                '</div>'
            );
            this.scrollToBottom();

            // 2. Show Typing Indicator
            var typingEl = $('#chat-typing-indicator');
            typingEl.show();

            var self = this;
            var promises = Ajax.call([{
                methodname: 'local_aiacademic_chat_send_message',
                args: {
                    sessionid: self.activeSessionId,
                    courseid: self.courseid,
                    message: message
                }
            }]);

            promises[0].done(function(data) {
                typingEl.hide();
                inputEl.prop('disabled', false).focus();
                $('#btn-send').prop('disabled', false);

                // 3. Append Assistant Message
                messagesEl.append(
                    '<div class="message assistant">' +
                        '<div class="avatar">🤖</div>' +
                        '<div class="msg_content">' +
                            '<p>' + self.escapeHtml(data.response).replace(/\n/g, '<br>') + '</p>' +
                        '</div>' +
                    '</div>'
                );
                self.scrollToBottom();

                // 4. Update session id and refresh sidebar list if new session
                if (self.activeSessionId === 0) {
                    self.activeSessionId = data.session_id;
                    self.loadSessions();
                    $('#btn-delete-session').show();
                }
            }).fail(function(ex) {
                typingEl.hide();
                inputEl.prop('disabled', false).focus();
                $('#btn-send').prop('disabled', false);
                Notification.exception(ex);
            });
        },

        deleteActiveSession: function() {
            if (this.activeSessionId === 0) {
                return;
            }

            var confirmMsg = 'Are you sure you want to delete this conversation history?';
            if (!confirm(confirmMsg)) {
                return;
            }

            var self = this;
            var promises = Ajax.call([{
                methodname: 'local_aiacademic_chat_delete_session',
                args: {
                    sessionid: self.activeSessionId
                }
            }]);

            promises[0].done(function() {
                self.activeSessionId = 0;
                self.loadSessions();
                self.startNewSession();
            }).fail(Notification.exception);
        },

        scrollToBottom: function() {
            var el = document.getElementById('chat-messages');
            if (el) {
                el.scrollTop = el.scrollHeight;
            }
        },

        escapeHtml: function(text) {
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    };

    return Chat;
});
