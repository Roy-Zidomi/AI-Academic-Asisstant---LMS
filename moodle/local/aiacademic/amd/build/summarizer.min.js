/**
 * Moodle local_aiacademic — Summarizer JS Module
 * AMD RequireJS module managing AJAX calls and tab panel content renders
 */
define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {
    'use strict';

    var Summarizer = {
        sesskey: '',
        courseid: 0,
        cmid: 0,

        /**
         * Initialize the summarizer page events.
         *
         * @param {Object} params Params passed from PHP
         */
        init: function(params) {
            this.sesskey = params.sesskey;
            this.courseid = params.courseid;
            this.cmid = params.cmid;

            this.registerEvents();

            // Auto-load summary if course module ID was passed in query param
            if (this.cmid > 0) {
                this.loadSummary(this.cmid, false);
            }
        },

        registerEvents: function() {
            var self = this;

            // Submit generation form
            $('#summarizer-form').on('submit', function(e) {
                e.preventDefault();
                var cmid = $('#material-select').val();
                if (cmid) {
                    self.loadSummary(cmid, false);
                }
            });

            // Force regenerate click handler
            $('#btn-force-summarize').on('click', function() {
                var cmid = $('#material-select').val();
                if (cmid) {
                    self.loadSummary(cmid, true);
                }
            });

            // Material dropdown change handler
            $('#material-select').on('change', function() {
                // Hide results when material selection changes to prompt user to submit
                $('#summarizer-results').hide();
                $('#btn-force-summarize').hide();
            });
        },

        loadSummary: function(cmid, forceRegenerate) {
            this.cmid = cmid;
            $('#material-select').val(cmid);

            // Hide old results, show loading layout
            $('#summarizer-results').hide();
            $('#summarizer-loading').show();
            $('#btn-summarize').prop('disabled', true);
            $('#btn-force-summarize').hide();

            var self = this;
            var promises = Ajax.call([{
                methodname: 'local_aiacademic_summary_generate',
                args: {
                    courseid: self.courseid,
                    cmid: cmid,
                    force_regenerate: forceRegenerate
                }
            }]);

            promises[0].done(function(data) {
                $('#summarizer-loading').hide();
                $('#btn-summarize').prop('disabled', false);
                $('#btn-force-summarize').show();

                self.renderSummary(data);
            }).fail(function(ex) {
                $('#summarizer-loading').hide();
                $('#btn-summarize').prop('disabled', false);
                Notification.exception(ex);
            });
        },

        renderSummary: function(data) {
            // Update material header title
            var filename = $('#material-select option:selected').text();
            $('#summary-material-title').text(filename);

            // Set badge model info
            var modelBadge = 'model: ' + (data.model || 'unknown') + ' | time: ' + (data.generation_time || '0') + 's';
            $('#summary-meta-badge').text(modelBadge);

            // 1. Render Executive Summary
            $('#content-executive').html('<p>' + this.escapeHtml(data.executive_summary).replace(/\n/g, '<br>') + '</p>');

            // 2. Render Key Points
            var kpEl = $('#content-keypoints');
            kpEl.empty();
            if (data.key_points && data.key_points.length > 0) {
                data.key_points.forEach(function(point) {
                    kpEl.append('<li class="list-group-item"><i class="fa fa-chevron-right text-primary mr-2"></i> ' + this.escapeHtml(point) + '</li>');
                }.bind(this));
            } else {
                kpEl.append('<li class="list-group-item text-muted">No key points extracted.</li>');
            }

            // 3. Render Important Concepts
            var cEl = $('#content-concepts');
            cEl.empty();
            if (data.concepts && data.concepts.length > 0) {
                data.concepts.forEach(function(c) {
                    cEl.append(
                        '<div class="col-md-6 mb-3">' +
                            '<div class="concept_card">' +
                                '<h5>' + this.escapeHtml(c.term) + '</h5>' +
                                '<p class="text-muted mb-0">' + this.escapeHtml(c.definition) + '</p>' +
                            '</div>' +
                        '</div>'
                    );
                }.bind(this));
            } else {
                cEl.append('<div class="col-12 text-muted">No concepts extracted.</div>');
            }

            // 4. Render Glossary
            var gEl = $('#content-glossary');
            gEl.empty();
            if (data.glossary && data.glossary.length > 0) {
                data.glossary.forEach(function(g) {
                    gEl.append(
                        '<tr>' +
                            '<td><strong>' + this.escapeHtml(g.term) + '</strong></td>' +
                            '<td>' + this.escapeHtml(g.definition) + '</td>' +
                        '</tr>'
                    );
                }.bind(this));
            } else {
                gEl.append('<tr><td colspan="2" class="text-muted text-center">No glossary items found.</td></tr>');
            }

            // 5. Render Study Guide
            $('#content-studyguide').html('<p>' + this.escapeHtml(data.study_guide).replace(/\n/g, '<br>') + '</p>');

            // Reveal results cards container
            $('#summarizer-results').show();
            
            // Switch tabs back to first one (Executive Summary)
            $('#summaryTabs a[href="#executive"]').tab('show');
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

    return Summarizer;
});
