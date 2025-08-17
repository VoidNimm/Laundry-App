<?php
require_once __DIR__ . '/../app/init.php';
require_once __DIR__ . '/../app/auth.php';
require_login();

$paketList = $pdo->query('SELECT * FROM tb_paket')->fetchAll();
$members = $pdo->query('SELECT * FROM tb_member')->fetchAll();
$outlets = $pdo->query('SELECT * FROM tb_outlet')->fetchAll();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_outlet = intval($_POST['id_outlet']);
    $id_member = !empty($_POST['id_member']) ? intval($_POST['id_member']) : null;
    $batas_waktu = !empty($_POST['batas_waktu']) ? $_POST['batas_waktu'] : null;
    $tgl_bayar = !empty($_POST['tgl_bayar']) ? $_POST['tgl_bayar'] : null;
    $biaya_tambahan = intval($_POST['biaya_tambahan'] ?? 0);
    $diskon_input = trim($_POST['diskon'] ?? '0');
    $diskon = is_numeric($diskon_input) ? floatval($diskon_input) : 0.0;
    $diskon_type = $_POST['diskon_type'] ?? 'percent';
    $pajak = intval($_POST['pajak'] ?? 0);
    $status = $_POST['status'] ?? 'baru';
    $dibayar = $_POST['dibayar'] ?? 'belum_dibayar';
    $id_user = $_SESSION['user']['id'];

    $item_paket = $_POST['paket_id'] ?? [];
    $item_qty = $_POST['qty'] ?? [];
    $item_ket = $_POST['keterangan'] ?? [];

    if (!$id_outlet) $errors[] = 'Outlet wajib dipilih.';
    if (count($item_paket) === 0) $errors[] = 'Tambahkan minimal 1 paket.';

    // FK checks
    if ($id_outlet) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM tb_outlet WHERE id = ?');
        $stmt->execute([$id_outlet]);
        if (!$stmt->fetchColumn()) $errors[] = 'Outlet tidak valid.';
    }
    if ($id_member) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM tb_member WHERE id = ?');
        $stmt->execute([$id_member]);
        if (!$stmt->fetchColumn()) $errors[] = 'Member tidak valid.';
    }

    // Items validation and compute subtotal
    $subtotal = 0.0;
    foreach ($item_paket as $i => $pid) {
        $pid = intval($pid);
        $qty = isset($item_qty[$i]) ? floatval($item_qty[$i]) : 0;
        if ($qty <= 0) $errors[] = 'Qty item tidak boleh nol atau negatif.';
        $stmt = $pdo->prepare('SELECT harga FROM tb_paket WHERE id = ?');
        $stmt->execute([$pid]);
        $p = $stmt->fetch();
        if (!$p) {
            $errors[] = 'Paket tidak valid.';
        } else {
            $subtotal += ($p['harga'] * $qty);
        }
    }

    // Validate deadlines
    if (!empty($batas_waktu)) {
        $bw = strtotime($batas_waktu);
        if ($bw !== false && $bw < time()) $errors[] = 'Batas waktu tidak boleh lebih kecil dari tanggal saat ini.';
    }

    // Normalize/correct numbers
    if ($biaya_tambahan < 0) $biaya_tambahan = 0;
    if ($pajak < 0) $pajak = 0;
    if ($pajak > 100) $pajak = 100;
    if ($diskon < 0) $diskon = 0;
    if ($diskon_type === 'percent' && $diskon > 100) $diskon = 100;
    if ($diskon_type === 'fixed' && $diskon > $subtotal + $biaya_tambahan) $diskon = $subtotal + $biaya_tambahan;

    // Convert fixed discount to percent for storage to keep a single interpretation in reports
    if ($diskon_type === 'fixed') {
        $base = ($subtotal + $biaya_tambahan);
        $diskon = $base > 0 ? min(100.0, ($diskon / $base) * 100.0) : 0.0;
    }

    if ($dibayar === 'dibayar') {
        if (empty($tgl_bayar)) $tgl_bayar = date('Y-m-d H:i:s');
    } else {
        $tgl_bayar = null;
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $kode = 'INV'.date('YmdHis').rand(100,999);

            // Store diskon as-is; treat <=100 as percent, >100 as fixed amount in reporting
            $stmt = $pdo->prepare('INSERT INTO tb_transaksi (id_outlet, kode_invoice, id_member, tgl, batas_waktu, tgl_bayar, biaya_tambahan, diskon, pajak, status, dibayar, id_user) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$id_outlet, $kode, $id_member, $batas_waktu, $tgl_bayar, $biaya_tambahan, $diskon, $pajak, $status, $dibayar, $id_user]);
            $id_transaksi = $pdo->lastInsertId();

            $stmtItem = $pdo->prepare('INSERT INTO tb_detail_transaksi (id_transaksi, id_paket, qty, keterangan) VALUES (?, ?, ?, ?)');
            foreach ($item_paket as $i => $pid) {
                $qty = floatval($item_qty[$i] ?? 1);
                if ($qty <= 0) $qty = 1;
                $ket = $item_ket[$i] ?? '';
                $stmtItem->execute([$id_transaksi, intval($pid), $qty, $ket]);
            }

            app_log($pdo, $id_user, 'create_transaksi', 'TX:'.$kode);
            $pdo->commit();
            $success = true;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Gagal menyimpan transaksi: " . $e->getMessage();
        }
    }
}

$transactions = $pdo->query('SELECT t.*, m.nama as member_name, u.nama as user_name FROM tb_transaksi t LEFT JOIN tb_member m ON m.id=t.id_member LEFT JOIN tb_user u ON u.id=t.id_user ORDER BY t.id DESC LIMIT 200')->fetchAll();
?>
<!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Transaksi</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    :root { --bg: #000; --fg:#fff; --muted:#a3a3a3; }
    html, body { background: var(--bg); color: var(--fg); }
    .underline-slide { position: relative; }
    .underline-slide:after { content:""; position:absolute; left:0; right:100%; bottom:-2px; height:1px; background:#fff; transition: right .25s ease; }
    .underline-slide:hover:after { right:0; }
    :root { --phi: 1.618; --fs-0: clamp(1rem, 0.96rem + 0.2vw, 1.125rem); --fs-1: calc(var(--fs-0) * var(--phi)); --fs-2: calc(var(--fs-1) * var(--phi)); }
    .phi-h2 { font-size: var(--fs-2); line-height: 1.15; letter-spacing: -0.015em; }
    .phi-body { font-size: var(--fs-0); line-height: 1.6; }
    .form-input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
    .form-select:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
    .item-row { transition: all 0.2s ease; }
    .item-row:hover { background: rgba(255, 255, 255, 0.05); }
    .remove-btn { transition: all 0.2s ease; }
    .remove-btn:hover { background: rgba(239, 68, 68, 0.2); border-color: rgba(239, 68, 68, 0.5); }
    .add-btn { transition: all 0.2s ease; }
    .add-btn:hover { background: rgba(34, 197, 94, 0.2); border-color: rgba(34, 197, 94, 0.5); }
    .save-btn { transition: all 0.2s ease; }
    .save-btn:hover { background: rgba(59, 130, 246, 0.2); border-color: rgba(59, 130, 246, 0.5); }
    .calculation-box { background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(147, 51, 234, 0.1)); }
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
    <div class="max-w-7xl mx-auto px-4">
      <div class="flex items-end justify-between">
        <div>
          <h1 class="phi-h2 font-semibold">Transaksi</h1>
          <p class="text-neutral-400 mt-1 phi-body">Create a new order and review recent transactions.</p>
        </div>
        <a href="index.php" class="hidden md:inline-flex items-center px-3 py-2 rounded border border-white/15 hover:bg-white/10 transition">
          <i class="fas fa-home mr-2"></i>Home
        </a>
      </div>

      <?php if($errors): ?>
      <div class="mt-4 rounded-lg border border-red-500/30 bg-red-500/10 text-red-200 p-4 text-sm">
        <div class="flex items-center">
          <i class="fas fa-exclamation-triangle mr-2"></i>
          <div>
            <?php foreach($errors as $e) echo htmlspecialchars($e).'<br>'; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>
      <?php if($success): ?>
      <div class="mt-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 text-emerald-200 p-4 text-sm">
        <div class="flex items-center">
          <i class="fas fa-check-circle mr-2"></i>
          Transaksi berhasil disimpan!
        </div>
      </div>
      <?php endif; ?>

      <section class="mt-6 grid grid-cols-1 lg:grid-cols-4 gap-6">
        <div class="lg:col-span-3 rounded-xl border border-white/10 p-6" data-animate="fade-up">
          <div class="flex items-center justify-between mb-6">
            <h2 class="font-semibold text-lg flex items-center">
              <i class="fas fa-plus-circle mr-2"></i>
              Buat Transaksi Baru
            </h2>
            <div class="text-sm text-neutral-400">
              <i class="fas fa-clock mr-1"></i>
              <?php echo date('d/m/Y H:i'); ?>
            </div>
          </div>
          
          <form method="post" id="txForm" class="space-y-6">
            <!-- Basic Information -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <label class="block text-sm font-medium">
                <i class="fas fa-store mr-1"></i>Outlet
                <select name="id_outlet" required class="form-select mt-1 w-full bg-black/20 rounded-lg border border-white/10 p-3 text-sm">
                  <option value="">Pilih Outlet</option>
                  <?php foreach($outlets as $o) echo '<option value="'.$o['id'].'">'.htmlspecialchars($o['nama']).'</option>'; ?>
                </select>
              </label>
              <label class="block text-sm font-medium">
                <i class="fas fa-user mr-1"></i>Member
                <select name="id_member" class="form-select mt-1 w-full bg-black/20 rounded-lg border border-white/10 p-3 text-sm">
                  <option value="">Pilih Member (Opsional)</option>
                  <?php foreach($members as $m) echo '<option value="'.$m['id'].'">'.htmlspecialchars($m['nama']).'</option>'; ?>
                </select>
              </label>
            </div>

            <!-- Date and Status -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <label class="block text-sm font-medium">
                <i class="fas fa-calendar-alt mr-1"></i>Batas Waktu
                <div class="relative mt-1">
                  <input type="datetime-local" name="batas_waktu" class="form-input w-full bg-black/20 rounded-lg border border-white/10 p-3 pr-10 text-sm">
                  <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                    <i class="fas fa-calendar text-neutral-400"></i>
                  </div>
                </div>
              </label>
              <label class="block text-sm font-medium">
                <i class="fas fa-tasks mr-1"></i>Status
                <select name="status" class="form-select mt-1 w-full bg-black/20 rounded-lg border border-white/10 p-3 text-sm">
                  <option value="baru">Baru</option>
                  <option value="proses">Proses</option>
                  <option value="selesai">Selesai</option>
                  <option value="diambil">Diambil</option>
                </select>
              </label>
              <label class="block text-sm font-medium">
                <i class="fas fa-credit-card mr-1"></i>Pembayaran
                <select name="dibayar" id="dibayarSelect" class="form-select mt-1 w-full bg-black/20 rounded-lg border border-white/10 p-3 text-sm">
                  <option value="belum_dibayar">Belum Dibayar</option>
                  <option value="dibayar">Dibayar</option>
                </select>
              </label>
            </div>

            <!-- Payment Details -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <label class="block text-sm font-medium">
                <i class="fas fa-calendar-check mr-1"></i>Tanggal Bayar
                <div class="relative mt-1">
                  <input type="datetime-local" name="tgl_bayar" id="tglBayarInput" class="form-input w-full bg-black/20 rounded-lg border border-white/10 p-3 pr-10 text-sm" disabled>
                  <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                    <i class="fas fa-calendar text-neutral-400"></i>
                  </div>
                </div>
              </label>
              <label class="block text-sm font-medium">
                <i class="fas fa-plus-circle mr-1"></i>Biaya Tambahan (Rp)
                <input type="number" min="0" name="biaya_tambahan" value="0" id="biayaTambahan" class="form-input mt-1 w-full bg-black/20 rounded-lg border border-white/10 p-3 text-sm">
              </label>
              <div class="grid grid-cols-2 gap-2">
                <label class="block text-sm font-medium">
                  <i class="fas fa-percent mr-1"></i>Diskon
                  <input type="number" step="0.01" min="0" name="diskon" value="0" id="diskonInput" class="form-input mt-1 w-full bg-black/20 rounded-lg border border-white/10 p-3 text-sm">
                </label>
                <label class="block text-sm font-medium">
                  <i class="fas fa-tag mr-1"></i>Jenis
                  <select name="diskon_type" id="diskonType" class="form-select mt-1 w-full bg-black/20 rounded-lg border border-white/10 p-3 text-sm">
                    <option value="percent">%</option>
                    <option value="fixed">Rp</option>
                  </select>
                </label>
              </div>
            </div>

            <!-- Tax -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <label class="block text-sm font-medium">
                <i class="fas fa-receipt mr-1"></i>Pajak (%)
                <input type="number" min="0" max="100" name="pajak" value="0" id="pajakInput" class="form-input mt-1 w-full bg-black/20 rounded-lg border border-white/10 p-3 text-sm">
              </label>
            </div>

            <!-- Items Section -->
            <div id="itemsArea" class="mt-6">
              <div class="flex items-center justify-between mb-4">
                <div class="font-medium text-lg flex items-center">
                  <i class="fas fa-shopping-cart mr-2"></i>
                  Items
                </div>
                <button type="button" id="addItemBtn" class="add-btn px-4 py-2 text-sm rounded-lg border border-white/20 hover:bg-white/10 transition flex items-center">
                  <i class="fas fa-plus mr-1"></i>
                  Tambah Item
                </button>
              </div>
              <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                  <thead class="text-left text-neutral-400 bg-black/20">
                    <tr>
                      <th class="py-3 px-3 rounded-l-lg">Paket</th>
                      <th class="py-3 px-3">Qty</th>
                      <th class="py-3 px-3">Harga Satuan</th>
                      <th class="py-3 px-3">Subtotal</th>
                      <th class="py-3 px-3">Keterangan</th>
                      <th class="py-3 px-3 rounded-r-lg">Aksi</th>
                    </tr>
                  </thead>
                  <tbody id="itemsBody"></tbody>
                </table>
              </div>
            </div>

            <!-- Calculation Summary -->
            <div class="calculation-box rounded-xl border border-white/10 p-4 mt-6">
              <h3 class="font-semibold mb-3 flex items-center">
                <i class="fas fa-calculator mr-2"></i>
                Ringkasan Perhitungan
              </h3>
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                  <div class="text-neutral-400">Subtotal:</div>
                  <div class="font-semibold" id="subtotalDisplay">Rp 0</div>
                </div>
                <div>
                  <div class="text-neutral-400">Biaya Tambahan:</div>
                  <div class="font-semibold" id="biayaTambahanDisplay">Rp 0</div>
                </div>
                <div>
                  <div class="text-neutral-400">Diskon:</div>
                  <div class="font-semibold text-red-400" id="diskonDisplay">Rp 0</div>
                </div>
                <div>
                  <div class="text-neutral-400">Pajak:</div>
                  <div class="font-semibold text-yellow-400" id="pajakDisplay">Rp 0</div>
                </div>
              </div>
              <div class="mt-3 pt-3 border-t border-white/10">
                <div class="flex justify-between items-center">
                  <div class="text-lg font-semibold">Total:</div>
                  <div class="text-2xl font-bold text-green-400" id="totalDisplay">Rp 0</div>
                </div>
              </div>
            </div>

            <!-- Submit Button -->
            <div class="pt-4">
              <button type="submit" class="save-btn px-6 py-3 rounded-lg bg-white/10 hover:bg-white/20 border border-white/20 transition flex items-center">
                <i class="fas fa-save mr-2"></i>
                Simpan Transaksi
              </button>
            </div>
          </form>
        </div>

        <!-- Recent Transactions -->
        <div class="rounded-xl border border-white/10 p-5" data-animate="slide-in">
          <h2 class="font-semibold text-lg flex items-center mb-4">
            <i class="fas fa-history mr-2"></i>
            Transaksi Terbaru
          </h2>
          <div class="max-h-[28rem] overflow-auto">
            <table class="w-full text-sm">
              <thead class="text-left text-neutral-400 sticky top-0 bg-black/80">
                <tr>
                  <th class="py-2 pr-3">#</th>
                  <th class="py-2 pr-3">Invoice</th>
                  <th class="py-2 pr-3">Member</th>
                  <th class="py-2 pr-3">Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($transactions as $t): ?>
                <tr class="border-t border-white/5 hover:bg-white/5 transition">
                  <td class="py-2 pr-3"><?= $t['id'] ?></td>
                  <td class="py-2 pr-3 font-mono text-xs"><?= htmlspecialchars($t['kode_invoice']) ?></td>
                  <td class="py-2 pr-3"><?= htmlspecialchars($t['member_name'] ?: '-') ?></td>
                  <td class="py-2 pr-3">
                    <span class="px-2 py-1 rounded-full text-xs 
                      <?= $t['status'] === 'selesai' ? 'bg-green-500/20 text-green-300' : 
                         ($t['status'] === 'proses' ? 'bg-yellow-500/20 text-yellow-300' : 
                         ($t['status'] === 'diambil' ? 'bg-blue-500/20 text-blue-300' : 'bg-gray-500/20 text-gray-300')) ?>">
                      <?= htmlspecialchars($t['status']) ?>
                    </span>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </div>
  </main>

  <script>
  const paketList = <?php echo json_encode($paketList); ?>;
  
  function formatCurrency(amount) {
    return new Intl.NumberFormat('id-ID', {
      style: 'currency',
      currency: 'IDR',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0
    }).format(amount);
  }

  function calculateTotals() {
    let subtotal = 0;
    const rows = document.querySelectorAll('#itemsBody tr');
    
    rows.forEach(row => {
      const qtyInput = row.querySelector('input[name="qty[]"]');
      const paketSelect = row.querySelector('select[name="paket_id[]"]');
      
      if (qtyInput && paketSelect) {
        const qty = parseFloat(qtyInput.value) || 0;
        const paketId = paketSelect.value;
        const paket = paketList.find(p => p.id == paketId);
        
        if (paket) {
          const itemTotal = qty * paket.harga;
          subtotal += itemTotal;
          
          // Update item subtotal display
          const subtotalCell = row.querySelector('.item-subtotal');
          if (subtotalCell) {
            subtotalCell.textContent = formatCurrency(itemTotal);
          }
        }
      }
    });

    const biayaTambahan = parseFloat(document.getElementById('biayaTambahan').value) || 0;
    const diskonInput = parseFloat(document.getElementById('diskonInput').value) || 0;
    const diskonType = document.getElementById('diskonType').value;
    const pajakPercent = parseFloat(document.getElementById('pajakInput').value) || 0;

    let diskonValue = 0;
    if (diskonType === 'percent') {
      diskonValue = (subtotal + biayaTambahan) * (diskonInput / 100);
    } else {
      diskonValue = Math.min(diskonInput, subtotal + biayaTambahan);
    }

    const afterDiscount = Math.max(0, subtotal + biayaTambahan - diskonValue);
    const pajakValue = afterDiscount * (pajakPercent / 100);
    const total = afterDiscount + pajakValue;

    // Update displays
    document.getElementById('subtotalDisplay').textContent = formatCurrency(subtotal);
    document.getElementById('biayaTambahanDisplay').textContent = formatCurrency(biayaTambahan);
    document.getElementById('diskonDisplay').textContent = formatCurrency(diskonValue);
    document.getElementById('pajakDisplay').textContent = formatCurrency(pajakValue);
    document.getElementById('totalDisplay').textContent = formatCurrency(total);
  }

  function addRow() {
    const tbody = document.getElementById('itemsBody');
    const tr = document.createElement('tr');
    tr.className = 'item-row';
    
    const options = paketList.map(p => 
      `<option value="${p.id}" data-harga="${p.harga}">${p.nama_paket} (${p.jenis}) - ${formatCurrency(p.harga)}</option>`
    ).join('');
    
    tr.innerHTML = `
      <td class="py-3 px-3">
        <select name="paket_id[]" class="form-select w-full bg-black/20 rounded border border-white/10 p-2 text-sm" required>
          <option value="">Pilih Paket</option>
          ${options}
        </select>
      </td>
      <td class="py-3 px-3">
        <input type="number" name="qty[]" class="form-input w-20 bg-black/20 rounded border border-white/10 p-2 text-sm" value="1" step="0.1" min="0.1" required>
      </td>
      <td class="py-3 px-3 text-neutral-400 item-price">-</td>
      <td class="py-3 px-3 font-semibold item-subtotal">Rp 0</td>
      <td class="py-3 px-3">
        <input name="keterangan[]" class="form-input w-full bg-black/20 rounded border border-white/10 p-2 text-sm" placeholder="Keterangan...">
      </td>
      <td class="py-3 px-3 text-right">
        <button type="button" class="remove-btn px-3 py-1 text-xs rounded border border-red-500/30 hover:bg-red-500/10 transition">
          <i class="fas fa-trash"></i>
        </button>
      </td>`;
    
    tbody.appendChild(tr);
    
    // Add event listeners
    const removeBtn = tr.querySelector('.remove-btn');
    const qtyInput = tr.querySelector('input[name="qty[]"]');
    const paketSelect = tr.querySelector('select[name="paket_id[]"]');
    
    removeBtn.addEventListener('click', () => {
      tr.remove();
      calculateTotals();
    });
    
    qtyInput.addEventListener('input', calculateTotals);
    paketSelect.addEventListener('change', function() {
      const selectedOption = this.options[this.selectedIndex];
      const priceCell = tr.querySelector('.item-price');
      if (selectedOption.value) {
        const paket = paketList.find(p => p.id == selectedOption.value);
        priceCell.textContent = formatCurrency(paket.harga);
      } else {
        priceCell.textContent = '-';
      }
      calculateTotals();
    });
  }

  // Initialize
  document.getElementById('addItemBtn').addEventListener('click', addRow);
  addRow(); // Add first row

  // Payment toggle
  const dibayarSelect = document.getElementById('dibayarSelect');
  const tglBayarInput = document.getElementById('tglBayarInput');
  
  function syncTglBayar(){
    const paid = dibayarSelect && dibayarSelect.value === 'dibayar';
    if (tglBayarInput) {
      tglBayarInput.disabled = !paid;
      if (!paid) tglBayarInput.value = '';
    }
  }
  
  if (dibayarSelect) dibayarSelect.addEventListener('change', syncTglBayar);
  syncTglBayar();

  // Add calculation listeners
  document.getElementById('biayaTambahan').addEventListener('input', calculateTotals);
  document.getElementById('diskonInput').addEventListener('input', calculateTotals);
  document.getElementById('diskonType').addEventListener('change', calculateTotals);
  document.getElementById('pajakInput').addEventListener('input', calculateTotals);

  // Form validation
  document.getElementById('txForm').addEventListener('submit', function(e) {
    const items = document.querySelectorAll('#itemsBody tr');
    let hasValidItems = false;
    
    items.forEach(row => {
      const paketSelect = row.querySelector('select[name="paket_id[]"]');
      const qtyInput = row.querySelector('input[name="qty[]"]');
      
      if (paketSelect.value && qtyInput.value > 0) {
        hasValidItems = true;
      }
    });
    
    if (!hasValidItems) {
      e.preventDefault();
      alert('Harap tambahkan minimal 1 item dengan qty > 0');
      return false;
    }
  });
  </script>

  <script src="assets/ui.js"></script>
</body></html>