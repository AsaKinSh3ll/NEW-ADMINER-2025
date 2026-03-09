<?php
/**
 * SimpleAdmin — PHP Database Manager
 * Single-file MySQL/MariaDB admin tool · v2.0.0
 */

session_start();
error_reporting(0);
define('VERSION', '2.0.0');

// ── Helpers ──────────────────────────────────────────────────────────────────
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function redirect($params = []) {
    $q = http_build_query(array_filter($params, fn($v) => $v !== null && $v !== false));
    header('Location: ?' . $q);
    exit;
}

function flash($msg, $type = 'success') { $_SESSION['flash'] = ['msg' => $msg, 'type' => $type]; }
function getFlash() { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }

// ── DB Connection ─────────────────────────────────────────────────────────────
$pdo = null; $dbError = null;
if (isset($_SESSION['db'])) {
    $d = $_SESSION['db'];
    try {
        $dsn = "mysql:host={$d['host']};port={$d['port']};charset=utf8mb4";
        if (!empty($d['name'])) $dsn .= ";dbname={$d['name']}";
        $pdo = new PDO($dsn, $d['user'], $d['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Exception $e) {
        $dbError = $e->getMessage();
        unset($_SESSION['db']);
        $pdo = null;
    }
}

// ── Route Params ──────────────────────────────────────────────────────────────
$action = $_REQUEST['action'] ?? '';
$db     = $_GET['db']     ?? ($_SESSION['db']['name'] ?? '');
$table  = $_GET['table']  ?? '';
$view   = $_GET['view']   ?? 'browse'; // browse | structure | insert | edit | export

// ── Actions ───────────────────────────────────────────────────────────────────

// LOGIN
if ($action === 'login') {
    $host = trim($_POST['host'] ?? 'localhost');
    $port = (int)($_POST['port'] ?? 3306);
    $user = trim($_POST['user'] ?? '');
    $pass = $_POST['pass'] ?? '';
    $name = trim($_POST['db'] ?? '');
    try {
        $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
        if ($name) $dsn .= ";dbname=$name";
        new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $_SESSION['db'] = compact('host','port','user','pass','name');
        redirect(['db' => $name]);
    } catch (Exception $e) { $loginError = $e->getMessage(); }
}

// LOGOUT
if ($action === 'logout') { session_destroy(); redirect(); }

// USE DB
if ($action === 'usedb' && $pdo) {
    $_SESSION['db']['name'] = $db;
    redirect(['db' => $db]);
}

// CREATE DATABASE
if ($action === 'create_db' && $pdo) {
    $newDb = trim($_POST['new_db'] ?? '');
    if ($newDb) {
        try {
            $pdo->exec("CREATE DATABASE `$newDb` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            flash("Database `$newDb` created.");
            redirect(['db' => $newDb]);
        } catch (Exception $e) { flash($e->getMessage(), 'error'); redirect(); }
    }
}

// DROP DATABASE
if ($action === 'drop_db' && $pdo && $db) {
    try {
        $pdo->exec("DROP DATABASE `$db`");
        $_SESSION['db']['name'] = '';
        flash("Database `$db` dropped.");
        redirect();
    } catch (Exception $e) { flash($e->getMessage(), 'error'); redirect(['db' => $db]); }
}

// RENAME TABLE
if ($action === 'rename_table' && $pdo && $db && $table) {
    $newName = trim($_POST['new_name'] ?? '');
    if ($newName) {
        try {
            $pdo->exec("USE `$db`");
            $pdo->exec("RENAME TABLE `$table` TO `$newName`");
            flash("Table renamed to `$newName`.");
            redirect(['db' => $db, 'table' => $newName]);
        } catch (Exception $e) { flash($e->getMessage(), 'error'); redirect(['db' => $db, 'table' => $table]); }
    }
}

// DROP TABLE
if ($action === 'drop_table' && $pdo && $db && $table) {
    try {
        $pdo->exec("USE `$db`");
        $pdo->exec("DROP TABLE `$table`");
        flash("Table `$table` dropped.");
        redirect(['db' => $db]);
    } catch (Exception $e) { flash($e->getMessage(), 'error'); redirect(['db' => $db, 'table' => $table]); }
}

// TRUNCATE TABLE
if ($action === 'truncate_table' && $pdo && $db && $table) {
    try {
        $pdo->exec("USE `$db`");
        $pdo->exec("TRUNCATE TABLE `$table`");
        flash("Table `$table` truncated.");
    } catch (Exception $e) { flash($e->getMessage(), 'error'); }
    redirect(['db' => $db, 'table' => $table]);
}

// DELETE ROW
if ($action === 'delete' && $pdo && $db && $table) {
    $pdo->exec("USE `$db`");
    try {
        $pdo->exec("DELETE FROM `$table` WHERE " . $_POST['where'] . " LIMIT 1");
        flash('Row deleted.');
    } catch (Exception $e) { flash($e->getMessage(), 'error'); }
    redirect(['db' => $db, 'table' => $table, 'page' => $_GET['page'] ?? 1]);
}

// INSERT ROW
if ($action === 'insert' && $pdo && $db && $table) {
    $pdo->exec("USE `$db`");
    $cols = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll();
    $fields = []; $placeholders = []; $values = [];
    foreach ($cols as $col) {
        if ($col['Extra'] === 'auto_increment') continue;
        $fields[]       = "`{$col['Field']}`";
        $placeholders[] = '?';
        $val = $_POST['col_' . $col['Field']] ?? null;
        $values[] = ($val === '' || $val === null) ? null : $val;
    }
    try {
        $sql  = "INSERT INTO `$table` (" . implode(',', $fields) . ") VALUES (" . implode(',', $placeholders) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        flash('Row inserted. ID: ' . $pdo->lastInsertId());
        redirect(['db' => $db, 'table' => $table]);
    } catch (Exception $e) { flash($e->getMessage(), 'error'); redirect(['db' => $db, 'table' => $table, 'view' => 'insert']); }
}

// UPDATE ROW
if ($action === 'update' && $pdo && $db && $table) {
    $pdo->exec("USE `$db`");
    $cols  = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll();
    $priCol = null;
    foreach ($cols as $c) if ($c['Key'] === 'PRI') { $priCol = $c['Field']; break; }
    $sets = []; $values = [];
    foreach ($cols as $col) {
        if ($col['Key'] === 'PRI') continue;
        $sets[]   = "`{$col['Field']}` = ?";
        $val = $_POST['col_' . $col['Field']] ?? null;
        $values[] = ($val === '' || $val === null) ? null : $val;
    }
    if ($priCol && $sets) {
        $values[] = $_POST['pk_val'];
        try {
            $sql  = "UPDATE `$table` SET " . implode(', ', $sets) . " WHERE `$priCol` = ?";
            $pdo->prepare($sql)->execute($values);
            flash('Row updated.');
            redirect(['db' => $db, 'table' => $table]);
        } catch (Exception $e) { flash($e->getMessage(), 'error'); redirect(['db' => $db, 'table' => $table, 'view' => 'edit', 'pk' => $_POST['pk_val']]); }
    }
}

// CREATE TABLE
if ($action === 'create_table' && $pdo && $db) {
    $pdo->exec("USE `$db`");
    $tName = trim($_POST['tname'] ?? '');
    $cNames = $_POST['cname'] ?? [];
    $cTypes = $_POST['ctype'] ?? [];
    $cNull  = $_POST['cnull'] ?? [];
    if ($tName && $cNames) {
        $defs = ["`id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY"];
        foreach ($cNames as $i => $cn) {
            if (!trim($cn)) continue;
            $nullable = in_array($i, $cNull) ? 'NULL' : 'NOT NULL';
            $defs[] = "`" . trim($cn) . "` " . ($cTypes[$i] ?? 'VARCHAR(255)') . " $nullable";
        }
        try {
            $pdo->exec("CREATE TABLE `$tName` (" . implode(', ', $defs) . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            flash("Table `$tName` created.");
            redirect(['db' => $db, 'table' => $tName]);
        } catch (Exception $e) { flash($e->getMessage(), 'error'); redirect(['db' => $db, 'view' => 'create_table']); }
    }
}

// ADD COLUMN
if ($action === 'add_column' && $pdo && $db && $table) {
    $pdo->exec("USE `$db`");
    $cName = trim($_POST['cname'] ?? '');
    $cType = trim($_POST['ctype'] ?? 'VARCHAR(255)');
    $cNull = ($_POST['cnull'] ?? '') === '1' ? 'NULL' : 'NOT NULL';
    $cDef  = trim($_POST['cdefault'] ?? '');
    if ($cName) {
        try {
            $defStr = $cDef !== '' ? " DEFAULT " . $pdo->quote($cDef) : '';
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$cName` $cType $cNull$defStr");
            flash("Column `$cName` added.");
        } catch (Exception $e) { flash($e->getMessage(), 'error'); }
    }
    redirect(['db' => $db, 'table' => $table, 'view' => 'structure']);
}

// DROP COLUMN
if ($action === 'drop_column' && $pdo && $db && $table) {
    $pdo->exec("USE `$db`");
    $col = $_POST['col'] ?? '';
    try {
        $pdo->exec("ALTER TABLE `$table` DROP COLUMN `$col`");
        flash("Column `$col` dropped.");
    } catch (Exception $e) { flash($e->getMessage(), 'error'); }
    redirect(['db' => $db, 'table' => $table, 'view' => 'structure']);
}

// SQL QUERY
$queryResult = null; $queryError = null; $querySQL = '';
if ($action === 'query' && $pdo) {
    $querySQL = trim($_POST['sql'] ?? '');
    if ($db) $pdo->exec("USE `$db`");
    try {
        $stmt = $pdo->query($querySQL);
        if ($stmt && $stmt->columnCount() > 0) {
            $meta = [];
            for ($i = 0; $i < $stmt->columnCount(); $i++) $meta[] = $stmt->getColumnMeta($i)['name'];
            $queryResult = ['meta' => $meta, 'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
        } else {
            flash('Query OK. Affected rows: ' . ($stmt ? $stmt->rowCount() : 0));
            redirect(['db' => $db, 'table' => $table]);
        }
    } catch (Exception $e) { $queryError = $e->getMessage(); }
}

// EXPORT
if ($action === 'export' && $pdo && $db) {
    $pdo->exec("USE `$db`");
    $exportTbl = $table ?: null;
    $exportSQL = "-- SimpleAdmin Export\n-- Database: $db\n-- Date: " . date('Y-m-d H:i:s') . "\n\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    $tblList = $exportTbl ? [$exportTbl] : $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tblList as $tbl) {
        $create = $pdo->query("SHOW CREATE TABLE `$tbl`")->fetch();
        $exportSQL .= "-- Table: $tbl\nDROP TABLE IF EXISTS `$tbl`;\n" . $create['Create Table'] . ";\n\n";
        $rows = $pdo->query("SELECT * FROM `$tbl`")->fetchAll(PDO::FETCH_NUM);
        if ($rows) {
            $cols = $pdo->query("SHOW COLUMNS FROM `$tbl`")->fetchAll(PDO::FETCH_COLUMN);
            $colList = implode(', ', array_map(fn($c) => "`$c`", $cols));
            $exportSQL .= "INSERT INTO `$tbl` ($colList) VALUES\n";
            $vRows = [];
            foreach ($rows as $row) {
                $escaped = array_map(fn($v) => $v === null ? 'NULL' : "'" . addslashes($v) . "'", $row);
                $vRows[] = '  (' . implode(', ', $escaped) . ')';
            }
            $exportSQL .= implode(",\n", $vRows) . ";\n\n";
        }
    }
    $exportSQL .= "SET FOREIGN_KEY_CHECKS=1;\n";
    $fname = ($exportTbl ?: $db) . '_' . date('Ymd_His') . '.sql';
    header('Content-Type: text/plain; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"$fname\"");
    echo $exportSQL;
    exit;
}

// ── Data Fetching ─────────────────────────────────────────────────────────────
$databases = []; $tables = []; $columns = []; $rows = []; $totalRows = 0;
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$search  = trim($_GET['search'] ?? '');
$editRow = null;

if ($pdo) {
    try { $databases = $pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN); } catch(Exception $e) {}
    if ($db) {
        try { $pdo->exec("USE `$db`"); $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN); } catch(Exception $e) {}
    }
    if ($db && $table && in_array($table, $tables)) {
        try {
            $columns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll();
            // Search
            $whereClause = '';
            if ($search) {
                $sConds = [];
                foreach ($columns as $col) $sConds[] = "`{$col['Field']}` LIKE " . $pdo->quote("%$search%");
                $whereClause = ' WHERE ' . implode(' OR ', $sConds);
            }
            $totalRows = $pdo->query("SELECT COUNT(*) FROM `$table`$whereClause")->fetchColumn();
            $offset    = ($page - 1) * $perPage;
            $rows      = $pdo->query("SELECT * FROM `$table`$whereClause LIMIT $perPage OFFSET $offset")->fetchAll();

            // Edit row fetch
            if ($view === 'edit' && isset($_GET['pk'])) {
                $priCol = null;
                foreach ($columns as $c) if ($c['Key'] === 'PRI') { $priCol = $c['Field']; break; }
                if ($priCol) {
                    $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE `$priCol` = ? LIMIT 1");
                    $stmt->execute([$_GET['pk']]);
                    $editRow = $stmt->fetch();
                }
            }
        } catch(Exception $e) {}
    }
}

$totalPages = $totalRows ? (int)ceil($totalRows / $perPage) : 1;
$flash = getFlash();
$title  = $table ? "$table — $db" : ($db ? $db : 'SimpleAdmin');

// Primary key helper
$priCol = null;
foreach ($columns as $c) if ($c['Key'] === 'PRI') { $priCol = $c['Field']; break; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($title) ?> · SimpleAdmin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:ital,wght@0,300;0,400;0,500;0,600;1,400&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0d0e11;--surface:#13151a;--surface2:#181a21;
  --border:#1f2229;--border2:#2a2d37;
  --text:#c8ccd8;--muted:#565b6e;--faint:#3a3d4a;
  --accent:#00d4aa;--accent2:#0099ff;
  --danger:#ff4757;--warn:#ffa502;--success:#2ed573;--purple:#a855f7;
  --mono:'JetBrains Mono',monospace;--head:'Space Mono',monospace;
}
html,body{height:100%;background:var(--bg);color:var(--text);font-family:var(--mono);font-size:13px;line-height:1.6}
a{color:var(--accent);text-decoration:none}a:hover{color:#fff}
/* Layout */
.layout{display:flex;height:100vh;overflow:hidden}
.sidebar{width:230px;min-width:230px;background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden}
.main{flex:1;overflow-y:auto;display:flex;flex-direction:column}
/* Brand */
.brand{padding:16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;flex-shrink:0}
.brand-icon{width:30px;height:30px;background:var(--accent);border-radius:7px;display:flex;align-items:center;justify-content:center;color:#000;font-family:var(--head);font-weight:700;font-size:15px;flex-shrink:0}
.brand-name{font-family:var(--head);font-size:12px;font-weight:700;color:#fff}
.brand-ver{font-size:10px;color:var(--muted)}
/* Sidebar nav */
.nav-section{padding:8px 0;border-bottom:1px solid var(--border);flex-shrink:0}
.nav-label{padding:4px 14px 2px;font-size:10px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:1.5px}
.nav-item{display:flex;align-items:center;gap:8px;padding:5px 14px;color:var(--muted);font-size:12px;transition:all .12s;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.nav-item:hover,.nav-item.active{color:var(--text);background:rgba(255,255,255,.04)}
.nav-item.active{color:var(--accent);border-left:2px solid var(--accent);padding-left:12px}
.nav-dot{width:5px;height:5px;border-radius:50%;background:var(--border2);flex-shrink:0}
.nav-item.active .nav-dot,.nav-item:hover .nav-dot{background:var(--accent)}
.db-list{overflow-y:auto}
.db-list::-webkit-scrollbar{width:3px}.db-list::-webkit-scrollbar-thumb{background:var(--border2);border-radius:2px}
/* Topbar */
.topbar{display:flex;align-items:center;gap:10px;padding:10px 18px;border-bottom:1px solid var(--border);background:var(--surface);flex-shrink:0;flex-wrap:wrap}
.breadcrumb{display:flex;align-items:center;gap:6px;font-size:12px;flex:1;min-width:0}
.breadcrumb span{color:var(--muted)}
/* Tab bar */
.tabs{display:flex;gap:2px;padding:0 18px;background:var(--surface);border-bottom:1px solid var(--border);flex-shrink:0}
.tab{padding:8px 14px;font-size:11px;font-family:var(--mono);color:var(--muted);cursor:pointer;border-bottom:2px solid transparent;transition:all .15s;text-decoration:none;display:inline-flex;align-items:center;gap:5px}
.tab:hover{color:var(--text)}
.tab.active{color:var(--accent);border-bottom-color:var(--accent)}
/* Buttons */
.btn{display:inline-flex;align-items:center;gap:5px;padding:6px 13px;border-radius:5px;font-family:var(--mono);font-size:12px;font-weight:500;cursor:pointer;border:1px solid transparent;transition:all .15s;white-space:nowrap;line-height:1}
.btn-primary{background:var(--accent);color:#000;border-color:var(--accent)}.btn-primary:hover{background:#00f0c0}
.btn-secondary{background:var(--surface2);color:var(--text);border-color:var(--border2)}.btn-secondary:hover{border-color:var(--text)}
.btn-ghost{background:transparent;color:var(--muted);border-color:var(--border2)}.btn-ghost:hover{color:var(--text);border-color:var(--text)}
.btn-danger{background:transparent;color:var(--danger);border-color:var(--danger)}.btn-danger:hover{background:var(--danger);color:#fff}
.btn-warn{background:transparent;color:var(--warn);border-color:var(--warn)}.btn-warn:hover{background:var(--warn);color:#000}
.btn-purple{background:transparent;color:var(--purple);border-color:var(--purple)}.btn-purple:hover{background:var(--purple);color:#fff}
.btn-sm{padding:3px 9px;font-size:11px}
.btn-xs{padding:2px 6px;font-size:10px}
/* Content */
.content{padding:18px;flex:1}
/* Flash */
.flash{padding:9px 14px;margin-bottom:14px;border-radius:6px;font-size:12px;display:flex;align-items:center;gap:8px}
.flash.success{background:rgba(46,213,115,.09);border:1px solid rgba(46,213,115,.25);color:var(--success)}
.flash.error{background:rgba(255,71,87,.09);border:1px solid rgba(255,71,87,.25);color:var(--danger)}
/* Cards */
.card{background:var(--surface);border:1px solid var(--border);border-radius:8px;overflow:hidden;margin-bottom:14px}
.card-header{padding:10px 14px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.card-title{font-family:var(--head);font-size:11px;font-weight:700;color:#fff;letter-spacing:.5px}
.card-body{padding:14px}
/* Table */
.tbl-wrap{overflow-x:auto}
.tbl-wrap::-webkit-scrollbar{height:4px}.tbl-wrap::-webkit-scrollbar-thumb{background:var(--border2);border-radius:2px}
table{width:100%;border-collapse:collapse;font-size:12px}
thead tr{background:rgba(255,255,255,.025)}
th{padding:7px 11px;text-align:left;font-family:var(--head);font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--border);white-space:nowrap}
td{padding:7px 11px;border-bottom:1px solid var(--border);vertical-align:top;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
tr:last-child td{border-bottom:none}
tr:hover td{background:rgba(255,255,255,.02)}
td.null{color:var(--muted);font-style:italic}
td.num{color:var(--accent2)}
td.actions{white-space:nowrap;display:flex;gap:5px;padding:5px 11px;border-bottom:none}
/* SQL Editor */
.sql-editor{width:100%;padding:11px;background:#09090c;border:1px solid var(--border2);border-radius:6px;color:var(--accent);font-family:var(--mono);font-size:12px;line-height:1.7;resize:vertical;min-height:90px;outline:none;transition:border-color .15s}
.sql-editor:focus{border-color:var(--accent)}
/* Forms */
.form-row{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px}
.form-group{display:flex;flex-direction:column;gap:4px;flex:1;min-width:140px}
.form-group.narrow{max-width:180px;flex:none}
.form-label{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;font-weight:600}
.form-input,.form-select,.form-textarea{padding:7px 9px;background:#09090c;border:1px solid var(--border2);border-radius:5px;color:var(--text);font-family:var(--mono);font-size:12px;outline:none;transition:border-color .15s;width:100%}
.form-input:focus,.form-select:focus,.form-textarea:focus{border-color:var(--accent)}
.form-input::placeholder,.form-textarea::placeholder{color:var(--muted)}
.form-select option{background:var(--surface)}
.form-textarea{resize:vertical;min-height:60px;line-height:1.6}
/* Login */
.login-wrap{display:flex;align-items:center;justify-content:center;height:100vh}
.login-box{width:420px;background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:32px}
.login-icon{width:42px;height:42px;background:var(--accent);border-radius:9px;display:flex;align-items:center;justify-content:center;font-family:var(--head);font-size:20px;font-weight:700;color:#000;flex-shrink:0}
.login-title{font-family:var(--head);font-size:18px;color:#fff}
.login-sub{font-size:11px;color:var(--muted)}
.login-error{color:var(--danger);font-size:12px;background:rgba(255,71,87,.08);border:1px solid rgba(255,71,87,.2);border-radius:5px;padding:8px 12px;margin-bottom:14px}
/* Stats */
.stats{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;margin-bottom:14px}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:7px;padding:12px 14px}
.stat-val{font-family:var(--head);font-size:20px;color:#fff;font-weight:700}
.stat-lbl{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-top:1px}
/* Pagination */
.page-btn{padding:3px 9px;border-radius:4px;border:1px solid var(--border2);background:transparent;color:var(--muted);font-family:var(--mono);font-size:11px;text-decoration:none;transition:all .12s}
.page-btn:hover{color:var(--text);border-color:var(--text)}
.page-btn.active{color:var(--accent);border-color:var(--accent);background:rgba(0,212,170,.07)}
/* Tags */
.tag{display:inline-block;padding:1px 6px;border-radius:3px;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;line-height:1.6}
.tag-green{background:rgba(46,213,115,.11);color:var(--success)}
.tag-blue{background:rgba(0,153,255,.11);color:var(--accent2)}
.tag-yellow{background:rgba(255,165,2,.11);color:var(--warn)}
.tag-red{background:rgba(255,71,87,.11);color:var(--danger)}
.tag-gray{background:rgba(100,100,120,.14);color:var(--muted)}
.tag-purple{background:rgba(168,85,247,.11);color:var(--purple)}
/* Section title */
.section-title{font-family:var(--head);font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:1.5px;margin-bottom:10px;padding-bottom:6px;border-bottom:1px solid var(--border)}
/* Search bar */
.search-bar{display:flex;gap:8px;align-items:center;margin-bottom:14px}
.search-input{flex:1;max-width:320px;padding:6px 10px;background:#09090c;border:1px solid var(--border2);border-radius:5px;color:var(--text);font-family:var(--mono);font-size:12px;outline:none;transition:border-color .15s}
.search-input:focus{border-color:var(--accent)}
.search-input::placeholder{color:var(--muted)}
/* Modal overlay */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:100;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal{background:var(--surface);border:1px solid var(--border2);border-radius:10px;padding:24px;width:420px;max-width:95vw}
.modal-title{font-family:var(--head);font-size:13px;color:#fff;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--border)}
.modal-footer{display:flex;justify-content:flex-end;gap:8px;margin-top:16px;padding-top:12px;border-top:1px solid var(--border)}
/* Inline edit highlight */
.editing td{background:rgba(0,212,170,.04) !important}
/* Empty state */
.empty{text-align:center;padding:40px 20px;color:var(--muted)}
.empty-icon{font-size:28px;margin-bottom:10px;opacity:.3}
/* col builder */
.col-builder-row{display:flex;gap:8px;align-items:center;margin-bottom:8px}
.col-builder-row .form-input,.col-builder-row .form-select{flex:1}
kbd{display:inline-block;padding:1px 5px;background:var(--border);border:1px solid var(--border2);border-radius:3px;font-size:10px;color:var(--muted)}
.divider{border:none;border-top:1px solid var(--border);margin:14px 0}
/* Scrollbar global */
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--border2);border-radius:3px}
</style>
</head>
<body>

<?php if (!$pdo): ?>
<!-- ═══════ LOGIN ═══════ -->
<div class="login-wrap">
  <div class="login-box">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:26px">
      <div class="login-icon">S</div>
      <div>
        <div class="login-title">SimpleAdmin</div>
        <div class="login-sub">MySQL / MariaDB Manager &middot; v<?= VERSION ?></div>
      </div>
    </div>
    <?php if (isset($loginError)): ?><div class="login-error">&#9888; <?= h($loginError) ?></div><?php endif; ?>
    <?php if ($dbError): ?><div class="login-error">Session expired: <?= h($dbError) ?></div><?php endif; ?>
    <form method="post" action="?action=login">
      <div class="form-row">
        <div class="form-group" style="flex:2">
          <label class="form-label">Host</label>
          <input class="form-input" name="host" value="localhost" required>
        </div>
        <div class="form-group narrow">
          <label class="form-label">Port</label>
          <input class="form-input" name="port" value="3306">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Username</label>
          <input class="form-input" name="user" value="root" required>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <input class="form-input" name="pass" type="password">
        </div>
      </div>
      <div class="form-group" style="margin-bottom:18px">
        <label class="form-label">Database <span style="color:var(--muted)">(optional)</span></label>
        <input class="form-input" name="db" placeholder="Leave blank to browse all">
      </div>
      <button class="btn btn-primary" style="width:100%;justify-content:center;padding:9px">Connect &rarr;</button>
    </form>
    <div style="margin-top:12px;text-align:center;font-size:11px;color:var(--muted)">No credentials stored server-side</div>
  </div>
</div>

<?php else: ?>
<!-- ═══════ APP ═══════ -->
<div class="layout">

<!-- ── Sidebar ─────────────────────────────────────────────────────────── -->
<aside class="sidebar">
  <div class="brand">
    <div class="brand-icon">S</div>
    <div>
      <div class="brand-name">SimpleAdmin</div>
      <div class="brand-ver">coded by AsaKin1337 [Sindikat777]</div>
      <div class="brand-ver">v<?= VERSION ?> &middot; <?= h($_SESSION['db']['user']) ?>@<?= h($_SESSION['db']['host']) ?></div>
    </div>
  </div>

  <div class="nav-section" style="padding-bottom:6px">
    <div class="nav-label">Databases</div>
    <div class="db-list" style="max-height:180px">
      <?php foreach ($databases as $dbn): ?>
      <a href="?action=usedb&db=<?= urlencode($dbn) ?>" class="nav-item <?= $dbn===$db?'active':'' ?>">
        <span class="nav-dot"></span><?= h($dbn) ?>
      </a>
      <?php endforeach; ?>
    </div>
    <div style="padding:6px 14px 2px;display:flex;gap:6px">
      <button class="btn btn-ghost btn-xs" onclick="openModal('modal-create-db')" style="flex:1;justify-content:center">+ New DB</button>
      <?php if ($db): ?>
      <button class="btn btn-danger btn-xs" onclick="openModal('modal-drop-db')" style="flex:1;justify-content:center">Drop DB</button>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($db && $tables): ?>
  <div class="nav-section" style="flex:1;overflow:hidden;display:flex;flex-direction:column;min-height:0">
    <div class="nav-label"><?= h($db) ?> / tables</div>
    <div class="db-list" style="flex:1">
      <?php foreach ($tables as $tbl): ?>
      <a href="?db=<?= urlencode($db) ?>&table=<?= urlencode($tbl) ?>" class="nav-item <?= $tbl===$table?'active':'' ?>">
        <span class="nav-dot"></span><?= h($tbl) ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php if ($db): ?>
    <div style="padding:6px 14px 2px">
      <a href="?db=<?= urlencode($db) ?>&view=create_table" class="btn btn-ghost btn-xs" style="width:100%;justify-content:center">+ New Table</a>
    </div>
    <?php endif; ?>
  </div>
  <?php elseif ($db): ?>
  <div style="padding:8px 14px">
    <a href="?db=<?= urlencode($db) ?>&view=create_table" class="btn btn-ghost btn-xs" style="width:100%;justify-content:center">+ New Table</a>
  </div>
  <?php endif; ?>

  <div style="padding:10px 12px;border-top:1px solid var(--border);flex-shrink:0">
    <a href="?action=logout" class="btn btn-ghost" style="width:100%;justify-content:center">&larr; Disconnect</a>
  </div>
</aside>

<!-- ── Main ────────────────────────────────────────────────────────────── -->
<main class="main">

  <!-- Topbar -->
  <div class="topbar">
    <div class="breadcrumb">
      <a href="?">~</a>
      <?php if ($db): ?><span>/</span><a href="?db=<?= urlencode($db) ?>"><?= h($db) ?></a><?php endif; ?>
      <?php if ($table): ?><span>/</span><span style="color:var(--text)"><?= h($table) ?></span><?php endif; ?>
    </div>
    <div style="display:flex;gap:7px;flex-wrap:wrap">
      <?php if ($db && !$table): ?>
        <a href="?action=export&db=<?= urlencode($db) ?>" class="btn btn-secondary btn-sm">&#8595; Export DB</a>
      <?php endif; ?>
      <?php if ($db && $table): ?>
        <a href="?db=<?= urlencode($db) ?>&table=<?= urlencode($table) ?>&view=insert" class="btn btn-secondary btn-sm">+ Insert Row</a>
        <a href="?action=export&db=<?= urlencode($db) ?>&table=<?= urlencode($table) ?>" class="btn btn-secondary btn-sm">&#8595; Export</a>
        <button class="btn btn-warn btn-sm" onclick="openModal('modal-rename')">&#9998; Rename</button>
        <button class="btn btn-warn btn-sm" onclick="openModal('modal-truncate')">&#9889; Truncate</button>
        <button class="btn btn-danger btn-sm" onclick="openModal('modal-drop')">&#x2715; Drop</button>
      <?php endif; ?>
    </div>
  </div>

  <!-- Tab bar (when table is selected) -->
  <?php if ($db && $table): ?>
  <div class="tabs">
    <a class="tab <?= ($view==='browse'||$view==='edit')?'active':'' ?>" href="?db=<?= urlencode($db) ?>&table=<?= urlencode($table) ?>&view=browse">&#9776; Browse</a>
    <a class="tab <?= $view==='structure'?'active':'' ?>" href="?db=<?= urlencode($db) ?>&table=<?= urlencode($table) ?>&view=structure">&#9783; Structure</a>
    <a class="tab <?= $view==='insert'?'active':'' ?>"    href="?db=<?= urlencode($db) ?>&table=<?= urlencode($table) ?>&view=insert">+ Insert</a>
    <a class="tab" href="?db=<?= urlencode($db) ?>&table=<?= urlencode($table) ?>&action=query">&#9654; SQL</a>
  </div>
  <?php endif; ?>

  <!-- Content -->
  <div class="content">
    <?php if ($flash): ?>
    <div class="flash <?= $flash['type'] ?>"><?= $flash['type']==='success'?'&#x2713;':'&#9888;' ?> <?= h($flash['msg']) ?></div>
    <?php endif; ?>

    <!-- ── SQL Editor ───────────────────────────────────────────────── -->
    <?php if ($db && (!$table || $action==='query')): ?>
    <div class="card" style="<?= $table?'margin-bottom:14px':'' ?>">
      <div class="card-header">
        <div class="card-title">SQL Query</div>
        <span class="tag tag-blue"><?= h($db) ?></span>
      </div>
      <div class="card-body">
        <form method="post" action="?action=query&db=<?= urlencode($db) ?>&table=<?= urlencode($table) ?>">
          <textarea class="sql-editor" name="sql" id="sql-editor" rows="4"
            placeholder="SELECT * FROM table_name LIMIT 100;"><?= h($querySQL ?: ($table&&!$queryResult ? "SELECT * FROM `$table` LIMIT 100;" : '')) ?></textarea>
          <?php if ($queryError): ?><div class="flash error" style="margin-top:8px">&#9888; <?= h($queryError) ?></div><?php endif; ?>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-top:9px">
            <span style="font-size:10px;color:var(--muted)"><kbd>Ctrl</kbd>+<kbd>Enter</kbd> to run</span>
            <button class="btn btn-primary">&#9654; Run</button>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── Query Results ────────────────────────────────────────────── -->
    <?php if ($queryResult): ?>
    <div class="card">
      <div class="card-header">
        <div class="card-title">Result</div>
        <span class="tag tag-green"><?= count($queryResult['rows']) ?> rows</span>
      </div>
      <div class="tbl-wrap">
        <table>
          <thead><tr><?php foreach ($queryResult['meta'] as $c): ?><th><?= h($c) ?></th><?php endforeach; ?></tr></thead>
          <tbody>
            <?php foreach ($queryResult['rows'] as $row): ?>
            <tr><?php foreach ($row as $v): ?><td class="<?= is_null($v)?'null':(is_numeric($v)?'num':'') ?>"><?= is_null($v)?'<em>NULL</em>':h(mb_strimwidth((string)$v,0,200,'&hellip;')) ?></td><?php endforeach; ?></tr>
            <?php endforeach; ?>
            <?php if (!$queryResult['rows']): ?><tr><td colspan="99" style="text-align:center;padding:24px;color:var(--muted)">No rows</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($db && $table && !$queryResult): ?>

      <?php if ($view === 'browse' || $view === 'edit'): ?>
      <!-- ── BROWSE VIEW ────────────────────────────────────────────── -->
      <?php if ($view === 'browse'): ?>

      <!-- Stats -->
      <div class="stats">
        <div class="stat-card"><div class="stat-val"><?= number_format($totalRows) ?></div><div class="stat-lbl">Rows</div></div>
        <div class="stat-card"><div class="stat-val"><?= count($columns) ?></div><div class="stat-lbl">Columns</div></div>
        <div class="stat-card"><div class="stat-val"><?= $totalPages ?></div><div class="stat-lbl">Pages</div></div>
      </div>

      <!-- Search -->
      <form method="get" action="" class="search-bar">
        <input type="hidden" name="db" value="<?= h($db) ?>">
        <input type="hidden" name="table" value="<?= h($table) ?>">
        <input class="search-input" name="search" placeholder="Search all columns&hellip;" value="<?= h($search) ?>">
        <button class="btn btn-secondary btn-sm">Search</button>
        <?php if ($search): ?><a href="?db=<?= urlencode($db) ?>&table=<?= urlencode($table) ?>" class="btn btn-ghost btn-sm">&#x2715; Clear</a><?php endif; ?>
      </form>

      <!-- Data table -->
      <div class="card">
        <div class="card-header">
          <div class="card-title">Data <?= $search ? '<span class="tag tag-yellow">filtered</span>' : '' ?></div>
          <span class="tag tag-gray">Page <?= $page ?> / <?= $totalPages ?></span>
        </div>
        <div class="tbl-wrap">
          <table>
            <thead>
              <tr>
                <?php foreach ($columns as $col): ?><th><?= h($col['Field']) ?></th><?php endforeach; ?>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($rows): foreach ($rows as $row): ?>
              <tr>
                <?php foreach ($row as $v): ?>
                <td class="<?= is_null($v)?'null':(is_numeric($v)?'num':'') ?>" title="<?= h((string)$v) ?>"><?= is_null($v)?'<em>NULL</em>':h(mb_strimwidth((string)$v,0,80,'&hellip;')) ?></td>
                <?php endforeach; ?>
                <td class="actions">
                  <?php if ($priCol && isset($row[$priCol])): ?>
                  <a class="btn btn-secondary btn-xs" href="?db=<?= urlencode($db) ?>&table=<?= urlencode($table) ?>&view=edit&pk=<?= urlencode($row[$priCol]) ?>&page=<?= $page ?>">&#9998; Edit</a>
                  <form method="post" action="?action=delete&db=<?= urlencode($db) ?>&table=<?= urlencode($table) ?>&page=<?= $page ?>" style="display:inline" onsubmit="return confirm('Delete this row?')">
                    <input type="hidden" name="where" value="`<?= h($priCol) ?>` = <?= h($pdo->quote($row[$priCol])) ?>">
                    <button class="btn btn-danger btn-xs">Del</button>
                  </form>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; else: ?>
              <tr><td colspan="99"><div class="empty"><div class="empty-icon">&#9723;</div><?= $search?'No matching rows':'Table is empty' ?></div></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div style="padding:10px 14px;border-top:1px solid var(--border);display:flex;align-items:center;gap:6px;flex-wrap:wrap">
          <?php if ($page>1): ?><a class="page-btn" href="?db=<?= urlencode($db) ?>&table=<?= urlencode($table) ?>&page=<?= $page-1 ?><?= $search?'&search='.urlencode($search):'' ?>">&larr;</a><?php endif; ?>
          <?php for ($i=max(1,$page-3);$i<=min($totalPages,$page+3);$i++): ?>
          <a class="page-btn <?= $i===$page?'active':'' ?>" href="?db=<?= urlencode($db) ?>&table=<?= urlencode($table) ?>&page=<?= $i ?><?= $search?'&search='.urlencode($search):'' ?>"><?= $i ?></a>
          <?php endfor; ?>
          <?php if ($page<$totalPages): ?><a class="page-btn" href="?db=<?= urlencode($db) ?>&table=<?= urlencode($table) ?>&page=<?= $page+1 ?><?= $search?'&search='.urlencode($search):'' ?>">&rarr;</a><?php endif; ?>
          <span style="margin-left:auto;color:var(--muted);font-size:11px"><?= number_format($totalRows) ?> rows total</span>
        </div>
        <?php endif; ?>
      </div>

      <?php elseif ($view === 'edit' && $editRow): ?>
      <!-- ── EDIT ROW ──────────────────────────────────────────────── -->
      <div class="card">
        <div class="card-header">
          <div class="card-title">Edit Row</div>
          <a href="?db=<?= urlencode($db) ?>&table=<?= urlencode($table) ?>&page=<?= $_GET['page'] ?? 1 ?>" class="btn btn-ghost btn-sm">&larr; Back</a>
        </div>
        <div class="card-body">
          <form method="post" action="?action=update&db=<?= urlencode($db) ?>&table=<?= urlencode($table) ?>">
            <input type="hidden" name="pk_val" value="<?= h($editRow[$priCol] ?? '') ?>">
            <?php foreach ($columns as $col):
              $val = $editRow[$col['Field']] ?? null;
              $isPri = $col['Key'] === 'PRI';
            ?>
            <div class="form-group" style="margin-bottom:10px">
              <label class="form-label">
                <?= h($col['Field']) ?>
                <?php if ($isPri): ?><span class="tag tag-green" style="margin-left:4px">PK</span><?php endif; ?>
                <span style="color:var(--faint);font-weight:400">&nbsp;<?= h($col['Type']) ?></span>
              </label>
              <?php if ($isPri): ?>
              <input class="form-input" value="<?= h($val) ?>" disabled style="color:var(--muted)">
              <?php elseif (str_contains(strtolower($col['Type']), 'text')): ?>
              <textarea class="form-textarea" name="col_<?= h($col['Field']) ?>" rows="4"><?= h($val) ?></textarea>
              <?php else: ?>
              <input class="form-input" name="col_<?= h($col['Field']) ?>" value="<?= h($val) ?>">
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <hr class="divider">
            <div style="display:flex;gap:8px;justify-content:flex-end">
              <a href="?db=<?= urlencode($db) ?>&table=<?= urlencode($table) ?>" class="btn btn-ghost">Cancel</a>
              <button class="btn btn-primary">&#x2713; Save Changes</button>
            </div>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <?php elseif ($view === 'structure'): ?>
      <!-- ── STRUCTURE VIEW ────────────────────────────────────────── -->
      <div class="card">
        <div class="card-header">
          <div class="card-title">Columns</div>
          <span class="tag tag-gray"><?= count($columns) ?> columns</span>
        </div>
        <div class="tbl-wrap">
          <table>
            <thead><tr><th>#</th><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th><th>Action</th></tr></thead>
            <tbody>
              <?php foreach ($columns as $i => $col): ?>
              <tr>
                <td style="color:var(--muted)"><?= $i+1 ?></td>
                <td style="color:#fff;font-weight:500"><?= h($col['Field']) ?></td>
                <td style="color:var(--accent2)"><?= h($col['Type']) ?></td>
                <td><?= $col['Null']==='YES'?'<span class="tag tag-yellow">YES</span>':'<span class="tag tag-gray">NO</span>' ?></td>
                <td><?php
                  if($col['Key']==='PRI') echo '<span class="tag tag-green">PRI</span>';
                  elseif($col['Key']==='UNI') echo '<span class="tag tag-blue">UNI</span>';
                  elseif($col['Key']==='MUL') echo '<span class="tag tag-yellow">MUL</span>';
                  else echo '<span style="color:var(--muted)">&mdash;</span>';
                ?></td>
                <td style="color:var(--muted)"><?= h($col['Default'] ?? '—') ?></td>
                <td style="color:var(--purple)"><?= h($col['Extra']) ?: '<span style="color:var(--muted)">—</span>' ?></td>
                <td>
                  <?php if ($col['Key'] !== 'PRI'): ?>
                  <form method="post" action="?action=drop_column&db=<?= urlencode($db) ?>&table=<?= urlencode($table) ?>" style="display:inline" onsubmit="return confirm('Drop column <?= h(addslashes($col['Field'])) ?>?')">
                    <input type="hidden" name="col" value="<?= h($col['Field']) ?>">
                    <button class="btn btn-danger btn-xs">Drop</button>
                  </form>
                  <?php else: echo '<span style="color:var(--muted);font-size:11px">—</span>'; endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Add Column -->
      <div class="card">
        <div class="card-header"><div class="card-title">Add Column</div></div>
        <div class="card-body">
          <form method="post" action="?action=add_column&db=<?= urlencode($db) ?>&table=<?= urlencode($table) ?>">
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Column Name</label>
                <input class="form-input" name="cname" placeholder="column_name" required>
              </div>
              <div class="form-group">
                <label class="form-label">Type</label>
                <select class="form-select" name="ctype">
                  <?php foreach (['VARCHAR(255)','INT','BIGINT','TINYINT(1)','TEXT','LONGTEXT','DECIMAL(10,2)','FLOAT','DOUBLE','DATE','DATETIME','TIMESTAMP','JSON'] as $t): ?>
                  <option><?= $t ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group narrow">
                <label class="form-label">Nullable</label>
                <select class="form-select" name="cnull">
                  <option value="0">NOT NULL</option>
                  <option value="1">NULL</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Default <span style="color:var(--muted)">(opt)</span></label>
                <input class="form-input" name="cdefault" placeholder="NULL / value">
              </div>
            </div>
            <div style="display:flex;justify-content:flex-end">
              <button class="btn btn-primary">+ Add Column</button>
            </div>
          </form>
        </div>
      </div>

      <?php elseif ($view === 'insert'): ?>
      <!-- ── INSERT VIEW ────────────────────────────────────────────── -->
      <div class="card">
        <div class="card-header">
          <div class="card-title">Insert New Row</div>
          <a href="?db=<?= urlencode($db) ?>&table=<?= urlencode($table) ?>" class="btn btn-ghost btn-sm">&larr; Back</a>
        </div>
        <div class="card-body">
          <form method="post" action="?action=insert&db=<?= urlencode($db) ?>&table=<?= urlencode($table) ?>">
            <?php foreach ($columns as $col): if ($col['Extra']==='auto_increment') continue; ?>
            <div class="form-group" style="margin-bottom:10px">
              <label class="form-label">
                <?= h($col['Field']) ?>
                <?php if ($col['Key']==='PRI'): ?><span class="tag tag-green" style="margin-left:4px">PK</span><?php endif; ?>
                <span style="color:var(--faint);font-weight:400">&nbsp;<?= h($col['Type']) ?></span>
                <?php if ($col['Null']==='YES'): ?><span class="tag tag-gray" style="margin-left:4px">nullable</span><?php endif; ?>
              </label>
              <?php if (str_contains(strtolower($col['Type']), 'text')): ?>
              <textarea class="form-textarea" name="col_<?= h($col['Field']) ?>" rows="3" placeholder="<?= $col['Null']==='YES'?'NULL':'' ?>"></textarea>
              <?php else: ?>
              <input class="form-input" name="col_<?= h($col['Field']) ?>" placeholder="<?= $col['Null']==='YES'?'NULL':($col['Default']??'') ?>">
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <hr class="divider">
            <div style="display:flex;gap:8px;justify-content:flex-end">
              <a href="?db=<?= urlencode($db) ?>&table=<?= urlencode($table) ?>" class="btn btn-ghost">Cancel</a>
              <button class="btn btn-primary">&#x2713; Insert Row</button>
            </div>
          </form>
        </div>
      </div>
      <?php endif; // view switch ?>

    <?php elseif ($db && !$table && !$queryResult): ?>
    <!-- ── DB OVERVIEW ───────────────────────────────────────────────── -->
    <?php if ($view === 'create_table'): ?>
    <!-- Create Table Form -->
    <div class="section-title">Create New Table in <?= h($db) ?></div>
    <div class="card">
      <div class="card-header"><div class="card-title">New Table</div><span class="tag tag-gray">auto id column included</span></div>
      <div class="card-body">
        <form method="post" action="?action=create_table&db=<?= urlencode($db) ?>">
          <div class="form-group" style="margin-bottom:14px">
            <label class="form-label">Table Name</label>
            <input class="form-input" name="tname" placeholder="my_table" required style="max-width:280px">
          </div>
          <div class="section-title" style="margin-top:14px">Columns</div>
          <div id="col-builder">
            <?php for ($ci = 0; $ci < 3; $ci++): ?>
            <div class="col-builder-row">
              <input class="form-input" name="cname[]" placeholder="column_name">
              <select class="form-select" name="ctype[]">
                <?php foreach (['VARCHAR(255)','INT','BIGINT','TINYINT(1)','TEXT','LONGTEXT','DECIMAL(10,2)','FLOAT','DATE','DATETIME','TIMESTAMP','JSON'] as $t): ?><option><?= $t ?></option><?php endforeach; ?>
              </select>
              <select class="form-select" name="cnull[]" style="max-width:120px">
                <option value="">NOT NULL</option>
                <option value="<?= $ci ?>"><?= $ci ?> NULL</option>
              </select>
              <button type="button" class="btn btn-ghost btn-xs" onclick="this.closest('.col-builder-row').remove()">&#x2715;</button>
            </div>
            <?php endfor; ?>
          </div>
          <button type="button" class="btn btn-ghost btn-sm" style="margin-top:8px" onclick="addColRow()">+ Add Column</button>
          <hr class="divider">
          <div style="display:flex;gap:8px;justify-content:flex-end">
            <a href="?db=<?= urlencode($db) ?>" class="btn btn-ghost">Cancel</a>
            <button class="btn btn-primary">&#x2713; Create Table</button>
          </div>
        </form>
      </div>
    </div>
    <?php else: ?>
    <!-- Tables list -->
    <div class="stats">
      <div class="stat-card"><div class="stat-val"><?= count($tables) ?></div><div class="stat-lbl">Tables</div></div>
    </div>
    <?php if ($tables): ?>
    <div class="card">
      <div class="card-header">
        <div class="card-title">Tables</div>
        <a href="?db=<?= urlencode($db) ?>&view=create_table" class="btn btn-secondary btn-sm">+ New Table</a>
      </div>
      <div class="tbl-wrap">
        <table>
          <thead><tr><th>Table</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($tables as $tbl): ?>
            <tr>
              <td><a href="?db=<?= urlencode($db) ?>&table=<?= urlencode($tbl) ?>" style="color:#fff"><?= h($tbl) ?></a></td>
              <td class="actions" style="border-bottom:1px solid var(--border)">
                <a class="btn btn-ghost btn-xs" href="?db=<?= urlencode($db) ?>&table=<?= urlencode($tbl) ?>">Browse</a>
                <a class="btn btn-ghost btn-xs" href="?db=<?= urlencode($db) ?>&table=<?= urlencode($tbl) ?>&view=structure">Structure</a>
                <a class="btn btn-ghost btn-xs" href="?db=<?= urlencode($db) ?>&table=<?= urlencode($tbl) ?>&view=insert">+ Insert</a>
                <a class="btn btn-secondary btn-xs" href="?action=export&db=<?= urlencode($db) ?>&table=<?= urlencode($tbl) ?>">Export</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php else: ?>
    <div class="empty"><div class="empty-icon">&#9723;</div>No tables yet. <a href="?db=<?= urlencode($db) ?>&view=create_table">Create one</a>.</div>
    <?php endif; ?>
    <?php endif; ?>

    <?php elseif (!$db && !$queryResult): ?>
    <!-- Welcome -->
    <div style="padding:24px 0">
      <div class="section-title">Connected</div>
      <div class="stats">
        <div class="stat-card"><div class="stat-val"><?= count($databases) ?></div><div class="stat-lbl">Databases</div></div>
      </div>
      <div style="color:var(--muted);font-size:12px;line-height:2">
        Select a database from the sidebar, or create a new one.<br>
        You can also run raw SQL using the editor above.
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /content -->
</main>
</div><!-- /layout -->

<!-- ═══════ MODALS ═══════ -->

<!-- Rename Table -->
<?php if ($table): ?>
<div class="modal-overlay" id="modal-rename">
  <div class="modal">
    <div class="modal-title">&#9998; Rename Table</div>
    <form method="post" action="?action=rename_table&db=<?= urlencode($db) ?>&table=<?= urlencode($table) ?>">
      <div class="form-group">
        <label class="form-label">New Table Name</label>
        <input class="form-input" name="new_name" value="<?= h($table) ?>" required autofocus>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-rename')">Cancel</button>
        <button class="btn btn-primary">Rename</button>
      </div>
    </form>
  </div>
</div>

<!-- Truncate Confirm -->
<div class="modal-overlay" id="modal-truncate">
  <div class="modal">
    <div class="modal-title">&#9889; Truncate Table</div>
    <p style="color:var(--text);font-size:12px;line-height:1.7;margin-bottom:6px">All rows in <strong style="color:#fff"><?= h($table) ?></strong> will be permanently deleted. The table structure will remain.</p>
    <form method="post" action="?action=truncate_table&db=<?= urlencode($db) ?>&table=<?= urlencode($table) ?>">
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-truncate')">Cancel</button>
        <button class="btn btn-warn">Yes, Truncate</button>
      </div>
    </form>
  </div>
</div>

<!-- Drop Table Confirm -->
<div class="modal-overlay" id="modal-drop">
  <div class="modal">
    <div class="modal-title">&#x2715; Drop Table</div>
    <p style="color:var(--text);font-size:12px;line-height:1.7;margin-bottom:6px">Table <strong style="color:#fff"><?= h($table) ?></strong> and all its data will be <strong style="color:var(--danger)">permanently deleted</strong>. This cannot be undone.</p>
    <form method="post" action="?action=drop_table&db=<?= urlencode($db) ?>&table=<?= urlencode($table) ?>">
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-drop')">Cancel</button>
        <button class="btn btn-danger">Yes, Drop Table</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Create DB -->
<div class="modal-overlay" id="modal-create-db">
  <div class="modal">
    <div class="modal-title">+ Create Database</div>
    <form method="post" action="?action=create_db">
      <div class="form-group">
        <label class="form-label">Database Name</label>
        <input class="form-input" name="new_db" placeholder="my_database" required autofocus>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-create-db')">Cancel</button>
        <button class="btn btn-primary">Create</button>
      </div>
    </form>
  </div>
</div>

<!-- Drop DB -->
<?php if ($db): ?>
<div class="modal-overlay" id="modal-drop-db">
  <div class="modal">
    <div class="modal-title">Drop Database</div>
    <p style="color:var(--text);font-size:12px;line-height:1.7;margin-bottom:6px">Database <strong style="color:#fff"><?= h($db) ?></strong> and <strong style="color:var(--danger)">ALL its tables and data</strong> will be permanently deleted.</p>
    <form method="post" action="?action=drop_db&db=<?= urlencode($db) ?>">
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-drop-db')">Cancel</button>
        <button class="btn btn-danger">Yes, Drop Database</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
// Ctrl+Enter SQL
document.getElementById('sql-editor')?.addEventListener('keydown', e => {
  if ((e.ctrlKey||e.metaKey) && e.key==='Enter') e.target.closest('form').submit();
});

// Modals
function openModal(id) { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
});

// Col builder
let colIdx = 3;
function addColRow() {
  const tpl = `<div class="col-builder-row">
    <input class="form-input" name="cname[]" placeholder="column_name">
    <select class="form-select" name="ctype[]">
      ${['VARCHAR(255)','INT','BIGINT','TINYINT(1)','TEXT','LONGTEXT','DECIMAL(10,2)','FLOAT','DATE','DATETIME','TIMESTAMP','JSON'].map(t=>`<option>${t}</option>`).join('')}
    </select>
    <select class="form-select" name="cnull[]" style="max-width:120px">
      <option value="">NOT NULL</option><option value="${colIdx}">${colIdx} NULL</option>
    </select>
    <button type="button" class="btn btn-ghost btn-xs" onclick="this.closest('.col-builder-row').remove()">&#x2715;</button>
  </div>`;
  document.getElementById('col-builder').insertAdjacentHTML('beforeend', tpl);
  colIdx++;
}
</script>

<?php endif; // pdo check ?>
</body>
</html>
