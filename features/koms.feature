@group koms
Feature: KOMs

  @group user
  Scenario: KOMs logged in
    Given user is logged in
    When call "GET" "/records"
    Then response status should be "200"

  @group visitor
  Scenario: KOMs logged out
    Given user is logged out
    When call "GET" "/records"
    Then response status should be "302"
