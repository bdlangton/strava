@group home
Feature: Home

  @group user
  Scenario: Homepage logged in
    Given user is logged in
    When call "GET" "/"
    Then response status should be "200"

  @group visitor
  Scenario: Homepage logged out
    Given user is logged out
    When call "GET" "/"
    Then response status should be "200"
