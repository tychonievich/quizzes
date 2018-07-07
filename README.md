This is an FYI dump of the ad-hoc quiz tool created for teaching CS 3330 and CS 4810 in Fall 2016.
I intend to document and update it over time, but wanted to document its current state as it is being used by other faculty as well.

This repository includes snapshots of <https://github.com/michelf/php-markdown/blob/lib/Michelf/Markdown.php> and  <http://pear.php.net/pepr/pepr-proposal-show.php?id=198>. I believe I edited `JSON.php` (and maybe `Markdown.php` too?) though I no longer remember how or why.

Quizzes are stored in a custom text format based on that used by Sakai in a subdirectory `questions/` and results are stored in a (very inefficient) set of JSON files in `log/`.

You will definitely need to edit `staff.php` for each course. If you do not use an authentication method that sets  `PHP_AUTH_USER` for you, you may also need to edit `qshowlib.php` which refers to it once.
