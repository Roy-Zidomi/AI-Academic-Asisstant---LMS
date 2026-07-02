/**
 * Moodle local_aiacademic — Quiz Generator JS Module
 * AMD RequireJS module managing quiz drafts review accordion and publish calls
 */
define(['jquery', 'core/ajax', 'core/notification', 'core/templates'], function($, Ajax, Notification, Templates) {
    'use strict';

    var QuizGen = {
        sesskey: '',
        courseid: 0,
        activeDraftId: 0,
        questions: [],

        /**
         * Initialize the quiz generator page events.
         *
         * @param {Object} params Params passed from PHP
         */
        init: function(params) {
            this.sesskey = params.sesskey;
            this.courseid = params.courseid;

            this.registerEvents();
        },

        registerEvents: function() {
            var self = this;

            // Submit generator settings form
            $('#quiz-gen-form').on('submit', function(e) {
                e.preventDefault();
                self.generateDraft();
            });

            // History draft list click handler
            $('.draft-item').on('click', function(e) {
                e.preventDefault();
                var bid = $(this).data('id');
                self.loadDraft(bid);
            });

            // Publish all approved questions button
            $('#btn-publish-all').on('click', function() {
                self.publishApprovedQuestions();
            });

            // Individual question review actions (approve / reject / edit)
            $('#questions-accordion').on('click', '.btn-review-action', function(e) {
                e.stopPropagation();
                var qid = $(this).data('id');
                var action = $(this).data('action');
                self.reviewQuestion(qid, action);
            });
        },

        generateDraft: function() {
            var cmid = $('#material-select').val();
            if (!cmid) {
                return;
            }

            var num = $('#num-questions').val();
            var difficulty = $('#difficulty-select').val();

            // Collect selected types
            var types = [];
            $('.qtype-checkbox:checked').each(function() {
                types.push($(this).val());
            });

            if (types.length === 0) {
                Notification.alert('Required Option', 'Please select at least one question type.');
                return;
            }

            // Hide main panels, show loading spinner state
            $('#quiz-empty-state').hide();
            $('#quiz-active-review').hide();
            $('#quiz-loading-state').show();
            $('#btn-generate-quiz').prop('disabled', true);

            var self = this;
            var promises = Ajax.call([{
                methodname: 'local_aiacademic_quiz_generate',
                args: {
                    courseid: self.courseid,
                    cmid: cmid,
                    question_types: types.join(','),
                    num_questions: num,
                    difficulty: difficulty
                }
            }]);

            promises[0].done(function(data) {
                $('#quiz-loading-state').hide();
                $('#btn-generate-quiz').prop('disabled', false);

                self.activeDraftId = data.genquiz_id;
                self.questions = data.questions;

                self.renderQuestions();
                // Refresh draft history dynamically by reloading page or appending (simple reload page)
                window.location.reload();
            }).fail(function(ex) {
                $('#quiz-loading-state').hide();
                $('#quiz-empty-state').show();
                $('#btn-generate-quiz').prop('disabled', false);
                Notification.exception(ex);
            });
        },

        loadDraft: function(bid) {
            this.activeDraftId = bid;
            $('.draft-item').removeClass('active');
            $('.draft-item[data-id="' + bid + '"]').addClass('active');

            $('#quiz-empty-state').hide();
            $('#quiz-active-review').hide();
            $('#quiz-loading-state').show();

            var self = this;
            var promises = Ajax.call([{
                methodname: 'local_aiacademic_quiz_get',
                args: {
                    genquiz_id: bid
                }
            }]);

            promises[0].done(function(data) {
                $('#quiz-loading-state').hide();
                self.questions = data.questions;
                self.renderQuestions();
            }).fail(function(ex) {
                $('#quiz-loading-state').hide();
                $('#quiz-empty-state').show();
                Notification.exception(ex);
            });
        },

        renderQuestions: function() {
            var accordionEl = $('#questions-accordion');
            accordionEl.empty();

            $('#review-meta-subtitle').text('Batch ID: #' + this.activeDraftId + ' | ' + this.questions.length + ' Questions');

            var self = this;
            this.questions.forEach(function(q, index) {
                var collapseId = 'collapse-' + q.id;
                var headingId = 'heading-' + q.id;
                var badgeColor = 'badge-secondary';
                var statusText = 'Pending';

                if (q.review_status === 1) {
                    badgeColor = 'badge-success';
                    statusText = 'Approved';
                } else if (q.review_status === 2) {
                    badgeColor = 'badge-danger';
                    statusText = 'Rejected';
                } else if (q.review_status === 3) {
                    badgeColor = 'badge-warning';
                    statusText = 'Edited';
                }

                var optionsHtml = '';
                if (q.type === 'multichoice' && q.options) {
                    optionsHtml = '<div class="options_container mt-3">';
                    Object.keys(q.options).forEach(function(key) {
                        var correctClass = (key === q.correct_answer) ? 'correct' : '';
                        optionsHtml += 
                            '<div class="option_row ' + correctClass + '">' +
                                '<strong>' + key + ':</strong> ' + self.escapeHtml(q.options[key]) +
                            '</div>';
                    });
                    optionsHtml += '</div>';
                } else if (q.type === 'truefalse') {
                    var correctTrue = (q.correct_answer === 'true') ? 'correct' : '';
                    var correctFalse = (q.correct_answer === 'false') ? 'correct' : '';
                    optionsHtml = 
                        '<div class="options_container mt-3">' +
                            '<div class="option_row ' + correctTrue + '">True</div>' +
                            '<div class="option_row ' + correctFalse + '">False</div>' +
                        '</div>';
                }

                var reviewButtons = 
                    '<div class="mt-3 border-top pt-2 d-flex justify-content-end gap-2">' +
                        '<button class="btn btn-sm btn-success btn-review-action mr-2" data-id="' + q.id + '" data-action="approve"><i class="fa fa-check"></i> Approve</button>' +
                        '<button class="btn btn-sm btn-danger btn-review-action" data-id="' + q.id + '" data-action="reject"><i class="fa fa-times"></i> Reject</button>' +
                    '</div>';

                accordionEl.append(
                    '<div class="card question_review_card" id="q-card-' + q.id + '">' +
                        '<div class="question_review_header d-flex justify-content-between align-items-center" id="' + headingId + '" data-toggle="collapse" data-target="#' + collapseId + '" aria-expanded="false" aria-controls="' + collapseId + '">' +
                            '<span class="text-truncate font-weight-bold" style="max-width: 80%;">' + (index + 1) + '. ' + self.escapeHtml(q.question) + '</span>' +
                            '<div>' +
                                '<span class="badge badge-info mr-2">' + q.type + '</span>' +
                                '<span class="badge ' + badgeColor + ' review-status-badge">' + statusText + '</span>' +
                            '</div>' +
                        '</div>' +
                        '<div id="' + collapseId + '" class="collapse" aria-labelledby="' + headingId + '" data-parent="#questions-accordion">' +
                            '<div class="question_review_body">' +
                                '<div class="question_text_container">' + self.escapeHtml(q.question) + '</div>' +
                                optionsHtml +
                                '<div class="explanation_container mt-3 text-muted" style="font-size:0.9rem;">' +
                                    '<strong>Explanation:</strong> ' + self.escapeHtml(q.explanation) +
                                '</div>' +
                                reviewButtons +
                            '</div>' +
                        '</div>' +
                    '</div>'
                );
            });

            $('#quiz-active-review').show();
        },

        reviewQuestion: function(qid, action) {
            var self = this;
            var promises = Ajax.call([{
                methodname: 'local_aiacademic_quiz_review_question',
                args: {
                    questionid: qid,
                    action: action
                }
            }]);

            promises[0].done(function(data) {
                var card = $('#q-card-' + qid);
                var badge = card.find('.review-status-badge');
                
                badge.removeClass('badge-secondary badge-success badge-danger badge-warning');
                
                if (data.review_status === 1) {
                    badge.addClass('badge-success').text('Approved');
                } else if (data.review_status === 2) {
                    badge.addClass('badge-danger').text('Rejected');
                }

                // Update internal array
                var idx = self.questions.findIndex(function(item) { return item.id === qid; });
                if (idx !== -1) {
                    self.questions[idx].review_status = data.review_status;
                }
            }).fail(Notification.exception);
        },

        publishApprovedQuestions: function() {
            var approvedCount = this.questions.filter(function(q) {
                return q.review_status === 1 || q.review_status === 3;
            }).length;

            if (approvedCount === 0) {
                Notification.alert('Publish Error', 'Please approve at least one question before publishing.');
                return;
            }

            var confirmMsg = 'You are going to import ' + approvedCount + ' approved questions directly to the Moodle Course Question Bank. Proceed?';
            if (!confirm(confirmMsg)) {
                return;
            }

            $('#btn-publish-all').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Publishing...');

            var self = this;
            var promises = Ajax.call([{
                methodname: 'local_aiacademic_quiz_publish',
                args: {
                    genquiz_id: self.activeDraftId,
                    target_type: 'questionbank'
                }
            }]);

            promises[0].done(function(data) {
                Notification.alert('Success', 'Published ' + data.questions_published + ' questions successfully!');
                window.location.reload();
            }).fail(function(ex) {
                $('#btn-publish-all').prop('disabled', false).html('<i class="fa fa-cloud-upload"></i> Publish to Question Bank');
                Notification.exception(ex);
            });
        },

        escapeHtml: function(text) {
            if (!text) {
                return '';
            }
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    };

    return QuizGen;
});
