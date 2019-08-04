@group segments
Feature: Segments

  @group user
  Scenario: Segments logged in
    Given user is logged in
    When call "GET" "/segments"
    Then response status should be "200"

  @group visitor
  Scenario: Segments logged out
    Given user is logged out
    When call "GET" "/segments"
    Then response status should be "302"
