$(document).ready(function() {
  $('select, input').not('.no-uniform').uniform();

  // Hide flash messages after five seconds.
  setTimeout(function() {
    $(".messages").slideUp()
  }, 5000);
});
