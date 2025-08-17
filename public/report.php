<?php
require_once __DIR__ . '/../app/init.php';
require_once __DIR__ . '/../app/auth.php';
require_login();

// Filters
$date_start = $_GET['start'] ?? date('Y-m-01');
$date_end = $_GET['end'] ?? date('Y-m-d');
$outlet_id = isset($_GET['outlet']) && $_GET['outlet'] !== '' ? intval($_GET['outlet']) : null;
$status = $_GET['status'] ?? '';
$dibayar = $_GET['dibayar'] ?? '';
$export = $_GET['export'] ?? '';

// Fetch outlets for filter
$outlets = $pdo->query('SELECT * FROM tb_outlet ORDER BY nama')->fetchAll();

// Build query
$where = [];
$params = [];
if ($date_start) { $where[] = 'DATE(t.tgl) >= ?'; $params[] = $date_start; }
if ($date_end) { $where[] = 'DATE(t.tgl) <= ?'; $params[] = $date_end; }
if ($outlet_id) { $where[] = 't.id_outlet = ?'; $params[] = $outlet_id; }
if ($status) { $where[] = 't.status = ?'; $params[] = $status; }
if ($dibayar) { $where[] = 't.dibayar = ?'; $params[] = $dibayar; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT t.*, m.nama AS member_name, o.nama AS outlet_name, u.nama AS user_name
        FROM tb_transaksi t
        LEFT JOIN tb_member m ON m.id = t.id_member
        LEFT JOIN tb_outlet o ON o.id = t.id_outlet
        LEFT JOIN tb_user u ON u.id = t.id_user
        $whereSql
        ORDER BY t.tgl DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Helper to compute amount per transaction
function compute_amount(PDO $pdo, array $tx): array {
  $stmt = $pdo->prepare('SELECT d.qty, p.harga FROM tb_detail_transaksi d JOIN tb_paket p ON p.id = d.id_paket WHERE d.id_transaksi = ?');
  $stmt->execute([$tx['id']]);
  $items = $stmt->fetchAll();
  $subtotal = 0.0;
  foreach ($items as $it) { $subtotal += $it['qty'] * $it['harga']; }
  $biaya_tambahan = max(0, (int)$tx['biaya_tambahan']);
  $diskon_value = 0.0;
  $diskon = (float)$tx['diskon'];
  // interpret: <=100 means percent, >100 means fixed currency
  if ($diskon <= 100.0) {
    $diskon_value = ($subtotal + $biaya_tambahan) * ($diskon / 100.0);
  } else {
    $diskon_value = min($diskon, $subtotal + $biaya_tambahan);
  }
  $pajak_percent = max(0, min(100, (int)$tx['pajak']));
  $after_discount = max(0.0, ($subtotal + $biaya_tambahan) - $diskon_value);
  $pajak_value = $after_discount * ($pajak_percent / 100.0);
  $total = $after_discount + $pajak_value;
  return [
    'subtotal' => $subtotal,
    'diskon_value' => $diskon_value,
    'pajak_value' => $pajak_value,
    'total' => $total,
  ];
}

// Compute totals and separate lists
$totals = [
  'subtotal' => 0.0,
  'diskon' => 0.0,
  'pajak' => 0.0,
  'total' => 0.0,
  'paid' => 0.0,
  'unpaid' => 0.0,
];
$paidRows = [];
$unpaidRows = [];
foreach ($rows as &$tx) {
  $amount = compute_amount($pdo, $tx);
  $tx['_subtotal'] = $amount['subtotal'];
  $tx['_diskon'] = $amount['diskon_value'];
  $tx['_pajak'] = $amount['pajak_value'];
  $tx['_total'] = $amount['total'];
  $totals['subtotal'] += $amount['subtotal'];
  $totals['diskon'] += $amount['diskon_value'];
  $totals['pajak'] += $amount['pajak_value'];
  $totals['total'] += $amount['total'];
  if ($tx['dibayar'] === 'dibayar') { $totals['paid'] += $amount['total']; $paidRows[] = $tx; }
  else { $totals['unpaid'] += $amount['total']; $unpaidRows[] = $tx; }
}
unset($tx);

// CSV export
if ($export === 'csv') {
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="report.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['Tanggal','Invoice','Outlet','Member','Status','Dibayar','Subtotal','Diskon','Pajak','Total']);
  foreach ($rows as $r) {
    fputcsv($out, [
      $r['tgl'], $r['kode_invoice'], $r['outlet_name'], $r['member_name'], $r['status'], $r['dibayar'],
      number_format($r['_subtotal'], 2, '.', ''),
      number_format($r['_diskon'], 2, '.', ''),
      number_format($r['_pajak'], 2, '.', ''),
      number_format($r['_total'], 2, '.', ''),
    ]);
  }
  fclose($out);
  exit;
}

// PDF: use browser print (simple). If export=pdf, render a minimal page and use @media print styling.
if ($export === 'pdf') {
  // fallthrough to HTML; users can Ctrl+P to Save as PDF.
}
?>
<!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Report</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    :root { --bg: #000; --fg:#fff; --muted:#a3a3a3; }
    html, body { background: var(--bg); color: var(--fg); }
    .underline-slide { position: relative; }
    .underline-slide:after { content:""; position:absolute; left:0; right:100%; bottom:-2px; height:1px; background:#fff; transition: right .25s ease; }
    .underline-slide:hover:after { right:0; }
    .form-input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
    .form-select:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
    .filter-btn { transition: all 0.2s ease; }
    .filter-btn:hover { background: rgba(59, 130, 246, 0.2); border-color: rgba(59, 130, 246, 0.5); }
    .export-btn { transition: all 0.2s ease; }
    .export-btn:hover { background: rgba(34, 197, 94, 0.2); border-color: rgba(34, 197, 94, 0.5); }
    .stat-card { background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(147, 51, 234, 0.1)); }
    .table-row { transition: all 0.2s ease; }
    .table-row:hover { background: rgba(255, 255, 255, 0.05); }
    @media print {
      .no-print { display: none !important; }
      body { background: #fff; color: #000; }
      table { width: 100%; border-collapse: collapse; }
      th, td { border: 1px solid #000; padding: 4px; }
    }
  </style>
  </head>
  <body class="h-full antialiased selection:bg-white/10">
  <header class="fixed top-0 inset-x-0 z-30 no-print">
    <div class="max-w-7xl mx-auto px-4">
      <div class="h-16 flex items-center justify-between">
        <a href="index.php" class="text-xl">Laundry<span class="text-neutral-400">.</span></a>
        <nav class="hidden md:flex items-center gap-6 text-sm text-neutral-400">
          <a href="index.php#services" class="underline-slide">Services</a>
          <a href="index.php#process" class="underline-slide">Process</a>
          <a href="index.php#about" class="underline-slide">About</a>
          <a href="index.php#packages" class="underline-slide">Packages</a>
          <a href="logout.php" class="ml-4 px-3 py-1.5 rounded border border-white/15 hover:bg-white/10 transition">Logout</a>
        </nav>
      </div>
    </div>
  </header>
  <main class="pt-24">
    <div class="max-w-7xl mx-auto px-4">
      <div class="flex items-end justify-between">
        <div>
          <h1 class="text-3xl font-semibold flex items-center">
            <i class="fas fa-chart-bar mr-3"></i>
            Laporan Transaksi
          </h1>
          <p class="text-neutral-400 mt-2">Filter dan ekspor data transaksi laundry.</p>
        </div>
        <a href="index.php" class="no-print hidden md:inline-flex items-center px-4 py-2 rounded-lg border border-white/15 hover:bg-white/10 transition">
          <i class="fas fa-home mr-2"></i>Home
        </a>
      </div>

      <!-- Enhanced Filters -->
      <form method="get" class="no-print mt-6 bg-black/20 rounded-xl border border-white/10 p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-semibold text-lg flex items-center">
            <i class="fas fa-filter mr-2"></i>
            Filter Data
          </h3>
          <div class="text-sm text-neutral-400">
            <i class="fas fa-calendar mr-1"></i>
            <?= date('d/m/Y', strtotime($date_start)) ?> - <?= date('d/m/Y', strtotime($date_end)) ?>
          </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 text-sm">
          <label class="block">
            <div class="flex items-center mb-1">
              <i class="fas fa-calendar-day mr-1"></i>
              Tanggal Mulai
            </div>
            <div class="relative">
              <input type="date" name="start" value="<?= htmlspecialchars($date_start) ?>" class="form-input w-full bg-black/20 rounded-lg border border-white/10 p-3 pr-10">
              <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                <i class="fas fa-calendar text-neutral-400"></i>
              </div>
            </div>
          </label>
          <label class="block">
            <div class="flex items-center mb-1">
              <i class="fas fa-calendar-check mr-1"></i>
              Tanggal Akhir
            </div>
            <div class="relative">
              <input type="date" name="end" value="<?= htmlspecialchars($date_end) ?>" class="form-input w-full bg-black/20 rounded-lg border border-white/10 p-3 pr-10">
              <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                <i class="fas fa-calendar text-neutral-400"></i>
              </div>
            </div>
          </label>
          <label class="block">
            <div class="flex items-center mb-1">
              <i class="fas fa-store mr-1"></i>
              Outlet
            </div>
            <select name="outlet" class="form-select w-full bg-black/20 rounded-lg border border-white/10 p-3">
              <option value="">Semua Outlet</option>
              <?php foreach ($outlets as $o): $sel = ($outlet_id==$o['id'])?' selected':''; echo '<option value="'.$o['id'].'"'.$sel.'>'.htmlspecialchars($o['nama']).'</option>'; endforeach; ?>
            </select>
          </label>
          <label class="block">
            <div class="flex items-center mb-1">
              <i class="fas fa-tasks mr-1"></i>
              Status
            </div>
            <select name="status" class="form-select w-full bg-black/20 rounded-lg border border-white/10 p-3">
              <option value="">Semua Status</option>
              <?php foreach(['baru','proses','selesai','diambil'] as $st){ $sel=$status===$st?' selected':''; echo '<option value="'.$st.'"'.$sel.'>'.htmlspecialchars(ucfirst($st)).'</option>'; } ?>
            </select>
          </label>
          <label class="block">
            <div class="flex items-center mb-1">
              <i class="fas fa-credit-card mr-1"></i>
              Pembayaran
            </div>
            <select name="dibayar" class="form-select w-full bg-black/20 rounded-lg border border-white/10 p-3">
              <option value="">Semua</option>
              <?php foreach(['dibayar','belum_dibayar'] as $db){ $sel=$dibayar===$db?' selected':''; echo '<option value="'.$db.'"'.$sel.'>'.htmlspecialchars($db === 'dibayar' ? 'Sudah Dibayar' : 'Belum Dibayar').'</option>'; } ?>
            </select>
          </label>
        </div>
        
        <div class="flex gap-3 mt-4">
          <button type="submit" class="filter-btn px-6 py-3 rounded-lg bg-white/10 hover:bg-white/20 border border-white/20 transition flex items-center">
            <i class="fas fa-search mr-2"></i>
            Terapkan Filter
          </button>
          <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>" class="export-btn px-6 py-3 rounded-lg border border-white/20 hover:bg-white/10 transition flex items-center">
            <i class="fas fa-file-csv mr-2"></i>
            Export CSV
          </a>
          <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'pdf'])) ?>" class="export-btn px-6 py-3 rounded-lg border border-white/20 hover:bg-white/10 transition flex items-center" onclick="window.print(); return false;">
            <i class="fas fa-file-pdf mr-2"></i>
            Export PDF
          </a>
        </div>
      </form>

      <!-- Statistics Cards -->
      <section class="mt-8 grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="stat-card rounded-xl border border-white/10 p-6">
          <div class="flex items-center justify-between">
            <div>
              <div class="text-sm text-neutral-400 flex items-center">
                <i class="fas fa-receipt mr-2"></i>
                Total Transaksi
              </div>
              <div class="text-3xl font-bold mt-2"><?= number_format(count($rows)) ?></div>
            </div>
            <div class="text-4xl text-neutral-400">
              <i class="fas fa-chart-line"></i>
            </div>
          </div>
        </div>
        <div class="stat-card rounded-xl border border-white/10 p-6">
          <div class="flex items-center justify-between">
            <div>
              <div class="text-sm text-neutral-400 flex items-center">
                <i class="fas fa-money-bill-wave mr-2"></i>
                Total Pendapatan
              </div>
              <div class="text-3xl font-bold mt-2 text-green-400">Rp <?= number_format($totals['paid'],0,',','.') ?></div>
            </div>
            <div class="text-4xl text-neutral-400">
              <i class="fas fa-coins"></i>
            </div>
          </div>
        </div>
        <div class="stat-card rounded-xl border border-white/10 p-6">
          <div class="flex items-center justify-between">
            <div>
              <div class="text-sm text-neutral-400 flex items-center">
                <i class="fas fa-clock mr-2"></i>
                Belum Dibayar
              </div>
              <div class="text-3xl font-bold mt-2 text-red-400">Rp <?= number_format($totals['unpaid'],0,',','.') ?></div>
            </div>
            <div class="text-4xl text-neutral-400">
              <i class="fas fa-exclamation-triangle"></i>
            </div>
          </div>
        </div>
        <div class="stat-card rounded-xl border border-white/10 p-6">
          <div class="flex items-center justify-between">
            <div>
              <div class="text-sm text-neutral-400 flex items-center">
                <i class="fas fa-percentage mr-2"></i>
                Rata-rata per Transaksi
              </div>
              <div class="text-3xl font-bold mt-2 text-purple-400">
                Rp <?= count($rows) > 0 ? number_format($totals['total'] / count($rows), 0, ',', '.') : '0' ?>
              </div>
            </div>
            <div class="text-4xl text-neutral-400">
              <i class="fas fa-calculator"></i>
            </div>
          </div>
        </div>
      </section>

      <!-- Summary Totals -->
      <section class="mt-8">
        <h2 class="font-semibold text-xl flex items-center mb-4">
          <i class="fas fa-calculator mr-2"></i>
          Ringkasan Keuangan
        </h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
          <div class="rounded-xl border border-white/10 p-4 bg-black/20">
            <div class="text-neutral-400 flex items-center">
              <i class="fas fa-receipt mr-2"></i>
              Subtotal
            </div>
            <div class="text-xl font-bold mt-1">Rp <?= number_format($totals['subtotal'],0,',','.') ?></div>
          </div>
          <div class="rounded-xl border border-white/10 p-4 bg-black/20">
            <div class="text-neutral-400 flex items-center">
              <i class="fas fa-tag mr-2"></i>
              Total Diskon
            </div>
            <div class="text-xl font-bold mt-1 text-red-400">Rp <?= number_format($totals['diskon'],0,',','.') ?></div>
          </div>
          <div class="rounded-xl border border-white/10 p-4 bg-black/20">
            <div class="text-neutral-400 flex items-center">
              <i class="fas fa-receipt mr-2"></i>
              Total Pajak
            </div>
            <div class="text-xl font-bold mt-1 text-yellow-400">Rp <?= number_format($totals['pajak'],0,',','.') ?></div>
          </div>
          <div class="rounded-xl border border-white/10 p-4 bg-black/20">
            <div class="text-neutral-400 flex items-center">
              <i class="fas fa-money-bill-wave mr-2"></i>
              Total Pembayaran
            </div>
            <div class="text-xl font-bold mt-1 text-green-400">Rp <?= number_format($totals['total'],0,',','.') ?></div>
          </div>
        </div>
      </section>

      <!-- Payment Status Tables -->
      <section class="mt-10 grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="rounded-xl border border-white/10 p-6">
          <h3 class="font-semibold text-lg flex items-center mb-4">
            <i class="fas fa-check-circle mr-2"></i>
            Sudah Dibayar (<?= count($paidRows) ?>)
          </h3>
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="text-left text-neutral-400 bg-black/20">
                <tr>
                  <th class="py-3 px-3 rounded-l-lg">Tanggal</th>
                  <th class="py-3 px-3">Invoice</th>
                  <th class="py-3 px-3">Member</th>
                  <th class="py-3 px-3 rounded-r-lg">Total</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($paidRows as $r): ?>
                <tr class="table-row border-t border-white/5">
                  <td class="py-3 px-3"><?= date('d/m/Y', strtotime($r['tgl'])) ?></td>
                  <td class="py-3 px-3 font-mono text-xs"><?= htmlspecialchars($r['kode_invoice']) ?></td>
                  <td class="py-3 px-3"><?= htmlspecialchars($r['member_name'] ?: '-') ?></td>
                  <td class="py-3 px-3 font-semibold text-green-400">Rp <?= number_format($r['_total'],0,',','.') ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="rounded-xl border border-white/10 p-6">
          <h3 class="font-semibold text-lg flex items-center mb-4">
            <i class="fas fa-clock mr-2"></i>
            Belum Dibayar (<?= count($unpaidRows) ?>)
          </h3>
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="text-left text-neutral-400 bg-black/20">
                <tr>
                  <th class="py-3 px-3 rounded-l-lg">Tanggal</th>
                  <th class="py-3 px-3">Invoice</th>
                  <th class="py-3 px-3">Member</th>
                  <th class="py-3 px-3 rounded-r-lg">Total</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($unpaidRows as $r): ?>
                <tr class="table-row border-t border-white/5">
                  <td class="py-3 px-3"><?= date('d/m/Y', strtotime($r['tgl'])) ?></td>
                  <td class="py-3 px-3 font-mono text-xs"><?= htmlspecialchars($r['kode_invoice']) ?></td>
                  <td class="py-3 px-3"><?= htmlspecialchars($r['member_name'] ?: '-') ?></td>
                  <td class="py-3 px-3 font-semibold text-red-400">Rp <?= number_format($r['_total'],0,',','.') ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- Detailed Table -->
      <section class="mt-10">
        <h2 class="font-semibold text-xl flex items-center mb-4">
          <i class="fas fa-table mr-2"></i>
          Detail Transaksi (<?= count($rows) ?>)
        </h2>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="text-left text-neutral-400 bg-black/20 sticky top-0">
              <tr>
                <th class="py-3 px-3 rounded-l-lg">Tanggal</th>
                <th class="py-3 px-3">Invoice</th>
                <th class="py-3 px-3">Outlet</th>
                <th class="py-3 px-3">Member</th>
                <th class="py-3 px-3">Status</th>
                <th class="py-3 px-3">Pembayaran</th>
                <th class="py-3 px-3">Subtotal</th>
                <th class="py-3 px-3">Diskon</th>
                <th class="py-3 px-3">Pajak</th>
                <th class="py-3 px-3 rounded-r-lg">Total</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($rows as $r): ?>
              <tr class="table-row border-t border-white/5">
                <td class="py-3 px-3"><?= date('d/m/Y H:i', strtotime($r['tgl'])) ?></td>
                <td class="py-3 px-3 font-mono text-xs"><?= htmlspecialchars($r['kode_invoice']) ?></td>
                <td class="py-3 px-3"><?= htmlspecialchars($r['outlet_name']) ?></td>
                <td class="py-3 px-3"><?= htmlspecialchars($r['member_name'] ?: '-') ?></td>
                <td class="py-3 px-3">
                  <span class="px-2 py-1 rounded-full text-xs 
                    <?= $r['status'] === 'selesai' ? 'bg-green-500/20 text-green-300' : 
                       ($r['status'] === 'proses' ? 'bg-yellow-500/20 text-yellow-300' : 
                       ($r['status'] === 'diambil' ? 'bg-blue-500/20 text-blue-300' : 'bg-gray-500/20 text-gray-300')) ?>">
                    <?= htmlspecialchars(ucfirst($r['status'])) ?>
                  </span>
                </td>
                <td class="py-3 px-3">
                  <span class="px-2 py-1 rounded-full text-xs 
                    <?= $r['dibayar'] === 'dibayar' ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300' ?>">
                    <?= htmlspecialchars($r['dibayar'] === 'dibayar' ? 'Dibayar' : 'Belum') ?>
                  </span>
                </td>
                <td class="py-3 px-3">Rp <?= number_format($r['_subtotal'],0,',','.') ?></td>
                <td class="py-3 px-3 text-red-400">Rp <?= number_format($r['_diskon'],0,',','.') ?></td>
                <td class="py-3 px-3 text-yellow-400">Rp <?= number_format($r['_pajak'],0,',','.') ?></td>
                <td class="py-3 px-3 font-semibold text-green-400">Rp <?= number_format($r['_total'],0,',','.') ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </main>
  <script src="assets/ui.js"></script>
  </body>
  </html>


