# Security

This project began as a free PHP template. Everything below was in it, and is
now fixed.

## Fixed in the rebuild

### A hardcoded administrator backdoor

`initialize.php` defined an account the users table knew nothing about:

```php
$dev_data = array('id'=>'-1', 'username'=>'dev_oretnom',
                  'password'=>'5da283a2d990e8d8512cf967df5bc0d0', ...);
define('dev_data', $dev_data);
```

A fixed username with a fixed MD5 password, sitting in a public repository, in a
constant named after the template's author. Nothing in the project referenced it,
so it was dead weight as well as a liability. It is gone.

### Database credentials in the source

The MySQL host, username, password and database name were `define()`d in
`initialize.php` and committed. They now come from the environment, with `.env`
for local work and `.env` in `.gitignore`.

**The old values are still in this repository's history.** They were
`root` with an empty password against `localhost`, which is a default rather
than a real secret, but if that pair was ever used on a reachable machine,
change it.

### SQL injection

Sixty queries built their SQL by interpolating values straight into the string.
Several took them from the URL with no filtering at all:

```php
$qry = $conn->query("SELECT * from `booking_list` where id = '{$_GET['id']}' ");
```

The worst was the staff account save, which took both the column names and the
values from `$_POST`:

```php
foreach($_POST as $k => $v){
    if(in_array($k, array('firstname', ...))){
        $data .= " {$k} = '{$v}' ";
    }
}
$this->conn->query("INSERT INTO users set {$data}");
```

Every one of the sixty now binds its parameters. Column names can only come from
a fixed whitelist via `DBConnection::buildSet()`, and `WHERE id IN (...)` lists
go through `DBConnection::inList()`, which drops anything non-numeric.

### Passwords stored as MD5

Unsalted MD5, which is reversible for any common password with a lookup table -
one of the seeded demo hashes was simply `password`. Hashing is now bcrypt at
cost 12 through `Login::hash()`. Existing MD5 rows still authenticate and are
upgraded in place on the first successful sign-in, so the change was not
breaking.

### The failed-login response leaked the query

```php
return json_encode(array('status'=>'incorrect',
    'last_qry'=>"SELECT * from users where username = '$username' ..."));
```

A failed sign-in returned the SQL statement, with the submitted username
interpolated, plus the raw mysqli error, to whoever was trying. Failures now say
only that the details did not match, and take the same path whether or not the
account exists.

### Reading other people's bookings

`view_booking.php` loaded whatever id was in the URL and checked nothing.
Changing the number showed any customer's pickup address, destination and phone
number. All three roles now go through an ownership check: administrators see
everything, customers see their own bookings, drivers see the ones assigned to
their cab.

### Local file inclusion in the page routers

All three front controllers took the page name straight from the query string
and used it as an include path:

```php
$page = isset($_GET['page']) ? $_GET['page'] : 'home';
...
include $page.'.php';
```

The only check was `file_exists($page . ".php")`, which a relative path
satisfies happily, so `?page=../../anything` included any `.php` file on the
server. The name is now run through `safe_page_name()`, which accepts only
letters, digits, underscores, hyphens and single slashes between segments, and
falls back to `home` for anything else.

### Sessions

The session id was not regenerated on sign-in, so an id fixed beforehand stayed
valid afterwards. It is regenerated now, and the session cookie is set
`HttpOnly` with `SameSite=Lax`, and `Secure` once the site is served over HTTPS.

### Errors

`ini_set('display_error', 1)` - note the typo, which meant it never did anything
- sat in `Login.php`. Error display is now driven by `APP_DEBUG` and defaults to
off, with a connection failure logged rather than printed.

## Still outstanding


- **No CSRF protection.** Any state-changing request carrying a valid session
  cookie is accepted, so a page on another site can act as a logged-in user.
  This is the most significant thing left.
- **Output escaping is inconsistent.** `e()` exists and the rebuilt pages use
  it, but older templates still echo database values into HTML raw, so a stored
  value containing markup would render.
- **`extract($_POST)` remains** in `Master.php` and `Users.php`, now with
  `EXTR_SKIP` so it cannot overwrite an existing variable. The SQL beneath it is
  parameterised, which is what made it dangerous before.
- **No rate limiting** on the sign-in endpoints.
- **Uploads** are checked with `mime_content_type` and re-encoded through GD,
  which is reasonable, but they are written under the web root.

## Reporting something

This is a university project rather than a maintained product. If you find
something, please open an issue describing it and how to reproduce it.
