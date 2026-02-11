@local @local_ascend_rewards
Feature: Ascend Rewards smoke checks
  In order to ensure the plugin page works in browser flows
  As an administrator
  I need to be able to open the Ascend Rewards page.

  Scenario: Admin can open the Ascend Rewards page
    Given I log in as "admin"
    When I visit "/local/ascend_rewards/index.php"
    Then I should see "Ascend Rewards"

  Scenario: Admin can open the Ascend Rewards admin dashboard
    Given I log in as "admin"
    When I visit "/local/ascend_rewards/admin_dashboard.php"
    Then I should see "Ascend Rewards Admin Dashboard"

  Scenario: Admin can open the badge audit trail
    Given I log in as "admin"
    When I visit "/local/ascend_rewards/admin_audit.php"
    Then I should see "Badge Audit Trail"
