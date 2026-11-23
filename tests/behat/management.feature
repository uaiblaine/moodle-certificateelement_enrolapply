@tool @tool_certificate @certificateelement @certificateelement_enrolapply @javascript
Feature: Adding the enrolment application answer element to a certificate template
  In order to print what a learner answered when applying for the course
  As a certificate manager
  I need to add the element to a template and name the question by its short name

  Background:
    Given the following certificate templates exist:
      | name          | numberofpages |
      | Certificate 1 | 1             |

  Scenario: Only a short name is accepted, and an unnamed element is listed by it
    Given I log in as "admin"
    And I change window size to "large"
    And I navigate to "Certificates > Manage certificate templates" in site administration
    And I follow "Certificate 1"
    When I add the element "Enrolment application answer" to page "1" of the "Certificate 1" site certificate template
    Then I should see "Add 'Enrolment application answer' element" in the ".modal.show .modal-header" "css_element"
    And I set the field "Question short name" to "Unit name"
    And I click on "Save" "button" in the ".modal.show .modal-footer" "css_element"
    And I should see "A short name has lower case letters, numbers and underscores only" in the ".modal.show .modal-body" "css_element"
    And I set the field "Question short name" to "unit"
    And I click on "Save" "button" in the ".modal.show .modal-footer" "css_element"
    And I should see "unit" in the "[data-region='elementlist']" "css_element"
    And I click on "Edit 'unit'" "link" in the "[data-region='elementlist']" "css_element"
    And the field "Question short name" matches value "unit"
