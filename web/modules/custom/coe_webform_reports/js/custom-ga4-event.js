(function ($, Drupal) {
  Drupal.behaviors.customGA4Event = {
    attach: function (context, settings) {
      // Use the once() method to ensure this code runs only once
      $(document, context).once('customGA4Event').each(function () {
        // Find the form element in the HTML by its ID
        var formElement = $('.webform-submission-form', context);

        // Extract the form_id attribute
        var formId = formElement ? formElement.attr('id') : 'unknown';
        // alert(formId);

        // Send the custom GA4 event with the form_id parameter
        gtag('event', 'webform_view', {
          'event_name': 'page_view',
          'content_id': formId
        });
      });
    }
  };
})(jQuery, Drupal);
