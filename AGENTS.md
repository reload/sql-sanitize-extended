# AGENTS.md

A Drupal module that extends `drush sql:sanitize`. `README.md` holds what
it does and how to run the checks; this file holds what is not obvious.

- Every listener lives in `src/Drush/Listeners/`. Drush finds listeners
  only there, in enabled modules.
- The order of a run is fixed by named priority constants: the steps at
  100, the sites' own listeners at 0, the guard at -1000. Keep them.
- All terminate listeners share `Activation::shouldRun()`, because
  terminate listeners also run after other commands, an abort and a
  failed hook.
- Each listener builds its plan from the options; they share no state.
- Decisions are pure static functions on plain data, unit-tested without
  Drupal. Reading the database sits in the stage's instance methods and
  is covered by kernel tests.
- The defaults live in one map in `src/Defaults.php`, grouped by the
  module that provides them.
- Write the functional test first for a change in how the module plugs
  into Drush. It is the only check that Drush finds the module.
- While the version is 0.x, write a breaking change as `feat`, never
  with `!` or a `BREAKING CHANGE` footer: semantic-release would release
  1.0.0. Do not edit `CHANGELOG.md`; the release writes it.
