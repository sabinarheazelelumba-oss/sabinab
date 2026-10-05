<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * AuthController
 *
 * Token based authentication (JWT access token + refresh token) built on
 * the LavaLust API library.
 *
 *   POST /api/auth/register   create an account (returns tokens)
 *   POST /api/auth/login      login with email/username + password
 *   POST /api/auth/refresh    exchange a refresh token for new tokens
 *   POST /api/auth/logout     revoke the refresh token   (auth required)
 *   GET  /api/auth/me         current user               (auth required)
 */
class AuthController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        header('Content-Type: application/json; charset=utf-8');
        $this->call->library('api');
    }

    // ------------------------------------------------------------------
    // POST /api/auth/register
    // ------------------------------------------------------------------
    public function register()
    {
        $this->api->require_method('POST');
        $this->api->rate_limit('register_' . $this->client_ip(), 10, 60);

        $in       = $this->api->body();
        $username = trim((string) ($in['username'] ?? ''));
        $email    = strtolower(trim((string) ($in['email'] ?? '')));
        $password = (string) ($in['password'] ?? '');

        $errors = [];
        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
            $errors['username'] = 'Username must be 3-50 characters (letters, numbers, . _ -).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            $errors['email'] = 'A valid email address is required.';
        }
        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }
        if ($errors) {
            $this->validation_error($errors);
        }

        $taken = $this->db->raw(
            "SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1",
            [$email, $username]
        )->fetch(PDO::FETCH_ASSOC);

        if ($taken) {
            $this->api->respond_error('Username or email is already registered.', 409);
        }

        $this->db->table('users')->insert([
            'username'  => $username,
            'email'     => $email,
            'password'  => password_hash($password, PASSWORD_DEFAULT),
            'role'      => 'user',
            'is_active' => 1,
        ]);
        $user_id = (int) $this->db->last_id();

        $user   = $this->find_user($user_id);
        $tokens = $this->api->issue_tokens([
            'id'     => $user_id,
            'role'   => $user['role'],
            'scopes' => ['read', 'write', 'delete'],
        ]);

        $this->api->respond([
            'status'  => 'success',
            'message' => 'Account created successfully.',
            'user'    => $user,
            'tokens'  => $tokens,
        ], 201);
    }

    // ------------------------------------------------------------------
    // POST /api/auth/login
    // ------------------------------------------------------------------
    public function login()
    {
        $this->api->require_method('POST');
        $this->api->rate_limit('login_' . $this->client_ip(), 10, 60);

        $in       = $this->api->body();
        $login    = trim((string) ($in['login'] ?? $in['email'] ?? $in['username'] ?? ''));
        $password = (string) ($in['password'] ?? '');

        if ($login === '' || $password === '') {
            $this->validation_error(['login' => 'Email/username and password are required.']);
        }

        $row = $this->db->raw(
            "SELECT id, username, email, password, role, is_active
               FROM users
              WHERE email = ? OR username = ?
              LIMIT 1",
            [strtolower($login), $login]
        )->fetch(PDO::FETCH_ASSOC);

        // Same message for "no such user" and "wrong password" on purpose.
        if (!$row || !password_verify($password, $row['password'])) {
            $this->api->respond_error('Invalid email/username or password.', 401);
        }

        if ((int) $row['is_active'] !== 1) {
            $this->api->respond_error('This account is disabled.', 403);
        }

        $tokens = $this->api->issue_tokens([
            'id'     => (int) $row['id'],
            'role'   => $row['role'],
            'scopes' => ['read', 'write', 'delete'],
        ]);

        $this->api->respond([
            'status'  => 'success',
            'message' => 'Login successful.',
            'user'    => $this->public_user($row),
            'tokens'  => $tokens,
        ]);
    }

    // ------------------------------------------------------------------
    // POST /api/auth/refresh
    // ------------------------------------------------------------------
    public function refresh()
    {
        $this->api->require_method('POST');
        $this->api->rate_limit('refresh_' . $this->client_ip(), 30, 60);

        $in    = $this->api->body();
        $token = (string) ($in['refresh_token'] ?? '');

        if ($token === '') {
            $this->validation_error(['refresh_token' => 'refresh_token is required.']);
        }

        // Validates, rotates and responds with new tokens by itself.
        $this->api->refresh_access_token($token);
    }

    // ------------------------------------------------------------------
    // POST /api/auth/logout   (needs a valid access token)
    // ------------------------------------------------------------------
    public function logout()
    {
        $this->api->require_method('POST');
        $auth  = $this->api->require_jwt();
        $in    = $this->api->body();
        $token = (string) ($in['refresh_token'] ?? '');

        if ($token !== '') {
            $this->api->revoke_refresh_token($token);
        } else {
            $this->db->raw("DELETE FROM refresh_tokens WHERE user_id = ?", [(int) $auth['sub']]);
        }

        $this->api->respond(['status' => 'success', 'message' => 'Logged out successfully.']);
    }

    // ------------------------------------------------------------------
    // GET /api/auth/me   (needs a valid access token)
    // ------------------------------------------------------------------
    public function me()
    {
        $auth = $this->api->require_jwt();
        $user = $this->find_user((int) $auth['sub']);

        if (!$user) {
            $this->api->respond_error('Unauthorized', 401);
        }

        $this->api->respond(['status' => 'success', 'user' => $user]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------
    private function find_user($id)
    {
        $row = $this->db->raw(
            "SELECT id, username, email, role, is_active FROM users WHERE id = ? LIMIT 1",
            [(int) $id]
        )->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->public_user($row) : null;
    }

    private function public_user(array $row)
    {
        return [
            'id'       => (int) $row['id'],
            'username' => $row['username'],
            'email'    => $row['email'],
            'role'     => $row['role'],
        ];
    }

    private function validation_error(array $errors)
    {
        $this->api->respond([
            'error'  => 'Validation failed.',
            'status' => 422,
            'errors' => $errors,
        ], 422);
    }

    /**
     * Client IP, aware of Render's proxy (X-Forwarded-For).
     */
    private function client_ip()
    {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($forwarded !== '') {
            return trim(explode(',', $forwarded)[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
