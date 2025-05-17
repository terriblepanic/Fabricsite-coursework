<?php
if (!isset($_SESSION)) session_start();

/**
 * Генерирует и сохраняет CSRF-токен в сессии (один на всё приложение).
 */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

/**
 * Выводит `<input type="hidden" name="csrf" value="...">`
 * для вставки прямо в форму.
 */
function csrf_field(): void
{
    echo '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * Проверяет валидность токена из POST.
 */
function csrf_check(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $ok = isset($_POST['csrf'], $_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $_POST['csrf']);
        if (!$ok) {
            http_response_code(419);              // Authentication Timeout
            exit('CSRF-токен недействителен');
        }
    }
}
