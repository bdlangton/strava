$(document).ready(function() {
  $('select, input').uniform();

  // Hide flash messages after five seconds.
  setTimeout(function() {
    $(".messages").slideUp()
  }, 5000);
});
