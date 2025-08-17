<?php
require_once __DIR__.'/../app/init.php';
require_once __DIR__.'/../app/auth.php';
require_login();
if (!is_admin()) { die('Access denied. Admin only.'); }

$action = $_GET['action'] ?? '';
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password_hashed = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO tb_user (nama, username, password, id_outlet, role) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$_POST['nama'], $_POST['username'], $password_hashed, $_POST['id_outlet'] ?: null, $_POST['role']]);
    header('Location: user.php');
    exit;
}
$users = $pdo->query('SELECT u.*, o.nama as outlet_name FROM tb_user u LEFT JOIN tb_outlet o ON o.id = u.id_outlet ORDER BY u.id DESC')->fetchAll();
$outlets = $pdo->query('SELECT * FROM tb_outlet')->fetchAll();
?><!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Users</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"></script>
  <style>
    :root { --bg: #000; --fg:#fff; --muted:#a3a3a3; }
    html, body { background: var(--bg); color: var(--fg); }
    .underline-slide { position: relative; }
    .underline-slide:after { content:""; position:absolute; left:0; right:100%; bottom:-2px; height:1px; background:#fff; transition: right .25s ease; }
    .underline-slide:hover:after { right:0; }
    :root { --phi: 1.618; --fs-0: clamp(1rem, 0.96rem + 0.2vw, 1.125rem); --fs-1: calc(var(--fs-0) * var(--phi)); --fs-2: calc(var(--fs-1) * var(--phi)); }
    .phi-h2 { font-size: var(--fs-2); line-height: 1.15; letter-spacing: -0.015em; }
    .phi-body { font-size: var(--fs-0); line-height: 1.6; }
  </style>
</head>
<body class="h-full antialiased selection:bg-white/10">
  <!-- Tiled grid background and dual border frame -->
  <div aria-hidden="true" class="fixed inset-0 pointer-events-none [z-index:-30] bg-[linear-gradient(to_right,rgba(255,255,255,0.06)_1px,transparent_1px),linear-gradient(to_bottom,rgba(255,255,255,0.06)_1px,transparent_1px)] [background-size:32px_32px]"></div>
  <div aria-hidden="true" class="pointer-events-none fixed inset-3 rounded-2xl border border-white/20 ring-1 ring-black/60 [z-index:-10]"></div>
  <header data-nav class="fixed top-0 inset-x-0 z-30">
    <div class="max-w-6xl mx-auto px-4">
      <div class="h-16 flex items-center justify-between">
        <a href="index.php" class="text-xl">Laundry<span class="text-neutral-400">.</span></a>
        <nav class="hidden md:flex items-center gap-6 text-sm text-neutral-400">
          <a href="index.php#services" class="underline-slide">Services</a>
          <a href="index.php#process" class="underline-slide">Process</a>
          <a href="index.php#about" class="underline-slide">About</a>
          <a href="index.php#packages" class="underline-slide">Packages</a>
          <a href="logout.php" class="ml-4 px-3 py-1.5 rounded border border-white/15 hover:bg-white/10 transition">Logout</a>
        </nav>
        <button data-menu-btn class="md:hidden px-3 py-2 border border-white/15 rounded">Menu</button>
      </div>
    </div>
    <div data-menu-panel data-open="false" class="fixed top-16 right-0 w-64 h-[calc(100vh-4rem)] bg-black/80 translate-x-full transition-transform z-20 border-l border-white/10">
      <div class="p-6 flex flex-col gap-4 text-neutral-400">
        <a href="index.php#services" class="underline-slide">Services</a>
        <a href="index.php#process" class="underline-slide">Process</a>
        <a href="index.php#about" class="underline-slide">About</a>
        <a href="index.php#packages" class="underline-slide">Packages</a>
        <a href="logout.php" class="mt-2 px-3 py-1.5 rounded border border-white/15 hover:bg-white/10 transition">Logout</a>
      </div>
    </div>
  </header>

  <main class="pt-24">
    <div class="max-w-6xl mx-auto px-4">
      <div class="flex items-end justify-between">
        <div>
          <h1 class="phi-h2 font-semibold">Users</h1>
          <p class="text-neutral-400 mt-1 phi-body">Admin: manage team members.</p>
        </div>
        <a href="index.php" class="hidden md:inline-flex items-center px-3 py-2 rounded border border-white/15 hover:bg-white/10 transition">Home</a>
      </div>

      <section class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="rounded-xl border border-white/10 p-5 md:col-span-1" data-animate="fade-up">
          <h2 class="font-semibold">Add User</h2>
          <form method="post" action="user.php?action=add" class="mt-4 space-y-3 text-sm">
            <label class="block">Nama
              <input name="nama" required class="mt-1 w-full bg-black/20 rounded border border-white/10 p-2">
            </label>
            <label class="block">Username
              <input name="username" required class="mt-1 w-full bg-black/20 rounded border border-white/10 p-2">
            </label>
            <label class="block">Password
              <input name="password" type="password" required class="mt-1 w-full bg-black/20 rounded border border-white/10 p-2">
            </label>
            <label class="block">Role
              <select name="role" class="mt-1 w-full bg-black/20 rounded border border-white/10 p-2">
                <option value="admin">admin</option>
                <option value="kasir">kasir</option>
                <option value="owner">owner</option>
              </select>
            </label>
            <label class="block">Outlet
              <select name="id_outlet" class="mt-1 w-full bg-black/20 rounded border border-white/10 p-2">
                <option value="">- none -</option>
                <?php foreach($outlets as $o) echo '<option value="'.$o['id'].'">'.htmlspecialchars($o['nama']).'</option>'; ?>
              </select>
            </label>
            <button class="mt-2 w-full px-4 py-2 rounded bg-white/10 hover:bg-white/20 transition">Save</button>
          </form>
        </div>
        <div class="rounded-xl border border-white/10 p-5 md:col-span-2" data-animate="slide-in">
          <h2 class="font-semibold">List</h2>
          <div class="mt-3 overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="text-left text-neutral-400">
                <tr><th class="py-2 pr-3">#</th><th class="py-2 pr-3">Username</th><th class="py-2 pr-3">Nama</th><th class="py-2 pr-3">Role</th><th class="py-2 pr-3">Outlet</th></tr>
              </thead>
              <tbody>
                <?php foreach($users as $u) echo '<tr class="border-t border-white/5"><td class="py-2 pr-3">'.$u['id'].'</td><td class="py-2 pr-3">'.htmlspecialchars($u['username']).'</td><td class="py-2 pr-3">'.htmlspecialchars($u['nama']).'</td><td class="py-2 pr-3">'.htmlspecialchars($u['role']).'</td><td class="py-2 pr-3">'.htmlspecialchars($u['outlet_name']).'</td></tr>'; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </div>
  </main>

  <script src="assets/ui.js"></script>
</body></html>