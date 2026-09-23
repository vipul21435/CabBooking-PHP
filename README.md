# Cab Booking System

A cab booking and fleet management application in PHP and MySQL, built for the
DBMS course at IIIT Delhi under Prof. Vikram Goyal.

Customers book a cab and track the ride; drivers see the jobs assigned to their
vehicle; administrators manage the fleet, the categories, the bookings and the
staff accounts.

[![CI](https://github.com/vipul21435/CabBooking-PHP/actions/workflows/ci.yml/badge.svg)](https://github.com/vipul21435/CabBooking-PHP/actions/workflows/ci.yml)

> **This repository was rebuilt.** The project started from a free PHP template
> that shipped a hardcoded administrator backdoor, database credentials in the
> source, MD5 passwords and SQL built by string concatenation throughout. All of
> that is fixed - [SECURITY.md](SECURITY.md) lists what was wrong and what was
> done about it.

---

## Run it

```bash
git clone https://github.com/vipul21435/CabBooking-PHP.git
cd CabBooking-PHP
cp .env.example .env
docker compose up
```

Then open <http://localhost:8000>. The schema and demo data load automatically
on first boot.

### Demo logins

| Role | Sign in at | Username | Password |
|---|---|---|---|
| Administrator | `/admin` | `admin` | `admin123` |
| Staff | `/admin` | `martha` | `staff123` |
| Customer | `/` | `christine@mail.com` | `client123` |
| Driver | `/driver` | `202202-00001` | `driver123` |

These are local demo values, set by
[`database/02-demo-accounts.sql`](database/02-demo-accounts.sql). Change them
before this goes anywhere real.

### Without Docker

You need PHP 8.0 or newer with the `mysqli` and `gd` extensions, and MySQL 5.7+.

```bash
mysql -u root -p -e "CREATE DATABASE cbsphp"
mysql -u root -p cbsphp < database/01-schema.sql
mysql -u root -p cbsphp < database/02-demo-accounts.sql

cp .env.example .env     # point DB_* at your server
php -S localhost:8000
```

PHP 8.0 is the floor because the code uses `str_starts_with`, `str_contains` and
array destructuring in list assignments.

## Configuration

Everything comes from the environment, with `.env` read for local work. Real
environment variables always win, so a deployment can set them without a file.
`.env` is git-ignored; [`.env.example`](.env.example) documents every value.

| Variable | Default | Notes |
|---|---|---|
| `APP_BASE_URL` | `http://localhost:8000` | Trailing slash added if missing |
| `APP_DEBUG` | `false` | Shows PHP errors. Never true in production |
| `APP_TIMEZONE` | `Asia/Kolkata` | The template shipped `Asia/Manila` |
| `DB_HOST` | `DB_PORT` | `127.0.0.1` | `3306` | |
| `DB_NAME` | `DB_USER` | `DB_PASSWORD` | `cbsphp` | `cbs` | - | |

## How it is laid out

```
+-- index.php, home.php, booking.php ...   customer-facing pages
+-- admin/        fleet, categories, bookings, reports, staff accounts
+-- driver/       the jobs assigned to one cab
+-- classes/
|   +-- DBConnection.php   the connection and the safe query helpers
|   +-- Login.php          all three sign-in flows, password hashing
|   +-- Users.php          staff and customer accounts
|   +-- Master.php         cabs, categories, bookings
|   +-- SystemSettings.php site settings and the session wrapper
+-- inc/          shared header, footer, navigation, session guard
+-- database/     schema and demo accounts, loaded in name order
+-- docker/       PHP + Apache image
+-- dist/, plugins/   AdminLTE 3 and its dependencies, vendored
+-- uploads/      avatars and cab photographs, written at runtime
```

### Talking to the database

Nothing builds SQL by concatenation any more. `DBConnection` exposes helpers
that bind every value:

```php
$db->fetchOne('SELECT * FROM `booking_list` WHERE `id` = ? AND `client_id` = ?', [$id, $clientId]);
$db->fetchAll('SELECT * FROM `cab_list` WHERE `delete_flag` = 0');
$db->execute('UPDATE `booking_list` SET `status` = ? WHERE `id` = ?', [$status, $id]);
$db->count('SELECT COUNT(*) FROM `users` WHERE `username` = ?', [$username]);
```

For an update whose columns vary with the form, `buildSet()` takes the column
names from a fixed whitelist and everything else travels as a bound parameter:

```php
[$set, $params] = DBConnection::buildSet($_POST, ['firstname', 'lastname', 'username', 'type']);
$params[] = $id;
$db->execute("UPDATE `users` SET {$set} WHERE `id` = ?", $params);
```

`inList()` does the same for `WHERE id IN (...)` where the ids are a stored
comma-separated string.

### Passwords

`Login::hash()` writes bcrypt at cost 12. `Login::verifyStored()` accepts both
bcrypt and the old 32-character MD5 rows, and any account that still has an MD5
hash is upgraded to bcrypt the first time it signs in successfully - so the
change locked nobody out.

## What is vendored

`dist/` and `plugins/` are [AdminLTE 3](https://adminlte.io/) and its
dependencies, committed rather than installed, because the project has no build
step and the pages reference them directly. The AdminLTE SCSS sources and the
`.map` files were removed in the rebuild - around 10 MB that nothing served.

## Known limitations

- No unit tests. There is CI, and it does exercise the application for real:
  every file is linted with `php -l` on PHP 8.0, 8.2 and 8.3, and a smoke job
  brings the stack up with Docker and checks that the home page renders, that
  the seeded administrator signs in, that a wrong password is rejected without
  leaking SQL, that an account still holding a legacy MD5 hash signs in and
  comes back out of the database as bcrypt, that `.env` and the schema dump are
  not reachable over HTTP, and that path traversal in the router is refused.
  That is end-to-end coverage of what the rebuild changed, but it is not a unit
  test suite over the domain logic.
- `classes/Master.php` and `classes/Users.php` still call `extract($_POST)`,
  now with `EXTR_SKIP`. The SQL beneath it is parameterised, so this is untidy
  rather than dangerous, but explicit reads would be better.
- CSRF tokens are not implemented. Any state-changing request from a logged-in
  user's browser is accepted.
- Output escaping is inconsistent: `e()` exists in `config.php` and the pages
  this rebuild touched use it, but the older templates still echo database
  values raw.

## Licence

[MIT](LICENSE). AdminLTE, Bootstrap and the other vendored front-end libraries
keep their own licences.
