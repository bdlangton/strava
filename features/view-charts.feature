@group charts
Feature: View Charts

  @group user
  Scenario: Charts logged in
    Given user is logged in
    When call "GET" "/column"
    Then response status should be "200"

  @group visitor
  Scenario: Charts logged out
    Given user is logged out
    When call "GET" "/column"
    Then response status should be "302"
