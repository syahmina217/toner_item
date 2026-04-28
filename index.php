<?php
$success_msg = '';
$error_msg   = '';
$restock_msg = '';
$edit_row    = null;
$manage_selected = '';
$manage_in = 0;
$manage_out = 0;
$dept_selected = '';
$dept_selected_kod = '';
$dept_unit_masuk = 0;

function ensureMovementColumns(mysqli $conn): void
{
    $hasUnitMasuk = false;
    $hasUnitKeluar = false;

    $result = $conn->query("SHOW COLUMNS FROM toner LIKE 'UnitMasuk'");
    if ($result) {
        $hasUnitMasuk = $result->num_rows > 0;
        $result->free();
    }

    $result = $conn->query("SHOW COLUMNS FROM toner LIKE 'UnitKeluar'");
    if ($result) {
        $hasUnitKeluar = $result->num_rows > 0;
        $result->free();
    }

    if (!$hasUnitMasuk) {
        $conn->query("ALTER TABLE toner ADD COLUMN UnitMasuk INT NOT NULL DEFAULT 0");
    }
    if (!$hasUnitKeluar) {
        $conn->query("ALTER TABLE toner ADD COLUMN UnitKeluar INT NOT NULL DEFAULT 0");
    }
}

function getDepartmentSeedList(): array
{
    return [
        "CMS, SVY COMP, SVI, PLAN, VAL",
        "REV & LAND TITLE",
        "CC",
        "SVY,REG,ENF",
        "VAL & COMP.",
        "REG,OSC,LB",
        "REV/REG",
        "LB & REG",
        "ADMIN & ENF",
        "SUPT & VAL",
        "STENO & CC",
        "CC,SS,LB,VAL,PLAN",
        "CMS, SRVY COMP, SS",
        "CMS, PLANNING & VALUATION",
        "CMS",
    ];
}

function ensureDepartmentTable(): ?mysqli
{
    $servername = "localhost";
    $username   = "root";
    $password   = "";

    $bootstrap = new mysqli($servername, $username, $password);
    if ($bootstrap->connect_error) {
        return null;
    }

    $bootstrap->query("CREATE DATABASE IF NOT EXISTS department");
    $bootstrap->close();

    $deptConn = new mysqli($servername, $username, $password, "department");
    if ($deptConn->connect_error) {
        return null;
    }

    $deptConn->query(
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

    // Ensure monthly log table exists
    $deptConn->query(
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
    $result = $deptConn->query("SHOW COLUMNS FROM department_monthly_log LIKE 'log_date'");
    if ($result) {
        $hasLogDate = $result->num_rows > 0;
        $result->free();
    }

    if (!$hasLogDate) {
        $deptConn->query("ALTER TABLE department_monthly_log ADD COLUMN log_date DATE NULL AFTER log_month");
        $deptConn->query("UPDATE department_monthly_log SET log_date = STR_TO_DATE(CONCAT(log_year, '-', LPAD(log_month, 2, '0'), '-01'), '%Y-%m-%d') WHERE log_date IS NULL");
        $deptConn->query("ALTER TABLE department_monthly_log MODIFY log_date DATE NOT NULL");
        $deptConn->query("ALTER TABLE department_monthly_log ADD INDEX idx_log_date (log_date)");
    }

    return $deptConn;
}

// ── Handle Add Product form submission ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_product') {

    $servername = "localhost";
    $username   = "root";
    $password   = "";
    $dbname     = "toner_item";

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        $error_msg = "Connection failed: " . $conn->connect_error;
    } else {
        ensureMovementColumns($conn);
        $kodProduk = trim($_POST['kod_produk']);
        $harga     = floatval($_POST['harga']);
        $produkInfo = trim($_POST['produk_info']);
        $jumlah    = intval($_POST['jumlah']);

        $stmt = $conn->prepare("INSERT INTO toner (KodProduk, Harga, ProdukInfo, Jumlah, UnitMasuk, UnitKeluar) VALUES (?, ?, ?, ?, 0, 0)");
        $stmt->bind_param("sdsi", $kodProduk, $harga, $produkInfo, $jumlah);

        if ($stmt->execute()) {
            $success_msg = "Product <strong>" . htmlspecialchars($kodProduk) . "</strong> has been added to the inventory.";
        } else {
            $error_msg = "Error inserting product: " . $stmt->error;
        }

        $stmt->close();
        $conn->close();
    }
}

// ── Handle Delete Product ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_product') {

    $servername = "localhost";
    $username   = "root";
    $password   = "";
    $dbname     = "toner_item";

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        $error_msg = "Connection failed: " . $conn->connect_error;
    } else {
        ensureMovementColumns($conn);
        $kodProduk = trim($_POST['kod_produk'] ?? '');
        if ($kodProduk !== '') {
            $stmt = $conn->prepare("DELETE FROM toner WHERE KodProduk = ?");
            $stmt->bind_param("s", $kodProduk);
            if ($stmt->execute()) {
                $success_msg = "Product <strong>" . htmlspecialchars($kodProduk) . "</strong> has been removed from the inventory.";
            } else {
                $error_msg = "Error deleting product: " . $stmt->error;
            }
            $stmt->close();
        }
        $conn->close();
    }
}

// ── Handle Update Product (edit) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_product') {

    $servername = "localhost";
    $username   = "root";
    $password   = "";
    $dbname     = "toner_item";

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        $error_msg = "Connection failed: " . $conn->connect_error;
    } else {
        ensureMovementColumns($conn);
        $kodProduk  = trim($_POST['kod_produk'] ?? '');
        $harga      = floatval($_POST['harga'] ?? 0);
        $produkInfo = trim($_POST['produk_info'] ?? '');
        $jumlah     = intval($_POST['jumlah'] ?? 0);

        $check = $conn->prepare("SELECT KodProduk FROM toner WHERE KodProduk = ?");
        $check->bind_param("s", $kodProduk);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$exists) {
            $error_msg = "The selected product could not be found.";
            $edit_row = [
                'KodProduk'  => $kodProduk,
                'Harga'      => $harga,
                'ProdukInfo' => $produkInfo,
                'Jumlah'     => $jumlah,
            ];
        } elseif ($produkInfo === '') {
            $error_msg = "Product description is required.";
            $edit_row = [
                'KodProduk'  => $kodProduk,
                'Harga'      => $harga,
                'ProdukInfo' => $produkInfo,
                'Jumlah'     => $jumlah,
            ];
        } else {
            $upd = $conn->prepare("UPDATE toner SET Harga = ?, ProdukInfo = ?, Jumlah = ? WHERE KodProduk = ?");
            $upd->bind_param("dsis", $harga, $produkInfo, $jumlah, $kodProduk);
            if ($upd->execute()) {
                $success_msg = "Product <strong>" . htmlspecialchars($kodProduk) . "</strong> has been updated successfully.";
                $sel = $conn->prepare("SELECT KodProduk, Harga, ProdukInfo, Jumlah FROM toner WHERE KodProduk = ?");
                $sel->bind_param("s", $kodProduk);
                $sel->execute();
                $edit_row = $sel->get_result()->fetch_assoc();
                $sel->close();
            } else {
                $error_msg = "Error updating product: " . $upd->error;
                $edit_row = [
                    'KodProduk'  => $kodProduk,
                    'Harga'      => $harga,
                    'ProdukInfo' => $produkInfo,
                    'Jumlah'     => $jumlah,
                ];
            }
            $upd->close();
        }
        $conn->close();
    }
}

// ── Handle Manage Stock (add / remove) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'manage_stock') {

    $servername = "localhost";
    $username   = "root";
    $password   = "";
    $dbname     = "toner_item";

    $manage_selected = trim($_POST['manage_kod_produk'] ?? '');
    $manage_in  = max(0, intval($_POST['unit_masuk'] ?? 0));
    $manage_out = max(0, intval($_POST['unit_keluar'] ?? 0));

    if ($manage_selected === '') {
        $error_msg = "Please select a toner product.";
    } elseif ($manage_in === 0 && $manage_out === 0) {
        $error_msg = "Please enter a stock-in or stock-out quantity.";
    } else {
        $conn = new mysqli($servername, $username, $password, $dbname);
        if ($conn->connect_error) {
            $error_msg = "Connection failed: " . $conn->connect_error;
        } else {
            ensureMovementColumns($conn);
            $stmt = $conn->prepare("SELECT Jumlah FROM toner WHERE KodProduk = ?");
            $stmt->bind_param("s", $manage_selected);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$row) {
                $error_msg = "Selected product not found.";
            } else {
                $current = intval($row['Jumlah']);
                $newTotal = $current + $manage_in - $manage_out;

                if ($newTotal < 0) {
                    $restock_msg = "Stock alert: <span class=\"restock-item-red\">" . htmlspecialchars($manage_selected) . "</span> does not have enough quantity for this stock-out request.";
                } else {
                    $upd = $conn->prepare("UPDATE toner SET Jumlah = ?, UnitMasuk = ?, UnitKeluar = ? WHERE KodProduk = ?");
                    $upd->bind_param("iiis", $newTotal, $manage_in, $manage_out, $manage_selected);
                    if ($upd->execute()) {
                        $success_msg = "Stock movement recorded for <strong>" . htmlspecialchars($manage_selected) . "</strong>. Current available quantity: <strong>" . $newTotal . "</strong>.";
                        $manage_in = 0;
                        $manage_out = 0;
                    } else {
                        $error_msg = "Error updating stock: " . $upd->error;
                    }
                    $upd->close();
                }
            }
            $conn->close();
        }
    }
}

// ── Handle Department Borrow Toner (separate DB: department) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'department_stock_in') {

    $dept_selected     = trim($_POST['dept_cawangan'] ?? '');
    $dept_selected_kod = trim($_POST['dept_kod_produk'] ?? '');
    $dept_unit_masuk   = max(0, intval($_POST['dept_unit_masuk'] ?? 0));
    $dept_log_date     = trim($_POST['dept_log_date'] ?? date('Y-m-d'));

    $dateObj = DateTime::createFromFormat('Y-m-d', $dept_log_date);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $dept_log_date) {
        $dept_log_date = date('Y-m-d');
        $dateObj = new DateTime($dept_log_date);
    }

    $logYear  = (int) $dateObj->format('Y');
    $logMonth = (int) $dateObj->format('n');

    if ($dept_selected === '' || $dept_selected_kod === '' || $dept_unit_masuk <= 0) {
        $error_msg = "Please complete all department usage fields with valid information.";
    } else {
        $servername = "localhost";
        $username   = "root";
        $password   = "";

        $mainConn = new mysqli($servername, $username, $password, "toner_item");
        $produkInfoForDept = '';

        if ($mainConn->connect_error) {
            $error_msg = "Connection failed: " . $mainConn->connect_error;
            goto skip_dept_insert;
        }

        ensureMovementColumns($mainConn);

        $pi = $mainConn->prepare("SELECT ProdukInfo, Jumlah FROM toner WHERE KodProduk = ?");
        $pi->bind_param("s", $dept_selected_kod);
        $pi->execute();
        $piRow = $pi->get_result()->fetch_assoc();
        $pi->close();

        if (!$piRow) {
            $error_msg = "The selected toner product could not be found.";
            $mainConn->close();
            goto skip_dept_insert;
        }

        $produkInfoForDept = $piRow['ProdukInfo'];
        $currentJumlah = intval($piRow['Jumlah']);

        if ($currentJumlah - $dept_unit_masuk < 0) {
            $restock_msg = "Stock alert: <span class=\"restock-item-red\">" . htmlspecialchars($dept_selected_kod) . "</span> does not have enough quantity available for this department request.";
            $mainConn->close();
            goto skip_dept_insert;
        }

        $newJumlah = $currentJumlah - $dept_unit_masuk;
        $updMain = $mainConn->prepare("UPDATE toner SET Jumlah = ?, UnitKeluar = ? WHERE KodProduk = ?");
        $updMain->bind_param("iis", $newJumlah, $dept_unit_masuk, $dept_selected_kod);

        if (!$updMain->execute()) {
            $error_msg = "Error updating main inventory: " . $updMain->error;
            $updMain->close();
            $mainConn->close();
            goto skip_dept_insert;
        }

        $updMain->close();
        $mainConn->close();

        $deptConn = ensureDepartmentTable();
        if ($deptConn) {
            $ins = $deptConn->prepare(
                "INSERT INTO department_stock_item (cawangan, kod_produk, produk_info, unit_masuk_total)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                     unit_masuk_total = unit_masuk_total + VALUES(unit_masuk_total),
                     produk_info = VALUES(produk_info)"
            );
            $ins->bind_param("sssi", $dept_selected, $dept_selected_kod, $produkInfoForDept, $dept_unit_masuk);

            if ($ins->execute()) {
                $log = $deptConn->prepare(
                    "INSERT INTO department_monthly_log (cawangan, kod_produk, produk_info, unit_masuk, log_year, log_month, log_date)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $log->bind_param("sssiiis", $dept_selected, $dept_selected_kod, $produkInfoForDept, $dept_unit_masuk, $logYear, $logMonth, $dept_log_date);
                $log->execute();
                $log->close();

                $success_msg = "Department toner usage has been recorded for <strong>" . htmlspecialchars($dept_selected) . "</strong> - <strong>" . htmlspecialchars($dept_selected_kod) . "</strong> ({$dept_unit_masuk} units) on <strong>" . htmlspecialchars($dept_log_date) . "</strong>.";
                $dept_selected = '';
                $dept_selected_kod = '';
                $dept_unit_masuk = 0;
            } else {
                $error_msg = "Unable to save department usage record: " . $ins->error;
            }

            $ins->close();
            $deptConn->close();
        } else {
            $error_msg = "Could not connect to department database.";
        }

        skip_dept_insert:;
    }
}

// ── Handle Delete Department Stock Item ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_department_item') {

    $deleteDeptId = intval($_POST['dept_item_id'] ?? 0);

    if ($deleteDeptId <= 0) {
        $error_msg = "Invalid department record selected.";
    } else {
        $deptConn = ensureDepartmentTable();
        if ($deptConn) {
            $stmt = $deptConn->prepare("DELETE FROM department_stock_item WHERE id = ?");
            $stmt->bind_param("i", $deleteDeptId);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $success_msg = "The department usage summary has been removed successfully.";
                } else {
                    $error_msg = "The selected department record could not be found.";
                }
            } else {
                $error_msg = "Unable to delete department record: " . $stmt->error;
            }
            $stmt->close();
            $deptConn->close();
        } else {
            $error_msg = "Could not connect to department database.";
        }
    }
}

// ── Load edit row if ?edit= param ──
if (isset($_GET['edit']) && $_GET['edit'] !== '') {
    $editKod = trim($_GET['edit']);
    $servername = "localhost";
    $username   = "root";
    $password   = "";
    $dbname     = "toner_item";
    $conn = new mysqli($servername, $username, $password, $dbname);
    if (!$conn->connect_error) {
        ensureMovementColumns($conn);
        $sel = $conn->prepare("SELECT KodProduk, Harga, ProdukInfo, Jumlah FROM toner WHERE KodProduk = ?");
        $sel->bind_param("s", $editKod);
        $sel->execute();
        $edit_row = $sel->get_result()->fetch_assoc();
        $sel->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Toner Inventory Management</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <style>
    :root {
      --blue: #3b82f6;
      --blue-dark: #2563eb;
      --red: #ef4444;
      --green: #22c55e;
      --gray-50: #f9fafb;
      --gray-100: #f3f4f6;
      --gray-200: #e5e7eb;
      --gray-400: #9ca3af;
      --gray-600: #4b5563;
      --gray-800: #1f2937;
      --radius: 12px;
      --shadow: 0 1px 4px rgba(0,0,0,.08), 0 4px 16px rgba(0,0,0,.06);
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', system-ui, sans-serif; background: var(--gray-50); color: var(--gray-800); }

    /* ── NAV ── */
    .top-nav {
      background: #fff;
      border-bottom: 1px solid var(--gray-200);
      padding: 0 32px;
      display: flex;
      align-items: center;
      gap: 8px;
      height: 56px;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 1px 4px rgba(0,0,0,.06);
    }
    .top-nav .nav-brand {
      font-weight: 700;
      font-size: 1rem;
      color: var(--blue-dark);
      text-decoration: none;
      margin-right: 24px;
      display: flex;
      align-items: center;
      gap: 7px;
    }
    .top-nav a.nav-link-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 14px;
      border-radius: 8px;
      font-size: .85rem;
      font-weight: 500;
      color: var(--gray-600);
      text-decoration: none;
      transition: background .15s, color .15s;
    }
    .top-nav a.nav-link-btn:hover,
    .top-nav a.nav-link-btn.active {
      background: var(--blue);
      color: #fff;
    }
    .top-nav a.nav-link-btn svg { width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round; }

    /* ── MAIN ── */
    .main-wrapper { max-width: 1100px; margin: 0 auto; padding: 32px 20px 60px; }
    .page-header { margin-bottom: 28px; }
    .page-header h1 { font-size: 1.55rem; font-weight: 700; color: var(--gray-800); }
    .page-header p  { color: var(--gray-400); font-size: .9rem; margin-top: 4px; }

    /* ── ALERTS ── */
    .alert { padding: 12px 18px; border-radius: 10px; font-size: .9rem; margin-bottom: 18px; }
    .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
    .alert-error   { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
    .alert-warning { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
    .restock-item-red { color: var(--red); font-weight: 700; }

    /* ── CARDS ── */
    .cards-row { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 28px; }
    @media(max-width:760px){ .cards-row { grid-template-columns: 1fr; } }
    .insert-card { background: #fff; border-radius: var(--radius); box-shadow: var(--shadow); padding: 28px; }
    .insert-card h2 { font-size: 1.05rem; font-weight: 700; margin-bottom: 6px; color: var(--gray-800); }
    .insert-card h3 { font-size: 1rem; font-weight: 700; margin-bottom: 16px; color: var(--gray-800); }
    .card-desc { font-size: .83rem; color: var(--gray-400); margin-bottom: 20px; }
    .manage-card { display: flex; flex-direction: column; }

    /* ── FORMS ── */
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .form-grid .full-width { grid-column: 1 / -1; }
    .manage-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .manage-grid .full-width { grid-column: 1 / -1; }
    .form-group { display: flex; flex-direction: column; gap: 5px; }
    .form-group label { font-size: .8rem; font-weight: 600; color: var(--gray-600); }
    .form-group input,
    .form-group select,
    .form-group textarea {
      padding: 9px 12px;
      border: 1.5px solid var(--gray-200);
      border-radius: 8px;
      font-size: .88rem;
      color: var(--gray-800);
      background: var(--gray-50);
      transition: border-color .15s;
      outline: none;
      width: 100%;
    }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus { border-color: var(--blue); background: #fff; }
    .form-group textarea { resize: vertical; min-height: 70px; }
    .required { color: var(--red); }
    .form-actions { display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap; }
    .btn-submit {
      display: inline-flex; align-items: center; gap: 7px;
      background: var(--blue); color: #fff;
      border: none; border-radius: 8px;
      padding: 9px 20px; font-size: .88rem; font-weight: 600;
      cursor: pointer; transition: background .15s;
    }
    .btn-submit:hover { background: var(--blue-dark); }
    .btn-reset {
      background: var(--gray-100); color: var(--gray-600);
      border: 1.5px solid var(--gray-200); border-radius: 8px;
      padding: 9px 18px; font-size: .88rem; font-weight: 500;
      cursor: pointer; transition: background .15s;
    }
    .btn-reset:hover { background: var(--gray-200); }
    .btn-secondary-link {
      display: inline-flex; align-items: center;
      color: var(--gray-600); font-size: .88rem;
      text-decoration: none;
      padding: 9px 14px;
      border-radius: 8px;
      border: 1.5px solid var(--gray-200);
      transition: background .15s;
    }
    .btn-secondary-link:hover { background: var(--gray-100); }

    /* ── TABLE ── */
    .inventory-table-wrap { background: #fff; border-radius: var(--radius); box-shadow: var(--shadow); padding: 28px; margin-bottom: 28px; }
    .inventory-table-wrap h2 { font-size: 1.05rem; font-weight: 700; margin-bottom: 4px; }
    .table-sub { font-size: .82rem; color: var(--gray-400); margin-bottom: 18px; }
    .table-responsive { overflow-x: auto; }
    .table { width: 100%; border-collapse: collapse; font-size: .85rem; }
    .table thead th { background: var(--gray-50); color: var(--gray-600); font-weight: 600; padding: 10px 14px; border-bottom: 2px solid var(--gray-200); white-space: nowrap; }
    .table tbody td { padding: 10px 14px; border-bottom: 1px solid var(--gray-100); vertical-align: middle; }
    .table tbody tr:last-child td { border-bottom: none; }
    .table tbody tr:hover td { background: #f0f7ff; }
    .col-num { text-align: right; }
    .col-actions { text-align: center; width: 100px; }
    .restock-badge { background: #fef2f2; color: var(--red); font-size: .75rem; font-weight: 700; padding: 3px 8px; border-radius: 99px; border: 1px solid #fecaca; }
    .restock-row td { background: #fff8f8 !important; }
    .table-actions { display: flex; gap: 6px; justify-content: center; }
    .btn-icon-action {
      display: inline-flex; align-items: center; justify-content: center;
      width: 32px; height: 32px; border-radius: 7px;
      border: 1.5px solid var(--gray-200); background: var(--gray-50);
      cursor: pointer; transition: all .15s; text-decoration: none;
    }
    .btn-icon-action svg { width: 14px; height: 14px; stroke: var(--gray-600); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
    .btn-icon-action:hover { background: #eff6ff; border-color: var(--blue); }
    .btn-icon-action:hover svg { stroke: var(--blue); }
    .btn-icon-action.danger:hover { background: #fef2f2; border-color: var(--red); }
    .btn-icon-action.danger:hover svg { stroke: var(--red); }

    /* ── DEPT FORM ── */
    .dept-controls .dept-grid { display: grid; grid-template-columns: 1fr 1fr 150px 170px auto; gap: 16px; align-items: flex-end; }
    @media(max-width:900px){ .dept-controls .dept-grid { grid-template-columns: 1fr 1fr; } }
    @media(max-width:560px){ .dept-controls .dept-grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body>

<!-- Top Nav -->
<nav class="top-nav">
  <a href="index.php" class="nav-brand">
    <svg viewBox="0 0 24 24" style="width:20px;height:20px;stroke:#3b82f6;fill:none;stroke-width:2;stroke-linecap:round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
    TonerMS
  </a>
  <a href="index.php" class="nav-link-btn active">
    <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
    Inventory Control
  </a>
  <a href="department.php" class="nav-link-btn">
    <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
    Department Reports
  </a>
</nav>

<section class="main-wrapper">
  <div class="page-header">
    <h1>Toner Inventory Control</h1>
    <p>Manage toner stock levels, record inventory movements, and monitor departmental toner usage.</p>
  </div>

  <?php if ($success_msg): ?>
    <div class="alert alert-success"><?= $success_msg ?></div>
  <?php endif; ?>
  <?php if ($error_msg): ?>
    <div class="alert alert-error"><?= $error_msg ?></div>
  <?php endif; ?>
  <?php if ($restock_msg): ?>
    <div class="alert alert-warning"><?= $restock_msg ?></div>
  <?php endif; ?>

  <div class="cards-row">
    <?php if ($edit_row): ?>
      <!-- ── Edit Product Card ── -->
      <div class="insert-card">
        <h2>Update Product Details</h2>
        <p class="card-desc">Review and update the details for <strong><?= htmlspecialchars($edit_row['KodProduk']) ?></strong>.</p>
        <form method="POST" action="index.php">
          <input type="hidden" name="action" value="update_product">
          <input type="hidden" name="kod_produk" value="<?= htmlspecialchars($edit_row['KodProduk'], ENT_QUOTES, 'UTF-8') ?>">
          <div class="form-grid">
            <div class="form-group">
              <label>Product Code</label>
              <input type="text" value="<?= htmlspecialchars($edit_row['KodProduk'], ENT_QUOTES, 'UTF-8') ?>" disabled>
            </div>
            <div class="form-group">
              <label for="harga_edit">Unit Price (RM) <span class="required">*</span></label>
              <input type="number" id="harga_edit" name="harga" step="0.01" min="0" required value="<?= htmlspecialchars(number_format((float)$edit_row['Harga'], 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-group full-width">
              <label for="produk_info_edit">Product Description <span class="required">*</span></label>
              <textarea id="produk_info_edit" name="produk_info" required><?= htmlspecialchars($edit_row['ProdukInfo'], ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div class="form-group">
              <label for="jumlah_edit">Available Quantity <span class="required">*</span></label>
              <input type="number" id="jumlah_edit" name="jumlah" min="0" required value="<?= (int) $edit_row['Jumlah'] ?>">
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn-submit">Save Changes</button>
            <a href="index.php" class="btn-secondary-link">← Back to Product Registration</a>
          </div>
        </form>
      </div>
    <?php else: ?>
      <!-- ── Insert Stock Card ── -->
      <div class="insert-card">
        <h2>
          <svg style="width:18px;height:18px;vertical-align:-2px;margin-right:6px;stroke:#3b82f6;fill:none;stroke-width:2.5;stroke-linecap:round" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Register New Toner Item
        </h2>
        <p class="card-desc">Enter the toner information below to register a new inventory item.</p>
        <form method="POST" action="index.php">
          <input type="hidden" name="action" value="add_product">
          <div class="form-grid">
            <div class="form-group">
              <label for="kod_produk">Product Code <span class="required">*</span></label>
              <input type="text" id="kod_produk" name="kod_produk" placeholder="e.g. TNR-001" required maxlength="50">
            </div>
            <div class="form-group">
              <label for="harga">Unit Price (RM) <span class="required">*</span></label>
              <input type="number" id="harga" name="harga" placeholder="e.g. 45.00" step="0.01" min="0" required>
            </div>
            <div class="form-group full-width">
              <label for="produk_info">Product Description <span class="required">*</span></label>
              <textarea id="produk_info" name="produk_info" placeholder="e.g. HP 85A Black Toner Cartridge – compatible with LaserJet P1102w" required></textarea>
            </div>
            <div class="form-group">
              <label for="jumlah">Opening Quantity <span class="required">*</span></label>
              <input type="number" id="jumlah" name="jumlah" placeholder="e.g. 10" min="0" required>
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn-submit">
              <svg style="width:15px;height:15px;stroke:#fff;fill:none;stroke-width:2.5;stroke-linecap:round" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
              Register Product
            </button>
            <button type="reset" class="btn-reset">Clear Form</button>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <!-- ── Manage stock card ── -->
    <div class="insert-card manage-card">
      <h3>Record Stock Movement</h3>
      <form method="POST" action="index.php">
        <input type="hidden" name="action" value="manage_stock">
        <div class="manage-grid">
          <div class="form-group full-width">
            <label for="manage_kod_produk">Toner Product <span class="required">*</span></label>
            <select id="manage_kod_produk" name="manage_kod_produk" required>
              <option value="" disabled <?= $manage_selected === '' ? 'selected' : '' ?>>Select a toner product</option>
              <?php
              $servername = "localhost";
              $username = "root";
              $password = "";
              $dbname = "toner_item";
              $conn = new mysqli($servername, $username, $password, $dbname);
              if (!$conn->connect_error) {
                  $res = $conn->query("SELECT KodProduk, ProdukInfo FROM toner ORDER BY KodProduk ASC");
                  if ($res) {
                      while ($p = $res->fetch_assoc()) {
                          $kodOpt = $p['KodProduk'];
                          $label = $p['KodProduk'] . " — " . $p['ProdukInfo'];
                          $selectedAttr = ($manage_selected !== '' && $manage_selected === $kodOpt) ? 'selected' : '';
                          echo '<option value="' . htmlspecialchars($kodOpt, ENT_QUOTES, 'UTF-8') . '" ' . $selectedAttr . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
                      }
                      $res->free();
                  }
                  $conn->close();
              }
              ?>
            </select>
          </div>
          <div class="form-group">
            <label for="unit_masuk">Stock In Quantity</label>
            <input type="number" id="unit_masuk" name="unit_masuk" min="0" value="<?= (int) $manage_in ?>">
          </div>
          <div class="form-group">
            <label for="unit_keluar">Stock Out Quantity</label>
            <input type="number" id="unit_keluar" name="unit_keluar" min="0" value="<?= (int) $manage_out ?>">
          </div>
        </div>
        <div class="form-actions" style="margin-top:18px;">
          <button type="submit" class="btn-submit" style="width:100%;justify-content:center;">Save Stock Movement</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Product inventory table -->
  <div class="inventory-table-wrap">
    <h2>Current Toner Inventory</h2>
    <p class="table-sub">A complete overview of toner items, available quantities, and inventory movement records.</p>
    <div class="table-responsive">
      <table class="table table-striped table-bordered">
        <thead class="table-light">
          <tr>
            <th>Product Code</th>
            <th>Product Description</th>
            <th class="col-num">Unit Price</th>
            <th class="col-num">Available Quantity</th>
            <th class="col-num">Stock Status</th>
            <th class="col-num">Stock In</th>
            <th class="col-num">Stock Out</th>
            <th class="col-actions">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $servername = "localhost";
          $username = "root";
          $password = "";
          $dbname = "toner_item";
          $conn = new mysqli($servername, $username, $password, $dbname);
          if ($conn->connect_error) {
              die("Connection failed: " . $conn->connect_error);
          }
          ensureMovementColumns($conn);
          $sql = "SELECT KodProduk, Harga, ProdukInfo, Jumlah, UnitMasuk, UnitKeluar FROM toner ORDER BY KodProduk ASC";
          $result = $conn->query($sql);
          if (!$result) {
              die("Invalid query: " . $conn->error);
          }
          while ($row = $result->fetch_assoc()) {
              $kod = htmlspecialchars($row["KodProduk"], ENT_QUOTES, 'UTF-8');
              $info = htmlspecialchars($row["ProdukInfo"], ENT_QUOTES, 'UTF-8');
              $harga = htmlspecialchars(number_format((float) $row["Harga"], 2, '.', ''), ENT_QUOTES, 'UTF-8');
              $jumlah = htmlspecialchars((string) (int) $row["Jumlah"], ENT_QUOTES, 'UTF-8');
              $jumlahInt = (int) $row["Jumlah"];
              $unitMasuk = htmlspecialchars((string) (int) ($row["UnitMasuk"] ?? 0), ENT_QUOTES, 'UTF-8');
              $unitKeluar = htmlspecialchars((string) (int) ($row["UnitKeluar"] ?? 0), ENT_QUOTES, 'UTF-8');
              $restockHtml = $jumlahInt <= 0 ? '<span class="restock-badge">Restock Required</span>' : '';
              $rowClass = $jumlahInt <= 0 ? ' class="restock-row"' : '';
              $kodRaw = $row["KodProduk"];
              $editHref = 'index.php?edit=' . rawurlencode($kodRaw);
              echo '<tr' . $rowClass . '>
                  <td>' . $kod . '</td>
                  <td>' . $info . '</td>
                  <td class="col-num">RM ' . $harga . '</td>
                  <td class="col-num"><strong>' . $jumlah . '</strong></td>
                  <td class="col-num">' . $restockHtml . '</td>
                  <td class="col-num">' . $unitMasuk . '</td>
                  <td class="col-num">' . $unitKeluar . '</td>
                  <td class="col-actions">
                    <div class="table-actions">
                      <a href="' . htmlspecialchars($editHref, ENT_QUOTES, 'UTF-8') . '" class="btn-icon-action" title="Edit item" aria-label="Edit">
                        <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                      </a>
                      <form method="post" action="index.php" style="display:inline;margin:0;" onsubmit="return confirm(\'Remove this toner product from inventory?\');">
                        <input type="hidden" name="action" value="delete_product">
                        <input type="hidden" name="kod_produk" value="' . htmlspecialchars($kodRaw, ENT_QUOTES, 'UTF-8') . '">
                        <button type="submit" class="btn-icon-action danger" title="Delete item" aria-label="Delete">
                          <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                        </button>
                      </form>
                    </div>
                  </td>
              </tr>';
          }
          $conn->close();
          ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Department borrowing toner input -->
  <div class="inventory-table-wrap dept-controls">
    <h2>Department Toner Usage Entry</h2>
    <p class="table-sub">Record toner usage for each department. Each submission updates the department report, reduces the available inventory quantity, and supports automatic monthly and yearly usage summaries.</p>
    <form method="POST" action="index.php" class="dept-grid">
      <input type="hidden" name="action" value="department_stock_in">
      <div class="form-group">
        <label for="dept_cawangan">Department / Branch</label>
        <select id="dept_cawangan" name="dept_cawangan" required>
          <option value="" disabled <?= $dept_selected === '' ? 'selected' : '' ?>>Select a department</option>
          <?php
          $seedDepartments = getDepartmentSeedList();
          sort($seedDepartments);
          foreach ($seedDepartments as $deptName) {
              $selectedAttr = ($dept_selected !== '' && $dept_selected === $deptName) ? 'selected' : '';
              echo '<option value="' . htmlspecialchars($deptName, ENT_QUOTES, 'UTF-8') . '" ' . $selectedAttr . '>' . htmlspecialchars($deptName, ENT_QUOTES, 'UTF-8') . '</option>';
          }
          ?>
        </select>
      </div>
      <div class="form-group">
        <label for="dept_kod_produk">Toner Product</label>
        <select id="dept_kod_produk" name="dept_kod_produk" required>
          <option value="" disabled <?= $dept_selected_kod === '' ? 'selected' : '' ?>>Select a toner product</option>
          <?php
          $servername = "localhost";
          $username = "root";
          $password = "";
          $dbname = "toner_item";
          $conn = new mysqli($servername, $username, $password, $dbname);
          if (!$conn->connect_error) {
              $res = $conn->query("SELECT KodProduk, ProdukInfo FROM toner ORDER BY KodProduk ASC");
              if ($res) {
                  while ($p = $res->fetch_assoc()) {
                      $kodOpt = $p['KodProduk'];
                      $label = $p['KodProduk'] . " — " . $p['ProdukInfo'];
                      $selectedAttr = ($dept_selected_kod !== '' && $dept_selected_kod === $kodOpt) ? 'selected' : '';
                      echo '<option value="' . htmlspecialchars($kodOpt, ENT_QUOTES, 'UTF-8') . '" ' . $selectedAttr . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
                  }
                  $res->free();
              }
              $conn->close();
          }
          ?>
        </select>
      </div>
      <div class="form-group">
        <label for="dept_unit_masuk">Quantity Issued</label>
        <input type="number" id="dept_unit_masuk" name="dept_unit_masuk" min="1" value="<?= (int) $dept_unit_masuk ?>" required>
      </div>
      <div class="form-group">
        <label for="dept_log_date">Issue Date</label>
        <input type="date" id="dept_log_date" name="dept_log_date"
               value="<?= date('Y-m-d') ?>"
               max="<?= date('Y-m-d') ?>"
               required
               style="padding:9px 12px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:.88rem;background:var(--gray-50);outline:none;width:100%;transition:border-color .15s;"
               onfocus="this.style.borderColor='var(--blue)'" onblur="this.style.borderColor='var(--gray-200)'">
      </div>
      <div class="form-group">
        <button type="submit" class="btn-submit">Save Department Usage</button>
      </div>
    </form>
  </div>

  <!-- Department totals table -->
  <div class="inventory-table-wrap">
    <h2>Department Usage Summary</h2>
    <p class="table-sub">Total toner quantities issued to each department by product.
      <a href="department.php" style="margin-left:10px;color:var(--blue);font-weight:600;text-decoration:none;">
        View Monthly and Yearly Report →
      </a>
    </p>
    <div class="table-responsive">
      <table class="table table-striped table-bordered">
        <thead class="table-light">
          <tr>
            <th>Department / Branch</th>
            <th>Product Code</th>
            <th>Product Description</th>
            <th class="col-num">Total Quantity Issued</th>
            <th class="col-actions">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $deptConn = ensureDepartmentTable();
          if ($deptConn) {
              $deptRes = $deptConn->query("SELECT id, cawangan, kod_produk, produk_info, unit_masuk_total FROM department_stock_item ORDER BY cawangan ASC, kod_produk ASC");
              if ($deptRes) {
                  while ($d = $deptRes->fetch_assoc()) {
                      $deptId = (int) $d['id'];
                      echo '<tr>
                          <td>' . htmlspecialchars($d['cawangan'], ENT_QUOTES, 'UTF-8') . '</td>
                          <td>' . htmlspecialchars($d['kod_produk'], ENT_QUOTES, 'UTF-8') . '</td>
                          <td>' . htmlspecialchars($d['produk_info'], ENT_QUOTES, 'UTF-8') . '</td>
                          <td class="col-num"><strong>' . (int) $d['unit_masuk_total'] . '</strong></td>
                          <td class="col-actions">
                            <div class="table-actions">
                              <form method="post" action="index.php" style="display:inline;margin:0;" onsubmit="return confirm(\'Remove this department usage summary?\');">
                                <input type="hidden" name="action" value="delete_department_item">
                                <input type="hidden" name="dept_item_id" value="' . $deptId . '">
                                <button type="submit" class="btn-icon-action danger" title="Delete department item" aria-label="Delete">
                                  <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                </button>
                              </form>
                            </div>
                          </td>
                      </tr>';
                  }
                  $deptRes->free();
              }
              $deptConn->close();
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

</body>
</html>