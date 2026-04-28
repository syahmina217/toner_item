<?php
// ── DB helpers ────────────────────────────────────────────────────────────────
function getDeptConn(): ?mysqli
{
    $bootstrap = new mysqli("localhost", "root", "");
    if ($bootstrap->connect_error) return null;
    $bootstrap->query("CREATE DATABASE IF NOT EXISTS department");
    $bootstrap->close();

    $conn = new mysqli("localhost", "root", "", "department");
    if ($conn->connect_error) return null;

    $conn->query(
        "CREATE TABLE IF NOT EXISTS department_stock_item (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cawangan VARCHAR(255) NOT NULL,
            kod_produk VARCHAR(100) NOT NULL,
            produk_info TEXT NOT NULL,
            unit_masuk_total INT NOT NULL DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cawangan_produk (cawangan, kod_produk)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS department_monthly_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cawangan VARCHAR(255) NOT NULL,
            kod_produk VARCHAR(100) NOT NULL,
            produk_info TEXT NOT NULL,
            unit_masuk INT NOT NULL DEFAULT 0,
            log_year INT NOT NULL,
            log_month INT NOT NULL,
            log_date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_cawangan (cawangan),
            INDEX idx_year_month (log_year, log_month),
            INDEX idx_log_date (log_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $hasLogDate = false;
    $result = $conn->query("SHOW COLUMNS FROM department_monthly_log LIKE 'log_date'");
    if ($result) {
        $hasLogDate = $result->num_rows > 0;
        $result->free();
    }
    if (!$hasLogDate) {
        $conn->query("ALTER TABLE department_monthly_log ADD COLUMN log_date DATE NULL AFTER log_month");
        $conn->query("UPDATE department_monthly_log SET log_date = STR_TO_DATE(CONCAT(log_year, '-', LPAD(log_month, 2, '0'), '-01'), '%Y-%m-%d') WHERE log_date IS NULL");
        $conn->query("ALTER TABLE department_monthly_log MODIFY log_date DATE NOT NULL");
        $conn->query("ALTER TABLE department_monthly_log ADD INDEX idx_log_date (log_date)");
    }

    return $conn;
}

function getAllDepartments(): array
{
    return [
        "ADMIN & ENF",
        "CC",
        "CC,SS,LB,VAL,PLAN",
        "CMS",
        "CMS, PLANNING & VALUATION",
        "CMS, SRVY COMP, SS",
        "CMS, SVY COMP, SVI, PLAN, VAL",
        "LB & REG",
        "REG,OSC,LB",
        "REV & LAND TITLE",
        "REV/REG",
        "STENO & CC",
        "SUPT & VAL",
        "SVY,REG,ENF",
        "VAL & COMP.",
    ];
}

// ── Input ─────────────────────────────────────────────────────────────────────
$currentYear  = (int) date('Y');
$currentMonth = (int) date('n');

$selDept  = trim($_GET['dept'] ?? '');
$selYear  = max(2020, min(2040, (int) ($_GET['year'] ?? $currentYear)));
$viewMode = ($_GET['view'] ?? 'year') === 'month' ? 'month' : 'year';
$selMonth = max(1, min(12, (int) ($_GET['month'] ?? $currentMonth)));

$deptConn    = getDeptConn();
$departments = getAllDepartments();

$monthlyData = [];
$monthNames  = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$fullMonthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
$allProducts = [];
$departmentTotals = [];

if ($deptConn) {
    if ($selDept !== '') {
        $stmt = $deptConn->prepare(
            "SELECT log_month, kod_produk, produk_info, SUM(unit_masuk) AS total
             FROM department_monthly_log
             WHERE cawangan = ? AND log_year = ?
             GROUP BY log_month, kod_produk, produk_info
             ORDER BY log_month ASC, kod_produk ASC"
        );
        $stmt->bind_param("si", $selDept, $selYear);
    } else {
        $stmt = $deptConn->prepare(
            "SELECT log_month, kod_produk, produk_info, SUM(unit_masuk) AS total
             FROM department_monthly_log
             WHERE log_year = ?
             GROUP BY log_month, kod_produk, produk_info
             ORDER BY log_month ASC, kod_produk ASC"
        );
        $stmt->bind_param("i", $selYear);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $m = (int) $r['log_month'];
        $k = $r['kod_produk'];
        $monthlyData[$m][$k] = ($monthlyData[$m][$k] ?? 0) + (int) $r['total'];
        $allProducts[$k] = $r['produk_info'];
    }
    $stmt->close();

    if ($selDept !== '') {
        $deptStmt = $deptConn->prepare(
            "SELECT cawangan, SUM(unit_masuk) AS total
             FROM department_monthly_log
             WHERE cawangan = ? AND log_year = ?
             GROUP BY cawangan
             ORDER BY cawangan ASC"
        );
        $deptStmt->bind_param("si", $selDept, $selYear);
    } else {
        $deptStmt = $deptConn->prepare(
            "SELECT cawangan, SUM(unit_masuk) AS total
             FROM department_monthly_log
             WHERE log_year = ?
             GROUP BY cawangan
             ORDER BY cawangan ASC"
        );
        $deptStmt->bind_param("i", $selYear);
    }

    $deptStmt->execute();
    $deptRes = $deptStmt->get_result();
    while ($d = $deptRes->fetch_assoc()) {
        $departmentTotals[$d['cawangan']] = (int) $d['total'];
    }
    $deptStmt->close();
}

// ── Yearly totals per product ──────────────────────────────────────────────
$yearlyTotals = [];
foreach ($allProducts as $kp => $info) {
    $sum = 0;
    for ($m = 1; $m <= 12; $m++) {
        $sum += $monthlyData[$m][$kp] ?? 0;
    }
    $yearlyTotals[$kp] = $sum;
}
$grandTotal = array_sum($yearlyTotals);

// ── Month totals across products ───────────────────────────────────────────
$monthTotals = [];
for ($m = 1; $m <= 12; $m++) {
    $monthTotals[$m] = array_sum($monthlyData[$m] ?? []);
}

// ── Busiest month ─────────────────────────────────────────────────────────
$busiestMonth = 0;
$busiestVal   = 0;
for ($m = 1; $m <= 12; $m++) {
    if ($monthTotals[$m] > $busiestVal) {
        $busiestVal   = $monthTotals[$m];
        $busiestMonth = $m;
    }
}

// ── Active months count ───────────────────────────────────────────────────
$activeMonths = count(array_filter($monthTotals, fn($v) => $v > 0));

// ── Selected-month breakdown ──────────────────────────────────────────────
$selMonthData  = $monthlyData[$selMonth] ?? [];
$selMonthTotal = array_sum($selMonthData);

// ── Day-level calendar data ───────────────────────────────────────────────
$dayData = [];
if ($deptConn && $viewMode === 'month') {
    if ($selDept !== '') {
        $stmt2 = $deptConn->prepare(
            "SELECT DAY(log_date) AS log_day, SUM(unit_masuk) AS total
             FROM department_monthly_log
             WHERE cawangan = ? AND log_year = ? AND log_month = ?
             GROUP BY log_day
             ORDER BY log_day ASC"
        );
        $stmt2->bind_param("sii", $selDept, $selYear, $selMonth);
    } else {
        $stmt2 = $deptConn->prepare(
            "SELECT DAY(log_date) AS log_day, SUM(unit_masuk) AS total
             FROM department_monthly_log
             WHERE log_year = ? AND log_month = ?
             GROUP BY log_day
             ORDER BY log_day ASC"
        );
        $stmt2->bind_param("ii", $selYear, $selMonth);
    }

    $stmt2->execute();
    $res2 = $stmt2->get_result();
    while ($rd = $res2->fetch_assoc()) {
        $dayData[(int) $rd['log_day']] = (int) $rd['total'];
    }
    $stmt2->close();
}

if ($deptConn) $deptConn->close();

function heatClass(int $val, int $max): string {
    if ($val === 0 || $max === 0) return 'heat-0';
    $pct = $val / $max;
    if ($pct >= 0.75) return 'heat-4';
    if ($pct >= 0.5)  return 'heat-3';
    if ($pct >= 0.25) return 'heat-2';
    return 'heat-1';
}

$maxMonth = max(1, max($monthTotals));
$maxDay   = max(1, $dayData ? max($dayData) : 1);
$pageScope = $selDept !== '' ? $selDept : 'All Departments';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Department Toner Usage Report, TonerMS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <style>
    :root {
      --blue:#3b82f6; --blue-dark:#2563eb; --blue-light:#eff6ff;
      --indigo:#6366f1; --indigo-light:#eef2ff;
      --green:#22c55e; --green-light:#f0fdf4;
      --red:#ef4444;
      --amber:#f59e0b; --amber-light:#fffbeb;
      --gray-50:#f9fafb; --gray-100:#f3f4f6; --gray-200:#e5e7eb;
      --gray-400:#9ca3af; --gray-600:#4b5563; --gray-800:#1f2937;
      --radius:12px;
      --shadow:0 1px 4px rgba(0,0,0,.08),0 4px 16px rgba(0,0,0,.06);
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Inter',system-ui,sans-serif;background:var(--gray-50);color:var(--gray-800);}

    .top-nav{background:#fff;border-bottom:1px solid var(--gray-200);padding:0 32px;display:flex;align-items:center;gap:8px;height:56px;position:sticky;top:0;z-index:100;box-shadow:0 1px 4px rgba(0,0,0,.06);}
    .top-nav .nav-brand{font-weight:700;font-size:1rem;color:var(--blue-dark);text-decoration:none;margin-right:24px;display:flex;align-items:center;gap:7px;}
    .top-nav a.nav-link-btn{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:8px;font-size:.85rem;font-weight:500;color:var(--gray-600);text-decoration:none;transition:background .15s,color .15s;}
    .top-nav a.nav-link-btn:hover,.top-nav a.nav-link-btn.active{background:var(--blue);color:#fff;}
    .top-nav a.nav-link-btn svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;}

    .main-wrapper{max-width:1200px;margin:0 auto;padding:32px 20px 60px;}
    .page-header{margin-bottom:24px;}
    .page-header h1{font-size:1.5rem;font-weight:700;}
    .page-header p{color:var(--gray-400);font-size:.88rem;margin-top:4px;}

    .filter-bar{background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);padding:20px 24px;margin-bottom:24px;display:flex;flex-wrap:wrap;gap:16px;align-items:flex-end;}
    .filter-bar .fg{display:flex;flex-direction:column;gap:5px;min-width:180px;}
    .filter-bar label{font-size:.78rem;font-weight:600;color:var(--gray-600);}
    .filter-bar select,.filter-bar input[type=number]{padding:8px 12px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:.87rem;color:var(--gray-800);background:var(--gray-50);outline:none;transition:border-color .15s;}
    .filter-bar select:focus,.filter-bar input[type=number]:focus{border-color:var(--blue);background:#fff;}
    .view-toggle{display:flex;gap:0;border:1.5px solid var(--gray-200);border-radius:8px;overflow:hidden;}
    .view-toggle a{padding:8px 18px;font-size:.85rem;font-weight:600;color:var(--gray-600);text-decoration:none;background:#fff;transition:background .15s,color .15s;}
    .view-toggle a.active{background:var(--blue);color:#fff;}
    .btn-apply{background:var(--blue);color:#fff;border:none;border-radius:8px;padding:8px 22px;font-size:.88rem;font-weight:600;cursor:pointer;transition:background .15s;white-space:nowrap;align-self:flex-end;}
    .btn-apply:hover{background:var(--blue-dark);}

    .stat-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;}
    @media(max-width:860px){.stat-row{grid-template-columns:repeat(2,1fr);}}
    @media(max-width:460px){.stat-row{grid-template-columns:1fr;}}
    .stat-card{background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);padding:20px 22px;display:flex;flex-direction:column;gap:6px;}
    .stat-card .lbl{font-size:.72rem;font-weight:700;color:var(--gray-400);text-transform:uppercase;letter-spacing:.6px;}
    .stat-card .val{font-size:2rem;font-weight:800;line-height:1;}
    .stat-card .sub{font-size:.75rem;color:var(--gray-400);}
    .stat-card.blue .val{color:var(--blue);}
    .stat-card.green .val{color:var(--green);}
    .stat-card.amber .val{color:var(--amber);}
    .stat-card.indigo .val{color:var(--indigo);}

    .cal-wrap,.breakdown-wrap{background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);padding:28px;margin-bottom:24px;}
    .cal-wrap h2,.breakdown-wrap h2{font-size:1.05rem;font-weight:700;margin-bottom:4px;}
    .cal-sub,.bd-sub{font-size:.82rem;color:var(--gray-400);margin-bottom:20px;}

    .year-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;}
    @media(max-width:900px){.year-grid{grid-template-columns:repeat(3,1fr);}}
    @media(max-width:640px){.year-grid{grid-template-columns:repeat(2,1fr);}}
    .month-tile{border:1.5px solid var(--gray-200);border-radius:10px;padding:16px 14px;cursor:pointer;transition:border-color .2s,box-shadow .2s;text-decoration:none;display:block;color:inherit;}
    .month-tile:hover{border-color:var(--blue);box-shadow:0 0 0 3px rgba(59,130,246,.12);}
    .month-tile.has-data{border-color:#bfdbfe;background:var(--blue-light);}
    .month-tile.current{border-color:var(--blue);box-shadow:0 0 0 3px rgba(59,130,246,.18);}
    .month-tile .mt-name{font-size:.8rem;font-weight:700;color:var(--gray-600);text-transform:uppercase;letter-spacing:.4px;margin-bottom:8px;}
    .month-tile .mt-total{font-size:1.5rem;font-weight:800;line-height:1;color:var(--blue);}
    .month-tile .mt-label{font-size:.7rem;color:var(--gray-400);margin-top:3px;}
    .month-tile.heat-0 .mt-total{color:var(--gray-400);}
    .month-tile.heat-1{background:#eff6ff;border-color:#bfdbfe;} .month-tile.heat-1 .mt-total{color:#3b82f6;}
    .month-tile.heat-2{background:#dbeafe;border-color:#93c5fd;} .month-tile.heat-2 .mt-total{color:#2563eb;}
    .month-tile.heat-3{background:#bfdbfe;border-color:#60a5fa;} .month-tile.heat-3 .mt-total{color:#1d4ed8;}
    .month-tile.heat-4{background:#93c5fd;border-color:#3b82f6;} .month-tile.heat-4 .mt-total{color:#1e40af;}
    .mt-products{margin-top:10px;display:flex;flex-direction:column;gap:4px;}
    .mt-prod-row{display:flex;justify-content:space-between;font-size:.71rem;color:var(--gray-600);}
    .mt-prod-row span:last-child{font-weight:700;color:var(--gray-800);}

    .month-nav{display:flex;align-items:center;gap:12px;margin-bottom:16px;}
    .month-nav .mn-title{font-size:1.1rem;font-weight:700;flex:1;}
    .month-nav a{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;border:1.5px solid var(--gray-200);text-decoration:none;color:var(--gray-600);font-size:.9rem;transition:all .15s;}
    .month-nav a:hover{background:var(--blue);color:#fff;border-color:var(--blue);}
    .cal-grid-header{display:grid;grid-template-columns:repeat(7,1fr);gap:4px;margin-bottom:4px;}
    .cal-day-name{text-align:center;font-size:.7rem;font-weight:700;color:var(--gray-400);text-transform:uppercase;padding:4px 0;}
    .cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:4px;}
    .cal-day{min-height:70px;border:1.5px solid var(--gray-200);border-radius:8px;padding:8px 6px;display:flex;flex-direction:column;gap:4px;background:#fff;transition:border-color .15s;}
    .cal-day:hover{border-color:#93c5fd;}
    .cal-day.empty{background:transparent;border-color:transparent;}
    .cal-day.today{border-color:var(--blue);box-shadow:0 0 0 2px rgba(59,130,246,.2);}
    .cal-day .cd-num{font-size:.75rem;font-weight:700;color:var(--gray-400);}
    .cal-day.today .cd-num{color:var(--blue);}
    .cal-day .cd-badge{font-size:.8rem;font-weight:800;color:var(--blue);}
    .cal-day .cd-unit{font-size:.65rem;color:var(--gray-400);}
    .cal-day.heat-1{background:#eff6ff;border-color:#bfdbfe;}
    .cal-day.heat-2{background:#dbeafe;border-color:#93c5fd;}
    .cal-day.heat-3{background:#bfdbfe;border-color:#60a5fa;}
    .cal-day.heat-4{background:#93c5fd;border-color:#3b82f6;}

    .bd-table{width:100%;border-collapse:collapse;font-size:.85rem;}
    .bd-table thead th{background:var(--gray-50);color:var(--gray-600);font-weight:600;padding:10px 14px;border-bottom:2px solid var(--gray-200);white-space:nowrap;}
    .bd-table tbody td{padding:9px 14px;border-bottom:1px solid var(--gray-100);vertical-align:middle;}
    .bd-table tbody tr:last-child td{border-bottom:none;}
    .bd-table tbody tr:hover td{background:#f0f7ff;}
    .td-right{text-align:right;}
    .total-row td{background:var(--blue-light) !important;font-weight:700;color:var(--blue-dark);}
    .badge-pill{display:inline-flex;align-items:center;justify-content:center;background:var(--blue-light);color:var(--blue-dark);font-weight:700;font-size:.75rem;padding:3px 10px;border-radius:99px;}

    .empty-state{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:60px 20px;gap:12px;color:var(--gray-400);}
    .empty-state svg{width:48px;height:48px;stroke:var(--gray-200);fill:none;stroke-width:1.5;}
    .empty-state p{font-size:.9rem;}

    .heat-legend{display:flex;align-items:center;gap:8px;font-size:.75rem;color:var(--gray-600);margin-top:16px;flex-wrap:wrap;}
    .hl-box{width:14px;height:14px;border-radius:3px;display:inline-block;}
  </style>
</head>
<body>

<nav class="top-nav">
  <a href="index.php" class="nav-brand">
    <svg viewBox="0 0 24 24" style="width:20px;height:20px;stroke:#3b82f6;fill:none;stroke-width:2;stroke-linecap:round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
    TonerMS
  </a>
  <a href="index.php" class="nav-link-btn">
    <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
    Inventory Control
  </a>
  <a href="department.php" class="nav-link-btn active">
    <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
    Department Reports
  </a>
</nav>

<section class="main-wrapper">
  <div class="page-header">
    <h1>Department Toner Usage Report</h1>
    <p>Review toner quantities issued to departments by month, year, product, and usage date.</p>
  </div>

  <form method="GET" action="department.php">
    <div class="filter-bar">
      <div class="fg" style="min-width:240px;">
        <label for="dept">Department / Branch</label>
        <select id="dept" name="dept">
          <option value="">All departments</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= htmlspecialchars($d, ENT_QUOTES) ?>" <?= $selDept === $d ? 'selected' : '' ?>>
              <?= htmlspecialchars($d) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="fg" style="min-width:100px;">
        <label for="year">Reporting Year</label>
        <input type="number" id="year" name="year" min="2020" max="2040" value="<?= $selYear ?>">
      </div>

      <div class="fg">
        <label>Report View</label>
        <div class="view-toggle">
          <a href="?dept=<?= urlencode($selDept) ?>&year=<?= $selYear ?>&view=year&month=<?= $selMonth ?>" class="<?= $viewMode === 'year' ? 'active' : '' ?>">Annual</a>
          <a href="?dept=<?= urlencode($selDept) ?>&year=<?= $selYear ?>&view=month&month=<?= $selMonth ?>" class="<?= $viewMode === 'month' ? 'active' : '' ?>">Monthly</a>
        </div>
      </div>

      <?php if ($viewMode === 'month'): ?>
      <div class="fg" style="min-width:140px;">
        <label for="month">Reporting Month</label>
        <select id="month" name="month">
          <?php for ($mi = 1; $mi <= 12; $mi++): ?>
            <option value="<?= $mi ?>" <?= $selMonth === $mi ? 'selected' : '' ?>>
              <?= $fullMonthNames[$mi-1] ?>
            </option>
          <?php endfor; ?>
        </select>
      </div>
      <?php else: ?>
        <input type="hidden" name="month" value="<?= $selMonth ?>">
      <?php endif; ?>

      <input type="hidden" name="view" value="<?= $viewMode ?>">
      <button type="submit" class="btn-apply">Apply Filter</button>
    </div>
  </form>

  <div class="stat-row">
    <div class="stat-card blue">
      <div class="lbl">Total Issued <?= $selYear ?></div>
      <div class="val"><?= number_format($grandTotal) ?></div>
      <div class="sub">toner units</div>
    </div>
    <div class="stat-card green">
      <div class="lbl">Active Reporting Months</div>
      <div class="val"><?= $activeMonths ?></div>
      <div class="sub">months with recorded usage</div>
    </div>
    <div class="stat-card amber">
      <div class="lbl">Highest Usage Month</div>
      <div class="val"><?= $busiestMonth > 0 ? $monthNames[$busiestMonth-1] : '—' ?></div>
      <div class="sub"><?= $busiestVal > 0 ? $busiestVal . ' units issued' : 'No usage recorded' ?></div>
    </div>
    <div class="stat-card indigo">
      <div class="lbl">Departments Tracked</div>
      <div class="val"><?= count($departmentTotals) ?></div>
      <div class="sub">departments with records</div>
    </div>
  </div>

  <?php if ($viewMode === 'year'): ?>
    <div class="cal-wrap">
      <h2><?= htmlspecialchars($pageScope) ?>, <?= $selYear ?> Usage Overview</h2>
      <p class="cal-sub">Select a month to view the daily usage calendar and detailed product breakdown.</p>

      <?php if ($grandTotal === 0): ?>
        <div class="empty-state">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
          <p>No toner usage records were found for <?= htmlspecialchars($pageScope) ?> in <?= $selYear ?>.</p>
        </div>
      <?php else: ?>
      <div class="year-grid">
        <?php for ($m = 1; $m <= 12; $m++):
          $mTotal = $monthTotals[$m];
          $hc = heatClass($mTotal, $maxMonth);
          $isCurrent = ($selYear === $currentYear && $m === $currentMonth);
          $tileUrl = '?dept=' . urlencode($selDept) . '&year=' . $selYear . '&view=month&month=' . $m;
          $tileClass = 'month-tile ' . $hc . ($mTotal > 0 ? ' has-data' : '') . ($isCurrent ? ' current' : '');
          $mProds = $monthlyData[$m] ?? [];
          arsort($mProds);
          $topProds = array_slice($mProds, 0, 3, true);
        ?>
          <a href="<?= $tileUrl ?>" class="<?= $tileClass ?>">
            <div class="mt-name"><?= $fullMonthNames[$m-1] ?></div>
            <div class="mt-total"><?= $mTotal > 0 ? number_format($mTotal) : '—' ?></div>
            <div class="mt-label"><?= $mTotal > 0 ? 'units issued' : 'no records' ?></div>
            <?php if ($topProds): ?>
            <div class="mt-products">
              <?php foreach ($topProds as $kp => $qty): ?>
                <div class="mt-prod-row">
                  <span><?= htmlspecialchars($kp) ?></span>
                  <span><?= $qty ?></span>
                </div>
              <?php endforeach; ?>
              <?php if (count($mProds) > 3): ?>
                <div class="mt-prod-row" style="color:var(--gray-400);font-style:italic;">
                  <span>+<?= count($mProds) - 3 ?> additional products</span><span></span>
                </div>
              <?php endif; ?>
            </div>
            <?php endif; ?>
          </a>
        <?php endfor; ?>
      </div>

      <div class="heat-legend">
        <span>Usage level:</span>
        <span class="hl-box" style="background:#e5e7eb;border:1px solid #d1d5db;"></span><span>None</span>
        <span class="hl-box" style="background:#eff6ff;border:1px solid #bfdbfe;"></span><span>Low</span>
        <span class="hl-box" style="background:#dbeafe;border:1px solid #93c5fd;"></span><span>Moderate</span>
        <span class="hl-box" style="background:#bfdbfe;border:1px solid #60a5fa;"></span><span>High</span>
        <span class="hl-box" style="background:#93c5fd;border:1px solid #3b82f6;"></span><span>Peak</span>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($departmentTotals): ?>
    <div class="breakdown-wrap">
      <h2>Department Usage Summary, <?= $selYear ?></h2>
      <p class="bd-sub">Total toner quantities issued to each department for the selected reporting year.</p>
      <div style="overflow-x:auto;">
        <table class="bd-table">
          <thead>
            <tr>
              <th>Department / Branch</th>
              <th class="td-right">Total Quantity Issued</th>
            </tr>
          </thead>
          <tbody>
            <?php arsort($departmentTotals); foreach ($departmentTotals as $deptName => $deptTotal): ?>
              <tr>
                <td><?= htmlspecialchars($deptName) ?></td>
                <td class="td-right"><strong><?= number_format($deptTotal) ?></strong></td>
              </tr>
            <?php endforeach; ?>
            <tr class="total-row">
              <td><strong>Overall Total</strong></td>
              <td class="td-right"><?= number_format(array_sum($departmentTotals)) ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($grandTotal > 0): ?>
    <div class="breakdown-wrap">
      <h2>Annual Product Usage Breakdown, <?= $selYear ?></h2>
      <p class="bd-sub">Monthly toner quantities issued by product for <?= htmlspecialchars($pageScope) ?>.</p>
      <div style="overflow-x:auto;">
        <table class="bd-table">
          <thead>
            <tr>
              <th>Product Code</th>
              <th>Product Description</th>
              <?php for ($m = 1; $m <= 12; $m++): ?>
                <th class="td-right"><?= $monthNames[$m-1] ?></th>
              <?php endfor; ?>
              <th class="td-right" style="background:#eff6ff;color:var(--blue-dark);">Annual Total</th>
            </tr>
          </thead>
          <tbody>
            <?php arsort($yearlyTotals); foreach ($yearlyTotals as $kp => $ytotal): ?>
            <tr>
              <td><span class="badge-pill"><?= htmlspecialchars($kp) ?></span></td>
              <td style="max-width:220px;white-space:normal;font-size:.8rem;"><?= htmlspecialchars($allProducts[$kp] ?? '') ?></td>
              <?php for ($m = 1; $m <= 12; $m++): ?>
                <td class="td-right"><?= ($monthlyData[$m][$kp] ?? 0) > 0 ? '<strong>' . number_format($monthlyData[$m][$kp]) . '</strong>' : '<span style="color:var(--gray-400)">—</span>' ?></td>
              <?php endfor; ?>
              <td class="td-right" style="background:#eff6ff;font-weight:800;color:var(--blue-dark);"><?= number_format($ytotal) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
              <td colspan="2"><strong>Overall Total, All Products</strong></td>
              <?php for ($m = 1; $m <= 12; $m++): ?>
                <td class="td-right"><?= $monthTotals[$m] > 0 ? number_format($monthTotals[$m]) : '—' ?></td>
              <?php endfor; ?>
              <td class="td-right"><?= number_format($grandTotal) ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

  <?php else: ?>
    <?php
      $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $selMonth, $selYear);
      $firstDow = (int) date('w', mktime(0,0,0,$selMonth,1,$selYear));
      $prevMonth = $selMonth - 1; $prevYear = $selYear;
      if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
      $nextMonth = $selMonth + 1; $nextYear = $selYear;
      if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
      $prevUrl = '?dept=' . urlencode($selDept) . '&year=' . $prevYear . '&view=month&month=' . $prevMonth;
      $nextUrl = '?dept=' . urlencode($selDept) . '&year=' . $nextYear . '&view=month&month=' . $nextMonth;
    ?>
    <div class="cal-wrap">
      <div class="month-nav">
        <a href="<?= $prevUrl ?>" title="Previous reporting month">&#8592;</a>
        <div class="mn-title">
          <?= $fullMonthNames[$selMonth-1] ?> <?= $selYear ?>
          <span style="font-size:.8rem;font-weight:400;color:var(--gray-400);margin-left:8px;"><?= htmlspecialchars($pageScope) ?></span>
        </div>
        <a href="<?= $nextUrl ?>" title="Next reporting month">&#8594;</a>
      </div>
      <p class="cal-sub">Each calendar date shows the total toner quantity issued on that day.</p>

      <div class="cal-grid-header">
        <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dn): ?>
          <div class="cal-day-name"><?= $dn ?></div>
        <?php endforeach; ?>
      </div>

      <div class="cal-grid">
        <?php for ($e = 0; $e < $firstDow; $e++): ?>
          <div class="cal-day empty"></div>
        <?php endfor; ?>

        <?php for ($day = 1; $day <= $daysInMonth; $day++):
          $units = $dayData[$day] ?? 0;
          $hc = heatClass($units, $maxDay);
          $isToday = ($selYear === $currentYear && $selMonth === $currentMonth && $day === (int) date('j'));
          $cellClass = 'cal-day ' . ($units > 0 ? $hc : '') . ($isToday ? ' today' : '');
        ?>
          <div class="<?= trim($cellClass) ?>">
            <div class="cd-num"><?= $day ?></div>
            <?php if ($units > 0): ?>
              <div class="cd-badge"><?= number_format($units) ?></div>
              <div class="cd-unit">unit<?= $units !== 1 ? 's' : '' ?> issued</div>
            <?php endif; ?>
          </div>
        <?php endfor; ?>
      </div>

      <div class="heat-legend" style="margin-top:20px;">
        <span>Usage level:</span>
        <span class="hl-box" style="background:#fff;border:1px solid #e5e7eb;"></span><span>None</span>
        <span class="hl-box" style="background:#eff6ff;border:1px solid #bfdbfe;"></span><span>Low</span>
        <span class="hl-box" style="background:#dbeafe;border:1px solid #93c5fd;"></span><span>Moderate</span>
        <span class="hl-box" style="background:#bfdbfe;border:1px solid #60a5fa;"></span><span>High</span>
        <span class="hl-box" style="background:#93c5fd;border:1px solid #3b82f6;"></span><span>Peak</span>
      </div>
    </div>

    <div class="breakdown-wrap">
      <h2><?= $fullMonthNames[$selMonth-1] ?> <?= $selYear ?> Product Usage Breakdown</h2>
      <p class="bd-sub">
        Total toner quantity issued for <strong><?= htmlspecialchars($pageScope) ?></strong> during this reporting month:
        <strong style="color:var(--blue);"><?= number_format($selMonthTotal) ?> units</strong>
      </p>

      <?php if (empty($selMonthData)): ?>
        <div class="empty-state" style="padding:30px;">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
          <p>No toner usage records were found for this reporting month.</p>
        </div>
      <?php else: arsort($selMonthData); ?>
        <div style="overflow-x:auto;">
          <table class="bd-table">
            <thead>
              <tr>
                <th>Product Code</th>
                <th>Product Description</th>
                <th class="td-right">Issued This Month</th>
                <th class="td-right">Year-to-Date Issued</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($selMonthData as $kp => $qty): ?>
              <tr>
                <td><span class="badge-pill"><?= htmlspecialchars($kp) ?></span></td>
                <td style="max-width:260px;white-space:normal;font-size:.8rem;"><?= htmlspecialchars($allProducts[$kp] ?? '') ?></td>
                <td class="td-right"><strong><?= number_format($qty) ?></strong></td>
                <td class="td-right" style="color:var(--gray-600);"><?= number_format($yearlyTotals[$kp] ?? 0) ?></td>
              </tr>
              <?php endforeach; ?>
              <tr class="total-row">
                <td colspan="2"><strong>Monthly Total</strong></td>
                <td class="td-right"><?= number_format($selMonthTotal) ?></td>
                <td class="td-right"><?= number_format($grandTotal) ?> <span style="font-size:.75rem;font-weight:400;">year-to-date</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div style="text-align:center;margin-top:8px;">
      <a href="?dept=<?= urlencode($selDept) ?>&year=<?= $selYear ?>&view=year"
         style="display:inline-flex;align-items:center;gap:6px;color:var(--blue);font-weight:600;font-size:.88rem;text-decoration:none;">
        ← Back to <?= $selYear ?> Annual Overview
      </a>
    </div>
  <?php endif; ?>

</section>
</body>
</html>