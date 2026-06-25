<?php

declare(strict_types=1);

namespace App\Core;

function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $path): string { $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/'); return ($base === '/' ? '' : $base) . $path; }
function csrf_token(): string { if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32)); return $_SESSION['_csrf']; }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function verify_csrf(): void { if (($_POST['_csrf'] ?? '') !== ($_SESSION['_csrf'] ?? null)) { http_response_code(419); exit('CSRF token mismatch.'); } }
function redirect(string $path): never { header('Location: ' . url($path)); exit; }
function flash(string $key, ?string $message = null): ?string { if ($message !== null) { $_SESSION['_flash'][$key] = $message; return null; } $m = $_SESSION['_flash'][$key] ?? null; unset($_SESSION['_flash'][$key]); return $m; }
