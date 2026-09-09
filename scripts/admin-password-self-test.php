<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/admin_password_helpers.php';
$count=0;
function password_check($condition, $label) { global $count; if (!$condition) throw new RuntimeException($label); $count++; }
$plain = 'Synthetic-password-only';
$hash = password_hash($plain, PASSWORD_BCRYPT, ['cost'=>4]);
password_check(red_admin_password_verify($plain, $hash), 'valid bcrypt login');
password_check(!red_admin_password_verify('wrong', $hash), 'wrong password refused');
password_check(!red_admin_password_verify($hash, $hash), 'stored hash cannot be used as password');
foreach (['0','1','true'] as $flag) {
    putenv('RED_ADMIN_ALLOW_LEGACY_PASSWORDS='.$flag);
    password_check(!red_admin_password_verify($plain, $plain), 'plaintext refused regardless of obsolete flag');
}
putenv('RED_ADMIN_ALLOW_LEGACY_PASSWORDS');
foreach (['', str_repeat('a',32), '$1$obsolete', '$2y$malformed'] as $stored) password_check(!red_admin_password_verify($stored,$stored), 'unknown/malformed values refused');
if (defined('PASSWORD_ARGON2ID')) {
    $argon=password_hash($plain,PASSWORD_ARGON2ID);
    password_check(red_admin_password_verify($plain,$argon), 'valid Argon2id login');
}
password_check(red_admin_password_migration_class($hash)==='hashed', 'migration preserves hashes');
password_check(red_admin_password_migration_class($plain)==='legacy', 'explicit migration candidate');
foreach (['',str_repeat('a',64),'$unknown',str_repeat('x',73),"zero\0byte"] as $value) password_check(red_admin_password_migration_class($value)==='resetRequired','ambiguous or unsafe conversion refused');
$source=file_get_contents(dirname(__DIR__).'/bin/login.php');
password_check(!str_contains($source,'RED_ADMIN_ALLOW_LEGACY_PASSWORDS') && !str_contains($source,'hash_equals($storedPassword'),'runtime has no plaintext switch or comparison');
echo "Hash-only password contract passed: $count assertions.\n";
