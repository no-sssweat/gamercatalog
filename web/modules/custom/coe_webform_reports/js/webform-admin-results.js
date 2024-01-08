document.addEventListener('DOMContentLoaded', function() {
  // Get all table rows in the document
  var tableRows = document.querySelectorAll('table.views-table tbody tr');

  // Flag to track whether the element exists in all rows
  var allRowsHaveElement = true;

  // Loop through each table row
  tableRows.forEach(function(row) {
    // Check if link exists
    if (row.querySelector('.views-field-google-sheets-view-link a')) {
      row.classList.add('sheets');
      // Find the elements within the current row
      var googleSheetsLink = row.querySelector('.views-field-google-sheets-view-link a');
      var editLink = row.querySelector('.dropbutton .edit a');
      var viewLink = row.querySelector('.dropbutton .view a');

      // Check if both elements exist in the current row
      if (googleSheetsLink && editLink && viewLink) {
        // Copy the href value from googleSheetsLink to editLink and viewLink
        editLink.href = googleSheetsLink.href;
        viewLink.href = googleSheetsLink.href;

        // Add the target="_blank" attribute to editLink and viewLink
        editLink.setAttribute('target', '_blank');
        viewLink.setAttribute('target', '_blank');
      } else {
        // If any row is missing the element, set the flag to false
        allRowsHaveElement = false;
      }
    } else {
      // If any row is missing the element, set the flag to false
      allRowsHaveElement = false;
    }
  });

  // Perform the action only if the element exists in all rows
  if (allRowsHaveElement) {
    // Hide filters
    var formItems = document.querySelectorAll('.views-exposed-form .form--inline > .form-item, .views-exposed-form .form--inline > div');
    formItems.forEach(function(item) {
      item.style.display = 'none';
    });
    // Show form action
    var formActions = document.querySelector('.views-exposed-form .form--inline > .form-actions');
    formActions.style.display = 'block';
  }
});
