<?php
/**
 * Unit: ContactCreateInput normalizes webhook-shaped contact payloads.
 */
define('CRM_LOADED', true);
require_once __DIR__ . '/../../public/includes/ContactCreateInput.php';

function assert_true($cond, $msg) {
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
    echo "OK: $msg\n";
}

$strict = ContactCreateInput::normalize([
    'first_name' => 'Ada',
    'last_name' => 'Lovelace',
    'email' => 'ada@example.com',
]);
assert_true($strict['first_name'] === 'Ada' && $strict['last_name'] === 'Lovelace', 'strict pass-through');

$named = ContactCreateInput::normalize([
    'name' => 'Grace Hopper',
    'email' => 'grace@example.com',
]);
assert_true($named['first_name'] === 'Grace' && $named['last_name'] === 'Hopper', 'split full name');

$nested = ContactCreateInput::normalize([
    'info' => ['name' => ['first' => 'Alan', 'last' => 'Turing']],
    'primaryInfo' => ['email' => 'alan@example.com'],
]);
assert_true($nested['first_name'] === 'Alan' && $nested['last_name'] === 'Turing', 'nested name');
assert_true(($nested['email'] ?? '') === 'alan@example.com', 'nested email');

$empty = ContactCreateInput::normalize(['email' => 'lead@example.com']);
assert_true($empty['first_name'] === 'Website' && $empty['last_name'] === 'Lead', 'defaults');

echo "ALL PASS\n";
