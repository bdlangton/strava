@group stats
Feature: Stats

  @group user
  Scenario: Stats logged in
    Given user is logged in
    When call "GET" "/big"
    Then response status should be "200"

  @group visitor
  Scenario: Stats logged out
    Given user is logged out
    When call "GET" "/big"
    Then response status should be "302"
