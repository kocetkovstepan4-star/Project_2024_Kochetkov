<?php
// Cookie-based lightweight session for phone-based auth
// For InfinityFree, default PHP sessions work.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 60*60*24*7,
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

function current_user_phone(): ?string {
    return isset($_SESSION['phone']) ? (string)$_SESSION['phone'] : null;
}

function require_auth_session() {
    if (!current_user_phone()) {
        respond([ 'ok' => false, 'error' => 'Unauthorized' ], 401);
    }
}


