@group activities
Feature: Activities

  @group user
  Scenario: Activities logged in
    Given user is logged in
    When call "GET" "/activities"
    Then response status should be "200"

  @group visitor
  Scenario: Activities logged out
    Given user is logged out
    When call "GET" "/activities"
    Then response status should be "302"
