# Cab Booking System

Cab booking and fleet management in PHP and MySQL. Built for the DBMS course at IIIT Delhi under Prof. Vikram Goyal.

Customers book a cab and follow the ride. Drivers see the jobs assigned to their vehicle. Admins manage the fleet, the categories, the bookings and the staff accounts.

[![CI](https://github.com/vipul21435/CabBooking-PHP/actions/workflows/ci.yml/badge.svg)](https://github.com/vipul21435/CabBooking-PHP/actions/workflows/ci.yml)

> This started from a free PHP template, and the template came with a hardcoded admin backdoor, database credentials in the source, MD5 passwords, and SQL built by string concatenation everywhere. That is all fixed. [SECURITY.md](SECURITY.md) has the list.

## Run it

```bash
git clone https://github.com/vipul21435/CabBooking-PHP.git
cd CabBooking-PHP
cp .env.example .env
docker compose up
```

Open http://localhost:8000. The schema and the demo data load on first boot.

| Role | Sign in at | Username | Password |
|---|---|---|---|
| Administrator | `/admin` | `admin` | `admin123` |
| Staff | `/admin` | `martha` | `staff123` |
| Customer | `/` | `christine@mail.com` | `client123` |
| Driver | `/driver` | `202202-00001` | `driver123` |

Local demo values, set by [`database/02-demo-accounts.sql`](database/02-demo-accounts.sql). Change them before this goes anywhere real.

### Without Docker

PHP 8.0 or newer with `mysqli` and `gd`, and MySQL 5.7 or newer.

```bash
mysql -u root -p -e "CREATE DATABASE cbsphp"
mysql -u root -p cbsphp < database/01-schema.sql
mysql -u root -p cbsphp < database/02-demo-accounts.sql

cp .env.example .env     # point DB_* at your server
php -S localhost:8000
```

PHP 8.0 is the floor because the code uses `str_starts_with`, `str_contains` and array destructuring in list assignments.

## Configuration

Everything comes from the environment, with `.env` read for local work. Real environment variables win, so a deployment can set them without a file. `.env` is git-ignored and [`.env.example`](.env.example) documents every value.

| Variable | Default | Notes |
|---|---|---|
| `APP_BASE_URL` | `http://localhost:8000` | trailing slash added if missing |
| `APP_DEBUG` | `false` | shows PHP errors, never true in production |
| `APP_TIMEZONE` | `Asia/Kolkata` | the template shipped `Asia/Manila` |
| `DB_HOST`, `DB_PORT` | `127.0.0.1`, `3306` | |
| `DB_NAME`, `DB_USER`, `DB_PASSWORD` | `cbsphp`, `cbs` | |

## Layout

```
index.php, home.php, booking.php     customer pages
admin/      fleet, categories, bookings, reports, staff accounts
driver/     the jobs assigned to one cab
classes/    DBConnection, Login, Password, Users, Master, SystemSettings
inc/        shared header, footer, navigation, session guard
database/   schema and demo accounts, loaded in name order
docker/     PHP and Apache image
dist/, plugins/   AdminLTE 3 and its dependencies, vendored
uploads/    avatars and cab photographs, written at runtime
```

## Talking to the database

Nothing builds SQL by concatenation. `DBConnection` binds every value:

```php
$db->fetchOne('SELECT * FROM `booking_list` WHERE `id` = ? AND `client_id` = ?', [$id, $clientId]);
$db->fetchAll('SELECT * FROM `cab_list` WHERE `delete_flag` = 0');
$db->execute('UPDATE `booking_list` SET `status` = ? WHERE `id` = ?', [$status, $id]);
$db->count('SELECT COUNT(*) FROM `users` WHERE `username` = ?', [$username]);
```

When an update's columns vary with the form, `buildSet()` takes the column names from a fixed whitelist and everything else travels bound:

```php
[$set, $params] = DBConnection::buildSet($_POST, ['firstname', 'lastname', 'username', 'type']);
$params[] = $id;
$db->execute("UPDATE `users` SET {$set} WHERE `id` = ?", $params);
```

`inList()` does the same for `WHERE id IN (...)` when the ids come out of a column as a comma separated string.

## Passwords

`Password::hash()` writes bcrypt at cost 12. `Password::verify()` accepts bcrypt and the old 32 character MD5 rows, and any account still holding MD5 is rewritten as bcrypt the first time it signs in. Nobody had to be locked out for the change.

## What is vendored

`dist/` and `plugins/` are [AdminLTE 3](https://adminlte.io/) and its dependencies, committed rather than installed, because the project has no build step and the pages reference them directly. The AdminLTE SCSS sources and the `.map` files went in the rebuild, around 10 MB that nothing served.

## What it does not have

No unit tests. CI does exercise the app for real: `php -l` over every file on PHP 8.0, 8.2 and 8.3, then a smoke job that brings the stack up with Docker and checks that the home page renders, the seeded administrator signs in, a wrong password is rejected without leaking SQL, an account holding a legacy MD5 hash signs in and comes back out of the database as bcrypt, `.env` and the schema dump are not reachable over HTTP, and path traversal in the router is refused. That covers what the rebuild changed. It is not a unit suite over the domain logic.

No CSRF tokens, so any state-changing request carrying a valid session cookie is accepted. That is the biggest thing still open.

`classes/Master.php` and `classes/Users.php` still call `extract($_POST)`, now with `EXTR_SKIP`. The SQL underneath is bound, so it is untidy rather than dangerous, but explicit reads would be better.

Output escaping is uneven. `e()` exists in `config.php` and the pages touched in the rebuild use it, but older templates still echo database values raw.

## Licence

[MIT](LICENSE). AdminLTE, Bootstrap and the other vendored front-end libraries keep their own licences.
