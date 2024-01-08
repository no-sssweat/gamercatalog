(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.realTimeSecondsCounter = {
    attach: function (context, settings) {
      // Select the completion_time input field.
      var completionTimeField = $('input[name="completion_time"]', context);

      // Check if the field has a value.
      var initialSeconds = parseInt(completionTimeField.val()) || 0;

      // Function to update the field value with the current count.
      function updateField() {
        completionTimeField.val(initialSeconds++);
      }

      // Start counting from the initial value.
      updateField();

      // Set up an interval to update the field every second.
      setInterval(updateField, 1000);
    }
  };
})(jQuery, Drupal, drupalSettings);
