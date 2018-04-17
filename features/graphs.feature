@group graphs
Feature: Graphs

  @group user
  Scenario: Graphs logged in
    Given user is logged in
    When call "GET" "/data"
    Then response status should be "200"

  @group visitor
  Scenario: Graphs logged out
    Given user is logged out
    When call "GET" "/data"
    Then response status should be "302"
