<?php

/**
 * Seeds one default account so you can log in right after migrating.
 *
 *   email    : admin@example.com
 *   username : admin
 *   password : Admin@12345   (override with SEED_ADMIN_PASSWORD in .env)
 *
 * CHANGE THIS PASSWORD (or roll this migration back) for anything public.
 */
class Seed_admin_user {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        $exists = $this->_lava->db->raw(
            "SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1",
            ['admin@example.com', 'admin']
        )->fetch(PDO::FETCH_ASSOC);

        if ($exists) {
            return;
        }

        $password = getenv('SEED_ADMIN_PASSWORD') ?: 'Admin@12345';

        $this->_lava->db->table('users')->insert([
            'username'  => 'admin',
            'email'     => 'admin@example.com',
            'password'  => password_hash($password, PASSWORD_DEFAULT),
            'role'      => 'admin',
            'is_active' => 1,
        ]);
    }

    public function down()
    {
        $this->_lava->db->table('users')->where('email', 'admin@example.com')->delete();
    }
}
