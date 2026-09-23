<?php
/**
 * Authentication for the three kinds of account: staff, client and driver.
 *
 * Changes from the version this replaces:
 *  - Passwords are checked with password_verify() against a bcrypt hash.
 *    Existing MD5 rows still work and are upgraded in place on first successful
 *    sign-in, so nobody is locked out by the change.
 *  - The failure branch used to return `last_qry` - the SQL statement, with the
 *    submitted username interpolated - and the raw mysqli error, straight to
 *    the browser. Both are gone; failures now say only that the details did not
 *    match, and take the same path whether the account exists or not.
 *  - extract($_POST) is gone. Fields are read explicitly.
 *  - The session id is regenerated on sign-in, so a session fixed before login
 *    cannot be reused afterwards.
 */

require_once __DIR__ . '/../config.php';

class Login extends DBConnection
{
    private $settings;

    public function __construct()
    {
        global $_settings;
        $this->settings = $_settings;
        parent::__construct();
    }

    public function __destruct()
    {
        parent::__destruct();
    }

    public function index()
    {
        echo "<h1>Access Denied</h1> <a href='" . e(BASE_URL) . "'>Go Back.</a>";
    }

    public static function hash(string $password): string
    {
        return Password::hash($password);
    }

    public static function verifyStored(string $password, string $stored): bool
    {
        return Password::verify($password, $stored);
    }

    /**
     * Checks a password and reports whether the stored hash is out of date.
     *
     * @param bool $needsUpgrade set when the row should be rewritten
     */
    private function verify(string $password, string $stored, ?bool &$needsUpgrade = null): bool
    {
        $needsUpgrade = false;
        if (!Password::verify($password, $stored)) {
            return false;
        }
        $needsUpgrade = Password::needsUpgrade($stored);
        return true;
    }

    private function upgradeHash(string $table, string $column, $id, string $password): void
    {
        // The table name is never user input: each caller passes a literal.
        $this->execute("UPDATE `{$table}` SET `password` = ? WHERE `{$column}` = ?", [self::hash($password), $id]);
    }

    /** Copies a row into the session, minus the password. */
    private function startSession(array $row, int $loginType): void
    {
        session_regenerate_id(true);
        foreach ($row as $key => $value) {
            if (!is_numeric($key) && $key !== 'password') {
                $this->settings->set_userdata($key, $value);
            }
        }
        $this->settings->set_userdata('login_type', $loginType);
    }

    /** Staff sign-in. */
    public function login()
    {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            return json_encode(['status' => 'incorrect', 'msg' => 'Enter your username and password.']);
        }

        $user = $this->fetchOne('SELECT * FROM `users` WHERE `username` = ?', [$username]);

        if ($user && $this->verify($password, (string) $user['password'], $needsUpgrade)) {
            if ($needsUpgrade) {
                $this->upgradeHash('users', 'id', $user['id'], $password);
            }
            $this->startSession($user, 1);
            return json_encode(['status' => 'success']);
        }

        // Identical answer whether the account exists or the password is wrong.
        return json_encode(['status' => 'incorrect', 'msg' => 'Incorrect username or password.']);
    }

    public function logout()
    {
        if ($this->settings->sess_des()) {
            redirect('admin/login.php');
        }
    }

    /** Client sign-in. */
    public function login_client()
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            return json_encode(['status' => 'failed', 'msg' => ' Enter your email and password.']);
        }

        $client = $this->fetchOne(
            'SELECT * FROM `client_list` WHERE `email` = ? AND `delete_flag` = 0',
            [$email]
        );

        if (!$client || !$this->verify($password, (string) $client['password'], $needsUpgrade)) {
            return json_encode(['status' => 'failed', 'msg' => ' Incorrect Email or Password.']);
        }

        if ((int) $client['status'] !== 1) {
            return json_encode(['status' => 'failed', 'msg' => ' Your Account has been blocked by the management.']);
        }

        if ($needsUpgrade) {
            $this->upgradeHash('client_list', 'id', $client['id'], $password);
        }
        $this->startSession($client, 2);
        return json_encode(['status' => 'success']);
    }

    public function logout_client()
    {
        if ($this->settings->sess_des()) {
            redirect('?');
        }
    }

    /** Driver sign-in, keyed on the cab registration code. */
    public function login_driver()
    {
        $regCode = trim((string) ($_POST['reg_code'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($regCode === '' || $password === '') {
            return json_encode(['status' => 'failed', 'msg' => ' Enter your code and password.']);
        }

        $driver = $this->fetchOne(
            'SELECT * FROM `cab_list` WHERE `reg_code` = ? AND `delete_flag` = 0',
            [$regCode]
        );

        if (!$driver || !$this->verify($password, (string) $driver['password'], $needsUpgrade)) {
            return json_encode(['status' => 'failed', 'msg' => ' Incorrect Code or Password.']);
        }

        if ((int) $driver['status'] !== 1) {
            return json_encode(['status' => 'failed', 'msg' => ' Your Account has been blocked by the management.']);
        }

        if ($needsUpgrade) {
            $this->upgradeHash('cab_list', 'id', $driver['id'], $password);
        }
        $this->startSession($driver, 3);
        return json_encode(['status' => 'success']);
    }

    public function logout_driver()
    {
        if ($this->settings->sess_des()) {
            redirect('driver');
        }
    }
}

$action = isset($_GET['f']) ? strtolower((string) $_GET['f']) : 'none';
$auth = new Login();

switch ($action) {
    case 'login':
        echo $auth->login();
        break;
    case 'logout':
        echo $auth->logout();
        break;
    case 'login_client':
        echo $auth->login_client();
        break;
    case 'logout_client':
        echo $auth->logout_client();
        break;
    case 'login_driver':
        echo $auth->login_driver();
        break;
    case 'logout_driver':
        echo $auth->logout_driver();
        break;
    default:
        $auth->index();
        break;
}
