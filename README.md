# SQL sanitize extended

A Drupal module that extends
[`drush sql:sanitize`](https://www.drush.org/13.x/commands/sql_sanitize/)
with steps that remove personal data a site's own database holds beyond
what core's plugins cover:

1. Most users
2. Entities and tables from Core and Contrib modules that may hold
   personal data
3. Files nothing uses

A guard checks the final state and fails the run if a user id still
points to a user that does not exist.

It plugs into `sql:sanitize`, so it runs alongside core's plugins and a
site's own sanitize listeners.

## Requirements

- PHP 8.3 or later
- Drupal 11.4 or later
- Drush 13.7 or later

## Installation

```shell
composer require reload/sql_sanitize_extended
drush pm:install sql_sanitize_extended
```

Drush finds listeners only in enabled modules, so the module must be
enabled in the database being sanitized. `drush sql:sanitize` then does
the rest; nothing else is called.

## What a run does

When the module is installed, `drush sql:sanitize` runs these steps:

1. Core's sanitize plugins rewrite mail, passwords and user fields.
2. Delete entities that may contain personal data, such as comments
   and webform submissions.
3. Empty the fields named in `--empty-fields`.
4. Reassign content that refers to users who are not kept to the
   anonymous user.
5. Delete the users who are not kept.
6. Delete files nothing uses any more.
7. Empty tables that may contain personal data, such as logs, sessions
   and caches.
8. The site's own sanitize listeners run.
9. A guard checks that no user id points to a user that no longer
   exists, and fails the run if one does.

The prompt lists what will be emptied and what is kept, without naming
users from the database.

### Supported modules

The entities and tables are grouped by the module that provides them in
`src/Defaults.php`.

An entry applies when its table exists or its
entity type is defined, whether or not the module is installed, which
also catches a table a crashed uninstall left behind.

### User handling

| User | Mail and password rewritten by core | Deleted by the module |
| --- | --- | --- |
| uid 0 | No | No |
| uid 1 | Yes | No |
| Named in `--kept-user-names` | Yes | No |
| With a role in `--ignored-roles` | No | No |
| Everyone else | Yes | Yes |

Core rewrites mail and password only as far as its own options say,
and rewrites every user's text, email and phone fields. Users the
module does not delete are kept, with the references to them.

- uid 1 is not renamed; developers log in with `drush uli`.
- `--kept-user-names` matches names ignoring case.
- Users with a role in `--ignored-roles` keep their mail and password
  hash. Name only roles that hold test accounts, or use
  `--kept-user-names`.

## Options

When the module is installed, `drush sql:sanitize` has these options:

| Option | Meaning |
| --- | --- |
| `--sanitize-extended=no` | Turns off the steps, the guard and the prompt lines. `0` does too. In `drush.yml` write `sanitize-extended: no`; an unquoted `0` or `false` fails with a TypeError, as for core's own `sanitize-*` options |
| `--kept-user-names` | Users kept by name, ignoring case |
| `--allowlist-tables` | Default tables to leave alone |
| `--allowlist-entity-types` | Default entity types to leave alone |
| `--empty-tables` | Tables emptied on top of the defaults |
| `--empty-entity-types` | Content entity types with SQL storage emptied on top of the defaults |
| `--empty-fields` | Configurable fields to empty, `<entity type>.<bundle>.<field>` |

Set them on the command or in `drush.yml` under
`command.sql.sanitize.options`:

```yaml
command:
  sql:
    sanitize:
      options:
        ignored-roles: editor,tester
        kept-user-names: testmedlem
        empty-tables: custom_log
```

A list is a comma-separated string, split the way core splits
`--ignored-roles`, without trimming. A YAML list is not accepted.

A value that matches nothing is a warning and is ignored, since a name
can exist in one environment and not in another. Read the warnings: a
misspelled name in `--kept-user-names` deletes that account.

Invalid options fail the run before the prompt, before anything changes:

- A table or entity type in both an allowlist and an empty option.
- An allowlisted table or entity type that is not a default.
- A malformed entry, a base field, a computed field or a field with
  custom storage in `--empty-fields`.
- `user`, or an entity type that is not a content entity type with SQL
  storage, in `--empty-entity-types`.
- A user reference field that covers only some bundles of a table
  without a bundle column.
- A list that is not a comma-separated string.

With `--sanitize-extended=no` nothing is validated.

### Turning the module off in an environment

The module is on wherever it is enabled, production too, where an
accidental `drush sql:sanitize -y` would delete users. Turn it off
there with an environment variable:

```shell
DRUSH_COMMAND_SQL_SANITIZE_OPTIONS_SANITIZE_EXTENDED=no
```

- Write `no`. Drush reads an environment variable of `0` as unset.
- A `sanitize-extended` value in the project's or the site's
  `drush.yml` or in the site alias overrides the variable, and so does
  the option on the command line.
- Core's own sanitize still runs; only the module is off.

## Development

```shell
composer install
drupal/vendor/bin/phpcs
drupal/vendor/bin/phpstan analyse
drupal/vendor/bin/phpunit --testsuite unit
drupal/vendor/bin/phpunit --testsuite kernel
drupal/vendor/bin/phpunit --testsuite functional
```

The linters run in Docker through Docker Compose, with pinned images:

```shell
composer lint
```

`composer lint:markdown`, `lint:yaml`, `lint:actions` and
`lint:commits` run one linter each. `lint:commits` checks the commits
since `main`; set `COMMITLINT_FROM` to use another base.

Composer installs a development site into the git-ignored `drupal/` and
links the module into it. The tests run on SQLite, in
`drupal/web/sites/simpletest`. The functional tests run `drush` as a
subprocess against the test site, so they take a few seconds each.

Releases are made by semantic-release from conventional commits.

## Limitations

- On SQLite without a table prefix Drupal finds no tables, so none is
  emptied.
- The guard does not see user ids inside serialized data or in paths
  like `/user/N`.
