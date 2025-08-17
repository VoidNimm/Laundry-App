<?php
require_once __DIR__ . '/../app/init.php';
require_once __DIR__ . '/../app/auth.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if (attempt_login($pdo, $username, $password)) {
        header('Location: index.php');
        exit;
    } else {
        $errors[] = 'Login failed. Check username/password.';
    }
}
?><!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    :root { --bg: #000; --fg:#fff; --muted:#a3a3a3; }
    html, body { background: var(--bg); color: var(--fg); }
    :root { --phi: 1.618; --fs-0: clamp(1rem, 0.96rem + 0.2vw, 1.125rem); --fs-1: calc(var(--fs-0) * var(--phi)); --fs-2: calc(var(--fs-1) * var(--phi)); }
    .phi-h2 { font-size: var(--fs-2); line-height: 1.15; letter-spacing: -0.015em; }
    .phi-body { font-size: var(--fs-0); line-height: 1.6; }
  </style>
</head>
<body class="h-full antialiased selection:bg-white/10">
  <!-- Tiled grid background and dual border frame -->
  <div aria-hidden="true" class="fixed inset-0 pointer-events-none [z-index:-30] bg-[linear-gradient(to_right,rgba(255,255,255,0.06)_1px,transparent_1px),linear-gradient(to_bottom,rgba(255,255,255,0.06)_1px,transparent_1px)] [background-size:32px_32px]"></div>
  <div aria-hidden="true" class="pointer-events-none fixed inset-3 rounded-2xl border border-white/20 ring-1 ring-black/60 [z-index:-10]"></div>
  <main class="min-h-screen grid place-items-center px-4">
    <div class="w-full max-w-sm rounded-2xl border border-white/10 p-6 bg-black/40">
      <div class="text-center mb-4">
        <div class="text-lg font-display phi-h1">Laundry<span class="text-neutral-400">.</span></div>
        <p class="text-neutral-400 phi-body">Please sign in to continue</p>
      </div>
      <?php if($errors): ?>
      <div class="mb-3 rounded border border-red-500/30 bg-red-500/10 text-red-200 p-3 text-sm">
        <?php foreach($errors as $e) echo htmlspecialchars($e).'<br>'; ?>
      </div>
      <?php endif; ?>
      <form method="post" class="space-y-3 text-sm">
        <label class="block">Username
          <input name="username" required class="mt-1 w-full bg-black/20 rounded border border-white/10 p-2">
        </label>
        <label class="block">Password
          <input name="password" type="password" required class="mt-1 w-full bg-black/20 rounded border border-white/10 p-2">
        </label>
        <button class="w-full mt-2 px-4 py-2 rounded bg-white/10 hover:bg-white/20 transition">Login</button>
      </form>
    </div>
  </main>
</body></html>