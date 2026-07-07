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

            // Publish all approved questions button. Use delegation because the review
            // panel can be refreshed after loading a draft.
            $('.local_aiacademic_quiz_container').off('click.local_aiacademic_publish', '#btn-publish-all');
            $('.local_aiacademic_quiz_container').on('click.local_aiacademic_publish', '#btn-publish-all', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.publishApprovedQuestions();
            });
            $(document).off('click.local_aiacademic_publish_document', '#btn-publish-all');
            $(document).on('click.local_aiacademic_publish_document', '#btn-publish-all', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.publishApprovedQuestions();
            });

            self.updatePublishButtonState();

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
                Notification.alert('Quiz Generation Failed', self.getErrorMessage(ex));
            });
        },

        loadDraft: function(bid) {
            this.activeDraftId = bid;
            $('#btn-publish-all').attr('data-active-draft-id', bid);
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
                Notification.alert('Unable to Load Draft', self.getErrorMessage(ex));
            });
        },

        renderQuestions: function() {
            var accordionEl = $('#questions-accordion');
            accordionEl.empty();

            $('#review-meta-subtitle').text('Batch ID: #' + this.activeDraftId + ' | ' + this.questions.length + ' Questions');
            $('#btn-publish-all').attr('data-active-draft-id', this.activeDraftId);

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
            this.updatePublishButtonState();
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
                self.updatePublishButtonState();
            }).fail(Notification.exception);
        },

        publishApprovedQuestions: function() {
            var draftId = this.getActiveDraftId();
            var approvedCount = this.getApprovedQuestionCount();

            if (!draftId) {
                Notification.alert('Publish Error', 'Please select a generated quiz draft before publishing.');
                return;
            }

            if (approvedCount === 0) {
                Notification.alert('Publish Error', 'Please approve at least one question before publishing.');
                return;
            }

            var settings = this.collectPublishSettings();
            if (!settings) {
                return;
            }

            $('#btn-publish-all')
                .prop('disabled', true)
                .html('<i class="fa fa-spinner fa-spin"></i> <span class="quiz_publish_button_text">Importing...</span>');

            var self = this;
            var promises = Ajax.call([{
                methodname: 'local_aiacademic_quiz_publish',
                args: {
                    genquiz_id: draftId,
                    target_type: 'sectionquiz',
                    quiz_name: settings.quiz_name,
                    timeopen: settings.timeopen,
                    timeclose: settings.timeclose,
                    timelimit: settings.timelimit,
                    attempts: settings.attempts,
                    grade: settings.grade,
                    questionsperpage: settings.questionsperpage,
                    shuffleanswers: settings.shuffleanswers,
                    visible: settings.visible
                }
            }]);

            promises[0].done(function(data) {
                if (data.quiz_url) {
                    window.location.href = data.quiz_url;
                    return;
                }

                Notification.alert('Success', 'Created quiz with ' + data.questions_published + ' questions successfully.');
                window.location.reload();
            }).fail(function(ex) {
                $('#btn-publish-all')
                    .prop('disabled', false)
                    .html('<i class="fa fa-cloud-upload"></i> <span class="quiz_publish_button_text">Import to Source Topic</span>');
                Notification.alert('Publish Failed', self.getErrorMessage(ex));
            });
        },

        collectPublishSettings: function() {
            var timeopen = this.datetimeToTimestamp($('#quiz-open-time').val());
            var timeclose = this.datetimeToTimestamp($('#quiz-close-time').val());
            var timelimitMinutes = parseInt($('#quiz-time-limit').val(), 10);
            var attempts = parseInt($('#quiz-attempts').val(), 10);
            var grade = parseFloat($('#quiz-grade').val());
            var questionsperpage = parseInt($('#quiz-questions-per-page').val(), 10);

            if (timeopen > 0 && timeclose > 0 && timeclose <= timeopen) {
                Notification.alert('Invalid Deadline', 'Close time must be later than open time.');
                return null;
            }

            return {
                quiz_name: ($('#quiz-name').val() || '').trim(),
                timeopen: timeopen,
                timeclose: timeclose,
                timelimit: isNaN(timelimitMinutes) ? 0 : Math.max(0, timelimitMinutes) * 60,
                attempts: isNaN(attempts) ? 1 : Math.max(0, attempts),
                grade: isNaN(grade) ? 10 : Math.max(1, grade),
                questionsperpage: isNaN(questionsperpage) ? 1 : Math.max(1, questionsperpage),
                shuffleanswers: $('#quiz-shuffle').is(':checked') ? 1 : 0,
                visible: $('#quiz-visible').is(':checked') ? 1 : 0
            };
        },

        datetimeToTimestamp: function(value) {
            if (!value) {
                return 0;
            }

            var timestamp = Date.parse(value);
            if (isNaN(timestamp)) {
                return 0;
            }

            return Math.floor(timestamp / 1000);
        },

        updatePublishButtonState: function() {
            var approvedCount = this.getApprovedQuestionCount();

            var button = $('#btn-publish-all');
            button.prop('disabled', false);
            button.attr('aria-disabled', approvedCount === 0 ? 'true' : 'false');

            if (approvedCount > 0) {
                button.attr('title', 'Import ' + approvedCount + ' approved question(s) to the source topic');
            } else {
                button.attr('title', 'Approve at least one question before importing to the source topic');
            }
        },

        getActiveDraftId: function() {
            var draftId = parseInt(this.activeDraftId, 10);
            if (draftId > 0) {
                return draftId;
            }

            draftId = parseInt($('#btn-publish-all').attr('data-active-draft-id'), 10);
            if (draftId > 0) {
                this.activeDraftId = draftId;
                return draftId;
            }

            var meta = $('#review-meta-subtitle').text() || '';
            var match = meta.match(/Batch ID:\s*#(\d+)/i);
            if (match) {
                draftId = parseInt(match[1], 10);
                if (draftId > 0) {
                    this.activeDraftId = draftId;
                    $('#btn-publish-all').attr('data-active-draft-id', draftId);
                    return draftId;
                }
            }

            return 0;
        },

        getApprovedQuestionCount: function() {
            if (this.questions && this.questions.length) {
                return this.questions.filter(function(q) {
                    var status = parseInt(q.review_status, 10);
                    return status === 1 || status === 3;
                }).length;
            }

            return $('.review-status-badge').filter(function() {
                var label = ($(this).text() || '').trim().toLowerCase();
                return label === 'approved' || label === 'edited';
            }).length;
        },

        getErrorMessage: function(ex) {
            if (!ex) {
                return 'The AI service did not return a usable error message. Please check the AI service logs.';
            }

            if (typeof ex === 'string') {
                return ex;
            }

            if (ex.message) {
                return ex.debuginfo ? ex.message + ' ' + ex.debuginfo : ex.message;
            }

            if (ex.error) {
                if (typeof ex.error === 'string') {
                    return ex.error;
                }
                if (ex.error.message) {
                    return ex.error.message;
                }
            }

            if (ex.debuginfo) {
                return ex.debuginfo;
            }

            if (ex.exception && ex.exception.message) {
                return ex.exception.message;
            }

            if (ex.errorcode) {
                return 'Request failed with error code: ' + ex.errorcode;
            }

            return 'Quiz generation failed. The PDF may be too large, the AI model may be unavailable, or Ollama timed out while generating questions.';
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
