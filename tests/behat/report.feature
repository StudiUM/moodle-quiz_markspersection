@mod @mod_quiz @quiz @quiz_markspersection
Feature: Basic use of the Marks per section report
  In order to easily get an overview of quiz attempts grouped by sections
  As a teacher
  I need to use the Marks per section report

  @javascript
  Scenario: Using the Marks per section report with one section
    Given the following "users" exist:
      | username | firstname | lastname | email                | idnumber |
      | teacher1 | T1        | Teacher1 | teacher1@example.com | T1000    |
      | student1 | S1        | Student1 | student1@example.com | S1000    |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "activities" exist:
      | activity   | name   | intro              | course | idnumber |
      | quiz       | Quiz 1 | Quiz 1 description | C1     | quiz1    |
    And the following "questions" exist:
      | questioncategory | qtype       | name | questiontext    |
      | Test questions   | truefalse   | TF1  | This is question 01 |
      | Test questions   | truefalse   | TF2  | This is question 02 |
    And quiz "Quiz 1" contains the following questions:
      | question | page | maxmark |
      | TF1      | 1    | 1.5     |
      | TF2      | 1    | 2.0     |
    And user "student1" has attempted "Quiz 1" with responses:
      | slot | response |
      |   1  | True     |
      |   2  | False    |
    # Basic check of the Marks per section report
    When I am on the "Quiz 1" "quiz activity" page logged in as teacher1
    And I navigate to "Results > Marks per section" in current page administration
    Then I should see "The quiz must contain at least two sections to display this report."

  @javascript
  Scenario: Using the Marks per section report with at least two sections
    Given the following "users" exist:
      | username | firstname | lastname | email                | idnumber |
      | teacher1 | T1        | Teacher1 | teacher1@example.com | T1000    |
      | student1 | S1        | Student1 | student1@example.com | S1000    |
      | student2 | S2        | Student2 | student2@example.com | S2000    |
      | student3 | S3        | Student3 | student3@example.com | S3000    |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "activities" exist:
      | activity   | name   | intro              | course | idnumber |
      | quiz       | Quiz 1 | Quiz 1 description | C1     | quiz1    |
    And the following "questions" exist:
      | questioncategory | qtype       | name | questiontext              | template |
      | Test questions   | truefalse   | TF1  | This is question 01       |          |
      | Test questions   | truefalse   | TF2  | This is question 02       |          |
      | Test questions   | truefalse   | TF3  | This is question 03       |          |
      | Test questions   | truefalse   | TF4  | This is question 04       |          |
      | Test questions   | truefalse   | TF5  | This is question 05       |          |
      | Test questions   | essay       | E1   | This is an essay question | plain    |
    And quiz "Quiz 1" contains the following questions:
      | question | page | maxmark |
      | TF1      | 1    | 1.5     |
      | TF2      | 2    | 2.0     |
      | TF3      | 2    | 1.0     |
      | TF4      | 3    | 1.25    |
      | TF5      | 3    | 2.5     |
      | E1       | 4    | 3       |
    And quiz "Quiz 1" contains the following sections:
      | heading   | firstslot | shuffle |
      | Section 1 | 1         | 0       |
      | Section 2 | 2         | 0       |
      | Section 3 | 4         | 0       |
      | Section 4 | 6         | 0       |
    And user "student1" has attempted "Quiz 1" with responses:
      | slot | response                |
      |   1  | True                    |
      |   2  | False                   |
      |   3  | False                   |
      |   4  | True                    |
      |   5  | True                    |
    And user "student2" has attempted "Quiz 1" with responses:
      | slot | response |
      |   1  | True     |
      |   2  | True     |

    # Student 3 fills the essay, which is impossible with "has attempted [...] with responses" so we log in.
    And I am on the "Quiz 1" "mod_quiz > View" page logged in as "student3"
    And I press "Attempt quiz now"
    And I click on "True" "radio"
    And I click on "Next page" "button"
    And I click on "Next page" "button"
    And I click on "Next page" "button"
    And I set the field with xpath "//textarea[contains(@class,'qtype_essay_plain')]" to "My essay."
    And I follow "Finish attempt ..."
    And I press "Submit all and finish"
    And I click on "Submit all and finish" "button" in the "Confirmation" "dialogue"
    And I log out

    # Basic check of the Marks per section report
    When I am on the "Quiz 1" "quiz activity" page logged in as teacher1
    And I navigate to "Results > Marks per section" in current page administration
    # Check section 1 column
    Then "S1 Student1Review attempt" row "Section 1/1.50Sort by Section 1/1.50 Ascending" column of "attempts" table should contain "1.50"
    And "S2 Student2Review attempt" row "Section 1/1.50Sort by Section 1/1.50 Ascending" column of "attempts" table should contain "1.50"
    And "S3 Student3Review attempt" row "Section 1/1.50Sort by Section 1/1.50 Ascending" column of "attempts" table should contain "1.50"
    # Check section 2 column
    And "S1 Student1Review attempt" row "Section 2/3.00Sort by Section 2/3.00 Ascending" column of "attempts" table should contain "0.00"
    And "S2 Student2Review attempt" row "Section 2/3.00Sort by Section 2/3.00 Ascending" column of "attempts" table should contain "2.00"
    And "S3 Student3Review attempt" row "Section 2/3.00Sort by Section 2/3.00 Ascending" column of "attempts" table should contain "0.00"
    # Check section 3 column
    And "S1 Student1Review attempt" row "Section 3/3.75Sort by Section 3/3.75 Ascending" column of "attempts" table should contain "3.75"
    And "S2 Student2Review attempt" row "Section 3/3.75Sort by Section 3/3.75 Ascending" column of "attempts" table should contain "0.00"
    And "S3 Student3Review attempt" row "Section 3/3.75Sort by Section 3/3.75 Ascending" column of "attempts" table should contain "0.00"
    # Check section 4 column
    And "S1 Student1Review attempt" row "Section 4/3.00Sort by Section 4/3.00 Ascending" column of "attempts" table should contain "Not yet graded"
    And "S2 Student2Review attempt" row "Section 4/3.00Sort by Section 4/3.00 Ascending" column of "attempts" table should contain "Not yet graded"
    And "S3 Student3Review attempt" row "Section 4/3.00Sort by Section 4/3.00 Ascending" column of "attempts" table should contain "Not yet graded"
    # Check average for sections
    And "Overall average" row "Section 1/1.50Sort by Section 1/1.50 Ascending" column of "attempts" table should contain "1.50 (3)"
    And "Overall average" row "Section 2/3.00Sort by Section 2/3.00 Ascending" column of "attempts" table should contain "0.67 (3)"
    And "Overall average" row "Section 3/3.75Sort by Section 3/3.75 Ascending" column of "attempts" table should contain "1.25 (3)"
    # Can't check easily if the column is empty, but if it does not have a . (as in 1.00) or a parenthesis (as in (1)) then it is most probably empty.
    And "Overall average" row "Section 4/3Sort by Section 4/3 Ascending" column of "attempts" table should not contain "."
    And "Overall average" row "Section 4/3Sort by Section 4/3 Ascending" column of "attempts" table should not contain "("
    # Check average with pagination
    And I set the field "Page size" to "1"
    And I press "Show report"
    And I should see "S1 Student1Review attempt"
    And I should not see "S2 Student2Review attempt"
    And I should not see "S3 Student3Review attempt"
    And "Overall average" row "Section 1/1.50Sort by Section 1/1.50 Ascending" column of "attempts" table should contain "1.50 (3)"
    And "Overall average" row "Section 2/3.00Sort by Section 2/3.00 Ascending" column of "attempts" table should contain "0.67 (3)"
    And "Overall average" row "Section 3/3.75Sort by Section 3/3.75 Ascending" column of "attempts" table should contain "1.25 (3)"
    And I set the field "Page size" to "30"
    And I press "Show report"
    # Sort sections
    # Section 1 column Ascending should not change any sort (the same value)
    And I click on "Section 1/1.50Sort by Section 1/1.50 Ascending" "link"
    And "S1 Student1Review attempt" "table_row" should appear before "S2 Student2Review attempt" "table_row"
    And "S2 Student2Review attempt" "table_row" should appear before "S3 Student3Review attempt" "table_row"
    # Section 1 column Descending should not change any sort (the same value)
    And I click on "Section 1/1.50Sort by Section 1/1.50 Ascending" "link"
    And "S1 Student1Review attempt" "table_row" should appear before "S2 Student2Review attempt" "table_row"
    And "S2 Student2Review attempt" "table_row" should appear before "S3 Student3Review attempt" "table_row"
    # Section 2 column Ascending
    And I click on "Section 2/3.00Sort by Section 2/3.00 Ascending" "link"
    And "S2 Student2Review attempt" "table_row" should appear before "S1 Student1Review attempt" "table_row"
    And "S1 Student1Review attempt" "table_row" should appear before "S3 Student3Review attempt" "table_row"
    # Section 2 column Descending
    And I click on "Section 2/3.00Sort by Section 2/3.00 Descending" "link"
    And "S3 Student3Review attempt" "table_row" should appear before "S1 Student1Review attempt" "table_row"
    And "S1 Student1Review attempt" "table_row" should appear before "S2 Student2Review attempt" "table_row"
    # Section 3 column Ascending
    And I click on "Section 3/3.75Sort by Section 3/3.75 Ascending" "link"
    And "S3 Student3Review attempt" "table_row" should appear before "S2 Student2Review attempt" "table_row"
    And "S2 Student2Review attempt" "table_row" should appear before "S1 Student1Review attempt" "table_row"
    # Section 3 column Descending
    And I click on "Section 3/3.75Sort by Section 3/3.75 Ascending" "link"
    And "S1 Student1Review attempt" "table_row" should appear before "S3 Student3Review attempt" "table_row"
    And "S3 Student3Review attempt" "table_row" should appear before "S2 Student2Review attempt" "table_row"
