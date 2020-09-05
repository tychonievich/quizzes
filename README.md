This repository contains an online quizzing tool, designed for and tested on UVA's implementation of shibboleth.

## Installation

This directory needs to be in a place apache can find it.

You'll need to give apache write-access to the `log/` and `cache/` subdirectories.
This can be done by, e.g. `chmod 777 log/ cache/` or `chown www-data log/ cache/`, etc.

### Dependencies

You'll need a copy of KaTeX and of Michelf's Markdown.

#### Automatic

Run `bash .htgetDeps.sh` -- this should download all needed dependencies for you.

#### Manual KaTeX

You'll need to install [KaTeX](https://github.com/KaTeX/KaTeX) if you want math rendering; you'll also need servable copies of the katex css and fonts files.

Two modes of KaTeX handling are supported;
client-side (the default) and server-side.

##### Server-side installation

Set `"server-side KaTeX":true` in `course.json` and run

1. `npm install --global katex`
2. `mkdir katex`
3. `cp /usr/local/lib/node_modules/katex/dist/katex.min.css katex/`
4. `cp -r /usr/local/lib/node_modeuls/katex/dist/fonts katex/`

(note: `/usr/local/lib/node_modules/` might be `/usr/lib/node_modules/` instead depending on how `npm` is installed).

##### Client-side installation

Set `"server-side KaTeX":false` in `course.json` and download and extract [the latest release of KaTeX](https://github.com/KaTeX/KaTeX/releases/latest).
After extraction, there should be at least the following in your main quizzes directory:

```
└── katex
    ├── fonts
    │   ... many files here
    ├── katex.min.css
    └── katex.min.js
```

#### Manual Markdown

You'll need a copy of [Markdown.php](https://github.com/michelf/php-markdown/tree/lib/Michelf) as well.

5. `wget "https://raw.githubusercontent.com/michelf/php-markdown/lib/Michelf/Markdown.php" -P Michelf`
6. `wget "https://raw.githubusercontent.com/michelf/php-markdown/lib/Michelf/MarkdownExtra.php" -P Michelf`
7. `wget "https://raw.githubusercontent.com/michelf/php-markdown/lib/Michelf/MarkdownInterface.php" -P Michelf`

### Customize

The files provided assume apache authenticates users via Shibboleth or another automated authentication system, placing verified user IDs in `$_SERVER[PHP_AUTH_USER]` before running any script.
If you have a different authentication system, you'll need to modify `authenticate.php` (and possibly `.htaccess`) accordingly.

You'll need to customize the course information in `course.json`

- `"homepage"` will be added as a link to the top and bottom of each page
- `"quizname"` will be used to identify quizzes to users
- `"staff"` is a list of login IDs who have pre-open viewing and grade adjusting powers
- `"time_mult"` is a mapping of user IDs and multipliers to add to their quiz time limits, for students who get extra time on tests
- `"server-side KaTeX"` should be set to `false` unless you have a compelling reason not to want client-side math rendering
- `"detailed-partial"`, if `true` will show partial credit as vulgar fractions like ⅜; without, all partial is shown just as ½

## Creating Quizzes

To create a quiz, create a file `questions/`*tasknumber*`.md`.
This file opens with a header, then a description, then a set of questions.
The header consists of lines of the form `key: value`; blank lines are not allowed in the header.

### Example

```
open: 2020-01-24 12:00
due: 2020-01-27 08:30
title: Demo Quiz
hours: 1
comments: true

This is a demonstration of this **quizzing tool**!

Cool, huh?

Question
What is $\sum_{i=2}{3} i$?

key: 5

Question box (2 points)
Write a brief biography of Dennis Richie

key: Dennis Richie invented the C programming language. This alone makes him one of the most important people in history.
key: /(Dennis|Richie).*([Hh](e|im)|Dennis|Richie)/s

Multiquestion
We have two kinds of multiple-choice questions.

Subquestion mmc
What would you like on your sandwich?

a. meat
*a. cheese
*h. peanut butter
*a. banana
X. mustard

ex: as we discussed in class, there is only one good sandwich (and mustard doesn't matter)

Subquestion
Where would you like me to deliver your sandwich?

h. your home
ex. I don't like making deliveries
a. my home
ex. I'm very private and won't tell you where I live
w.8 the classroom
ex. Can do, but technically eating in the classroom is against the rules
*a. the cafeteria
*a. my office

Question img
On a piece of paper, write by hand the following for-loop ten times

    ans = 0
    for i in range(10):
        ans += i

Scan or take a picture of your paper and upload it here.
```

### Quiz Header

Meaningful headers lines are

|key         |default             |notes                                |
|:-----------|:-------------------|:------------------------------------|
|`title`     |Quiz *tasknumber*   |Shows in index view, not in quiz view|
|`seconds`   |= 60 × `minutes`    |time limit; 0 means unlimited. At most one of `seconds`, `minutes`, and `hours` may be given|
|`minutes`   |= 60 × `hours`      |(see `seconds`)                      |
|`hours`     |`0`                 |(see `seconds`)                      |
|`open`      |`2999-12-31 12:00`  |time (per server clock) when students may first view the quiz|
|`due`       |`2999-12-31 12:00`  |time (per server clock) when students may no longer view the quiz and quiz key and grade becomes available|
|`comments`  |`true`              |may students add comments to their answers?|
|`keyless`   |`false`             |prevents autograding, hides key, lets students take time-limited quizzes late|
|`order`     |`shuffle`           |if no key shown, how should multiple-choice options be ordered? Values are `shuffle`, `sort`, and `pin`|
|`qorder`    |= `order`           |if no key shown, how should questions be ordered? Values are `shuffle`, `sort`, and `pin`|
|`hide`      |= `false`           |if `true`, will only be shown on index for (a) staff and (b) students who have already viewed it|
|`draft`     |= `false`           |if `true`, will only be shown on index and only be viewable by staff|

The header lines must be terminated by a blank line.

A deprecated header line is `directions`, an old one-line way to create a quiz description. New quizzes should use the description tool instead.

### Quiz Description

You can include information that applies to the whole quiz between the header and the first question.

You can edit question or option text after students take a quiz, but **must not** reorder, insert, or remove questions or options; under the hood answers are stored as "student's selection for the 3rd option of the 5th question in the file" so such reorderings mess up student answers.

### Stand-alone questions

A question consists of a question header, question description, and either a key or a set of options.

#### Question Headers

The question header must begin with the exact text `Question`.
The line may also optionally contain the following, in any order:

- `pin` to override the header `order` and keep options in the order listed in the question file
- `mmc` to make the question multiple-multiple-choice: that is, multiple-select instead of single-select
- `box` to make the question have a multi-line text area answer
- `img` to make the question have an image upload instead of web form answer
- `(3 points)` or the like to change the weight of this question; `(0 points)` drops it from grading entirely. Default is 1 point.

If the question is not `mmc`, `box`, or `img` then it is either single select (if there is at least one option provided) or short answer (otherwise).

#### Question Description

Text between the question header and the first option, explanation, or key is shown as the question text.

An explanation of the question as a whole is created by beginning a line `ex: `; the remainder of that line, and all subsequent lines until an option or key, is only rendered when displaying the key.

#### Question Options

An option is introduced as one of the following:

- `a. option text` -- an option that should not be selected
- `*a. option text` -- an option that should be selected
- `h. option text` -- an option worth half credit
- `x. option text` -- an option to drop and hide from display
- `X. option text` -- an option to drop but still display
- `w.875 option text` -- an option worth 0.875 instead of 1

For a select-one question, fractional weights create partial credit.
For a multiple-select question, each option is treated like a true-false question and fractional weights downplay the importance of a particular question.
All questions (h, x, X, w) may have a preceding `*`; this is meaningful only for multiple-select questions.

If a student selects a dropped question on a single-select, it is treated as full-credit. That may change in a future release.

Any option can be followed by an option-specific explanation as `ex. explanation text` (note: `ex.` for options, `ex:` for questions); like question explanations, these are only shown when displaying the key.

Any option may be multi-line; lines after an option and before the next option, explanation, key, or question are treated as part of the option.

#### Free-response keys

A key is provided on a line beginning `key: `; if the next character is a `/` it is treated as a perl-compatible regular expression, otherwise it's directly matched to what the user types. Either way, exactly one space must follow the colon.

Regular expressions are not anchored by default, so `key: /the/` will give full credit to answer `withered`, but `/^the$/` will only give credit to the exact answer `the`. Also note that many flags can follow the terminating `/`; a few of the most useful are

- `/th*e/i` is case-insensitive, matching `ThHhHhE` for example
- `/th.e/s` matches both `"thee"` and `"th\ne"` -- without it, `.` is any non-newline character
- `/☺+/u` matches in UTF-8 instead of by byte, meaning ☺ is treated as a character instead of a three-byte sequence `\xe2\x98\xba`. Important if you plan to apply quantifiers to non-ASCII characters.

You can have several keys; only the first will be displayed in a key view but if any match, the student gets points. Keys can also be partial-credit as e.g. `key(0.25): /..*/` to give ¼ credit to any non-blank answer. If multiple keys match a student answer, the highest-scoring one will be used.

#### A note on images

Currently, image-type questions have no enforced time limit.
We found that students often struggled to get images created and uploaded and removing such a limit reduced complaints and special cases dramatically.
A future version may make this customizable via `course.json`.

### Grouped questions

A question labeled "Subquestion" will be grouped with the previous question or subquestion; it is otherwise treated exactly like a Question.

A special label "Multiquestion" accepts no in-line options.
Text following Multiquestion and preceding the next Subquestion will appear as descriptive text for a question group.


    Question
    stand-alone
    
    Question
    because followed by Subquestion, joined with next
    
    Subquestion
    joined with previous
    
    Subquestion
    also with the two above
    
    Question
    stand-alone
    
    Multiquestion
    this text will not show up as it describes an empty question group
    
    Question
    stand-alone
    
    Multiquestion
    The following will all be displayed with `(see above)` pointing to this text
    
    Subquestion
    part 1
    
    Subquestion
    part 2
    
    Question
    stand-alone

## Grading Quizzes

Most questions will be autograded, but human input is sometimes needed

- free-response answers that did not receive full credit are logged for review
    - adjustments are performed by assigning key items for other answers
- if comments are enabled (`comments: true` header) then commented answers that did not receive full credit will be logged for human review
    - adjustments are performed on a per-student basis
- image uploads are not autograded
- questions with a `rubric` are not autograded

### Rubrics

This is an incomplete extension under active development:

- [x] parse rubrics in quiz definition files
- [x] graded rubric displayed to students
    - assumes `{"slug":"318c8248","feedback":"","rubric":[1,0,0.5]}`{.json} format (just those three keys, just list-of-numb grade)
- [ ] grading interface shows rubric
- [ ] multiple graders on same question supported without collisions

- [ ] display grade ranges before grading
- [ ] display "not yet graded"

If a question has a line `rubric:`, following material will be taken as part of the rubric as follows:

- If there is text following `rubric:`, it will be made a rubric item

- [Question Options](#question-options) syntax will create rubric items;
	note that `*` is ignored.
	As with options, `x` discards an item and `X` shows it but gives is 0 weight (same as w.0).
	Extra-weight options are not currently supported; decrease the weight of other items instead.
	
	Weights will be normalized and do not need to add up to point weight, but must not sum to zero.

If there is a rubric, then the question is ungraded until a human grades it.
Post-autograde pre-human grade, the quiz grade shows as a grade range, as e.g. "3--7 / 8 (37--88%)"
and the question shows "(not yet graded)"


## Staff special options

A few options are only available by staff editing URLs directly

- If you are staff and add `asuser=mst3k` as a query parameter (e.g. `quiz.php?quid=03&asuser=mst3k`), then you'll see the page `mst3k` sees, including their answers, etc.
- If you are staff and add `showkey` as a query parameter (e.g., `quiz.php?quid=03&showkey`) then you'll be able to see the key even before the quiz closes.

## Known bugs

At least some commented wrong answers are not showing up in the grading view. Most are; I only have one example that is not. Still trying to debug it.

We had some vague bug reports that the time limit on images might be "partially enforced". Not sure what that means

## Future extensions

- [x] Make server- vs client-side rendering of KaTeX configurable
- [ ] Permit gap between close and key release for extra time students on fixed-time quizzes
- [ ] Add student-view grading as well as question-view grading
- [ ] Add "drop this question for this student" grading option
- [ ] Add "this question is in topic X" for specification grading and ABET evaluation
- [ ] Add "this quiz can replace that other one" for specification grading
- [ ] Add randomized question pools
