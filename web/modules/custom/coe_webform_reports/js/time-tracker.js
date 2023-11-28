(function ($) {
  Drupal.behaviors.coe_webform_reports_time_tracker = {
    attach: function (context, settings) {
      // Record the start time when the form is loaded.
      var startTime = new Date().getTime();

      // Attach a submit handler to the form.
      $('.webform-button--submit', context).once('timeTrack').hover(function () {
        // Calculate the time difference between start time and submission time.
        var endTime = new Date().getTime();
        var timeDifference = Math.floor((endTime - startTime) / 1000); // Convert milliseconds to seconds

        // Set the calculated time difference in a hidden form field.
        $('input[name="completion_time"]').val(timeDifference);
      });
    }
  };
})(jQuery);
