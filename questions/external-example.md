title: Example external test
due: 2020-01-01
external: my-quiz.csv
compid: Email
score: Full Score
outof: 100

This "quiz" is a placeholder to show the scores earned in a non-quiz assessment.
This idea is that the staff load a CSV file into the quizzes directory with scores
and identify which columns to look at.

`compid` is the column header for the computing ID, which may be by itself or the prefix of an email address.
If missing, it defaults to the first column.

`score` is the column header for the column with the score for this quis.
If missing, it defaults to the second column.

`outof` is the scale of the scores in the score column.
If missing, it defaults to 1.
