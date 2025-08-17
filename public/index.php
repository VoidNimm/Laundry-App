<?php
require_once __DIR__ . '/../app/init.php';
require_once __DIR__ . '/../app/auth.php';
require_login();
$user = current_user();
$aboutImage = 'assets/manon-lince-gme2yNIbDe0-unsplash.jpg';
?>
<!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: { brand: { 100:'#fafafa', 200:'#d4d4d4', 300:'#a3a3a3' } },
          fontFamily: { display: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'] }
        }
      }
    }
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"></script>
  <style>
    :root { --bg: #000; --fg:#fff; --muted:#a3a3a3; }
    html, body { background: var(--bg); color: var(--fg); }
    .underline-slide { position: relative; }
    .underline-slide:after { content:""; position:absolute; left:0; right:100%; bottom:-2px; height:1px; background:#fff; transition: right .25s ease; }
    .underline-slide:hover:after { right:0; }
    .backdrop-blur { backdrop-filter: saturate(180%) blur(10px); }
    /* Golden ratio type scale */
    :root { --phi: 1.618; --fs-0: clamp(1rem, 0.96rem + 0.2vw, 1.125rem); --fs-1: calc(var(--fs-0) * var(--phi)); --fs-2: calc(var(--fs-1) * var(--phi)); --fs-3: calc(var(--fs-2) * var(--phi)); }
    .phi-h1 { font-size: var(--fs-3); line-height: 1.1; letter-spacing: -0.02em; }
    .phi-h2 { font-size: var(--fs-2); line-height: 1.15; letter-spacing: -0.015em; }
    .phi-h3 { font-size: var(--fs-1); line-height: 1.2; letter-spacing: -0.01em; }
    .phi-body { font-size: var(--fs-0); line-height: 1.6; }
    /* Moving background strip behind inline text */
    .moving-bg { position: relative; z-index: 0; display: inline-block; padding: 0 .35rem; }
    .moving-bg::before {
      content: "";
      position: absolute;
      z-index: -1;
      left: 50%;
      top: 50%;
      transform: translate(-50%, -50%);
      width: 140%;
      height: 250px;
      background-image: url('assets/manon-lince-gme2yNIbDe0-unsplash.jpg');
      background-repeat: repeat-x;
      background-size: auto 250px;
      background-position: 0 50%;
      animation: moving-bg-pan 40s linear infinite;
      opacity: .25;
      border-radius: 0;
      pointer-events: none;
    }
    @keyframes moving-bg-pan { from { background-position-x: 0; } to { background-position-x: -1200px; } }
  </style>
</head>
<body class="h-full antialiased selection:bg-white/10">
  <!-- Tiled grid background and dual border frame -->
  <div aria-hidden="true" class="fixed inset-0 pointer-events-none [z-index:-30] bg-[linear-gradient(to_right,rgba(255,255,255,0.06)_1px,transparent_1px),linear-gradient(to_bottom,rgba(255,255,255,0.06)_1px,transparent_1px)] [background-size:32px_32px]"></div>
  <div aria-hidden="true" class="pointer-events-none fixed inset-3 rounded-2xl border border-white/20 ring-1 ring-black/60 [z-index:-10]"></div>
  <!-- Navbar -->
  <header data-nav class="fixed top-0 inset-x-0 z-30">
    <div class="max-w-6xl mx-auto px-4">
      <div class="h-16 flex items-center justify-between">
        <a href="index.php" class="font-display text-xl tracking-wide">Laundry<span class="text-brand-300">.</span></a>
        <nav class="hidden md:flex items-center gap-8 text-sm text-brand-300">
          <a href="#services" class="underline-slide">Services</a>
          <a href="#process" class="underline-slide">Process</a>
          <a href="#about" class="underline-slide">About</a>
          <a href="#packages" class="underline-slide">Packages</a>
          <a href="logout.php" class="ml-4 px-3 py-1.5 rounded border border-white/15 hover:bg-white/10 transition">Logout</a>
        </nav>
        <button data-menu-btn class="md:hidden px-3 py-2 border border-white/15 rounded">Menu</button>
      </div>
    </div>
    <!-- mobile panel -->
    <div data-menu-panel data-open="false" class="fixed top-16 right-0 w-64 h-[calc(100vh-4rem)] bg-black/80 translate-x-full transition-transform z-20 border-l border-white/10">
      <div class="p-6 flex flex-col gap-4 text-brand-300">
        <a href="#services" class="underline-slide">Services</a>
        <a href="#process" class="underline-slide">Process</a>
        <a href="#about" class="underline-slide">About</a>
        <a href="#packages" class="underline-slide">Packages</a>
        <a href="logout.php" class="mt-2 px-3 py-1.5 rounded border border-white/15 hover:bg-white/10 transition">Logout</a>
      </div>
    </div>
  </header>

  <!-- Background shapes -->
  <div aria-hidden="true" class="fixed inset-0 -z-10 overflow-hidden">
    <div data-parallax="0.22" class="absolute bottom-0 left-1/2 -translate-x-1/2 w-[36rem] h-[36rem] rounded-full blur-[100px] bg-white/5"></div>
  </div>

  <!-- Hero -->
  <section data-hero class="relative pt-32 md:pt-40 pb-20 md:pb-28">
    <div class="max-w-6xl mx-auto px-4 text-center">
      <h1 data-animate="headline" class="font-display phi-h1 font-extrabold tracking-tight"><span class="moving-bg">Operate your Laundry with Confidence</span></h1>
      <p data-animate="sub" class="mt-5 text-brand-300 max-w-2xl mx-auto phi-body">A minimal, fast and elegant backoffice for outlets, packages and transactions. Designed in the spirit of premium creative studios</p>
      <div class="mt-8" data-animate="cta">
        <a href="transaksi.php" class="inline-flex items-center gap-2 px-5 py-3 rounded border border-white/15 hover:bg-white/10 transition will-change-transform">Start a Transaction<span>→</span></a>
      </div>
    </div>
  </section>
  

  <!-- Services -->
  <section id="services" class="mt-40 md:mt-56">
    <div class="max-w-6xl mx-auto px-4">
      <header class="mb-12 md:mb-16">
        <h2 class="phi-h2 font-semibold">Features</h2>
        <p class="text-brand-300 mt-2 phi-body">Everything you need to run your laundry business.</p>
      </header>
       <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 md:gap-6">
        <a href="transaksi.php" class="group rounded-xl border border-white/10 p-5 hover:bg-white/[0.03] transition" data-animate="fade-up">
          <div class="mb-3 text-white/90">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M8 3h8a2 2 0 0 1 2 2v14l-3-2-3 2-3-2-3 2V5a2 2 0 0 1 2-2z"/>
              <path d="M9 7h6"/>
              <path d="M9 11h6"/>
              <path d="M9 15h3"/>
            </svg>
          </div>
          <div class="font-semibold">Transaksi</div>
          <p class="text-brand-300 text-sm mt-1">Create and review orders with ease.</p>
          <div class="mt-4 text-xs text-brand-300 group-hover:translate-x-1 transition">Open →</div>
        </a>
         <a href="report.php" class="group rounded-xl border border-white/10 p-5 hover:bg-white/[0.03] transition" data-animate="fade-up">
           <div class="mb-3 text-white/90">
             <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
               <path d="M3 3h18v18H3z"/>
               <path d="M7 13l3 3 7-7"/>
             </svg>
           </div>
           <div class="font-semibold">Report</div>
           <p class="text-brand-300 text-sm mt-1">Filter, totals, CSV/PDF.</p>
           <div class="mt-4 text-xs text-brand-300 group-hover:translate-x-1 transition">Open →</div>
         </a>
        <a href="member.php" class="group rounded-xl border border-white/10 p-5 hover:bg-white/[0.03] transition" data-animate="fade-up">
          <div class="mb-3 text-white/90">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <circle cx="12" cy="8" r="4"/>
              <path d="M4 20a8 8 0 0 1 16 0"/>
            </svg>
          </div>
          <div class="font-semibold">Member</div>
          <p class="text-brand-300 text-sm mt-1">Manage your loyal customers.</p>
          <div class="mt-4 text-xs text-brand-300 group-hover:translate-x-1 transition">Open →</div>
        </a>
        <a href="paket.php" class="group rounded-xl border border-white/10 p-5 hover:bg-white/[0.03] transition" data-animate="fade-up">
          <div class="mb-3 text-white/90">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M21 7 12 3 3 7l9 4 9-4z"/>
              <path d="M3 7v10l9 4 9-4V7"/>
              <path d="M12 11v10"/>
            </svg>
          </div>
          <div class="font-semibold">Paket</div>
          <p class="text-brand-300 text-sm mt-1">Curate your service catalog.</p>
          <div class="mt-4 text-xs text-brand-300 group-hover:translate-x-1 transition">Open →</div>
        </a>
        <a href="outlet.php" class="group rounded-xl border border-white/10 p-5 hover:bg-white/[0.03] transition" data-animate="fade-up">
          <div class="mb-3 text-white/90">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M4 10h16v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-8z"/>
              <path d="M4 10l2-4h12l2 4"/>
              <path d="M10 20v-4h4v4"/>
            </svg>
          </div>
          <div class="font-semibold">Outlet</div>
          <p class="text-brand-300 text-sm mt-1">Scale across multiple locations.</p>
          <div class="mt-4 text-xs text-brand-300 group-hover:translate-x-1 transition">Open →</div>
        </a>
        <?php if (is_admin()): ?>
        <a href="user.php" class="group rounded-xl border border-white/10 p-5 hover:bg-white/[0.03] transition" data-animate="fade-up">
          <div class="mb-3 text-white/90">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M6 4v16"/>
              <circle cx="6" cy="8" r="2.4"/>
              <path d="M12 4v16"/>
              <circle cx="12" cy="14" r="2.4"/>
              <path d="M18 4v16"/>
              <circle cx="18" cy="10" r="2.4"/>
            </svg>
          </div>
          <div class="font-semibold">Users</div>
          <p class="text-brand-300 text-sm mt-1">Manage team permissions.</p>
          <div class="mt-4 text-xs text-brand-300 group-hover:translate-x-1 transition">Open →</div>
        </a>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- Process -->
  <section id="process" class="mt-40 md:mt-56">
    <div class="max-w-6xl mx-auto px-4">
      <header class="mb-12 md:mb-16">
        <h2 class="phi-h2 font-semibold">Process</h2>
        <p class="text-brand-300 mt-2 phi-body">A simple, transparent flow from drop-off to pickup.</p>
      </header>
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 md:gap-6">
        <?php $steps = ['Drop-off','Sorting','Wash & Dry','Pickup']; foreach($steps as $i=>$s): ?>
        <div class="rounded-xl border border-white/10 p-6" data-animate="fade-up">
          <div class="text-brand-300 text-sm">Step <?= $i+1 ?></div>
          <div class="mt-2 font-semibold text-lg"><?= htmlspecialchars($s) ?></div>
          <p class="text-brand-300 text-sm mt-2">We handle it with care using standardized procedures.</p>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- About -->
  <section id="about" class="mt-40 md:mt-56">
    <div class="max-w-6xl mx-auto px-4 grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-12 items-center">
      <div class="order-2 md:order-1" data-animate="slide-in">
        <h2 class="phi-h2 font-semibold">We focus on clarity and speed</h2>
        <p class="text-brand-300 mt-3 phi-body">Built for daily operations with a premium feel. Balanced typography, generous spacing, and subtle motion give you a calm working environment.</p>
        <div class="mt-6 flex gap-3">
          <a href="transaksi.php" class="px-4 py-2 rounded border border-white/15 hover:bg-white/10 transition">New Transaction</a>
          <a href="member.php" class="px-4 py-2 rounded border border-white/15 hover:bg-white/10 transition">Add Member</a>
        </div>
      </div>
      <a href="<?= htmlspecialchars($aboutImage) ?>" target="_blank" class="order-1 md:order-2 block relative h-56 md:h-80 rounded-2xl overflow-hidden border border-white/10" aria-label="About image">
        <img src="<?= htmlspecialchars($aboutImage) ?>" alt="About" class="w-full h-full object-cover">
      </a>
    </div>
  </section>

  <!-- Packages -->
  <section id="packages" class="mt-40 md:mt-56">
    <div class="max-w-6xl mx-auto px-4">
      <header class="mb-12 md:mb-16">
        <h2 class="phi-h2 font-semibold">The Package</h2>
        <p class="text-brand-300 mt-2 phi-body">Curated services ready to go.</p>
      </header>
      <?php $paketPreview = $pdo->query('SELECT p.*, o.nama as outlet_name FROM tb_paket p LEFT JOIN tb_outlet o ON o.id=p.id_outlet LIMIT 8')->fetchAll(); ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
        <?php foreach ($paketPreview as $p): ?>
          <div class="rounded-xl border border-white/10 p-5 hover:bg-white/[0.03] transition" data-animate="fade-up">
            <div class="text-sm text-brand-300"><?= htmlspecialchars($p['jenis']) ?> • <?= htmlspecialchars($p['outlet_name']) ?></div>
            <div class="mt-2 font-semibold text-lg"><?= htmlspecialchars($p['nama_paket']) ?></div>
            <div class="mt-1 text-brand-300">Rp <?= number_format($p['harga'],0,',','.') ?></div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="mt-8 text-center">
        <a href="paket.php" class="px-4 py-2 rounded border border-white/15 hover:bg-white/10 transition">Manage Packages</a>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="mt-24 md:mt-36 border-t border-white/10">
    <div class="max-w-6xl mx-auto px-4 py-10 flex flex-col md:flex-row items-center justify-between gap-4 text-brand-300 text-sm">
      <div>© <?= date('Y') ?> Laundry.</div>
      <div class="flex items-center gap-4">
        <a class="underline-slide" href="#services">Services</a>
        <a class="underline-slide" href="#process">Process</a>
        <a class="underline-slide" href="#about">About</a>
        <a class="underline-slide" href="#packages">Packages</a>
      </div>
    </div>
  </footer>

  <script src="assets/ui.js"></script>
</body>
</html>