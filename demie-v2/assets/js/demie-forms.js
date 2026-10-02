/* Demie Photography — AJAX enquiry form binding. */
(function ($) {
    'use strict';

    $(document).on('submit', 'form.demie-contact-form', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $feedback = $form.find('.demie-form-feedback');
        var $button = $form.find('button[type="submit"]');

        $feedback.prop('hidden', true).text('');

        $.post(demieCtx.ajaxUrl, {
            action: 'demie_contact',
            nonce: demieCtx.nonce,
            name: $form.find('[name="name"]').val(),
            email: $form.find('[name="email"]').val(),
            phone: $form.find('[name="phone"]').val(),
            subject: $form.find('[name="subject"]').val(),
            message: $form.find('[name="message"]').val()
        })
            .done(function (res) {
                var message = (res && res.data && res.data.message) ? res.data.message : '';
                $feedback.text(message).prop('hidden', false).removeClass('demie-form-error').addClass('demie-form-success');
                $form.trigger('reset');
            })
            .fail(function (xhr) {
                var res = xhr.responseJSON || {};
                var message = (res.data && (res.data.message || (res.data.errors && Object.values(res.data.errors)[0]))) ? (res.data.message || Object.values(res.data.errors)[0]) : 'Sorry, something went wrong. Please try again.';
                $feedback.text(message).prop('hidden', false).removeClass('demie-form-success').addClass('demie-form-error');
            })
            .always(function () {
                $button.prop('disabled', false);
            });
    });

    // Basic feedback styling (brand.css provides overrides if needed).
    $(document).ready(function () {
        if (!$('#demie-forms-css').length) {
            $('head').append(
                '<style id="demie-forms-css">' +
                '.demie-form-feedback{margin-top:16px;padding:12px 18px;border-radius:6px;font-size:14px;}' +
                '.demie-form-success{background:rgba(37,211,102,.12);border:1px solid rgba(37,211,102,.45);}' +
                '.demie-form-error{background:rgba(220,53,69,.12);border:1px solid rgba(220,53,69,.45);}' +
                '</style>'
            );
        }
    });
})(jQuery);
