Feature: View Charts

  As a logged in user
  In order to be able to view charts
  I should be able to view charts

  Scenario: User is logged in
    When call "GET" "/"
    Then response status should be "200"
