<div align="center">

```
███████╗██╗███╗   ███╗██████╗ ██╗     ███████╗ █████╗ ██████╗ ███╗   ███╗██╗███╗   ██╗
██╔════╝██║████╗ ████║██╔══██╗██║     ██╔════╝██╔══██╗██╔══██╗████╗ ████║██║████╗  ██║
███████╗██║██╔████╔██║██████╔╝██║     █████╗  ███████║██║  ██║██╔████╔██║██║██╔██╗ ██║
╚════██║██║██║╚██╔╝██║██╔═══╝██║     ██╔══╝  ██╔══██║██║  ██║██║╚██╔╝██║██║██║╚██╗██║
███████║██║██║ ╚═╝ ██║██║     ███████╗███████╗██║  ██║██████╔╝██║ ╚═╝ ██║██║██║ ╚████║
╚══════╝╚═╝╚═╝     ╚═╝╚═╝     ╚══════╝╚══════╝╚═╝  ╚═╝╚═════╝ ╚═╝     ╚═╝╚═╝╚═╝  ╚═══╝
```

**A lightweight, single-file PHP database manager for MySQL & MariaDB.**  
No installation. No dependencies. Just drop and go.

<br>

[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://mysql.com)
[![MariaDB](https://img.shields.io/badge/MariaDB-10%2B-003545?style=flat-square&logo=mariadb&logoColor=white)](https://mariadb.org)
[![License](https://img.shields.io/badge/License-MIT-00d4aa?style=flat-square)](LICENSE)
[![Single File](https://img.shields.io/badge/Size-Single%20File-ff4757?style=flat-square)](#)
[![Version](https://img.shields.io/badge/Version-2.0.0-0099ff?style=flat-square)](#)

<br>

[Features](#-features) · [Quick Start](#-quick-start) · [Screenshots](#-screenshots) · [Security](#-security) · [Requirements](#-requirements)

</div>

---

## ✦ What is SimpleAdmin?

SimpleAdmin is a **zero-dependency, single-file** PHP database manager — think Adminer, but stripped to the essentials with a dark terminal-inspired UI. No Composer, no npm, no framework. Upload one file and you have a full-featured database management panel.

```
wget https://raw.githubusercontent.com/youruser/simpleadmin/main/adminer.php
```

> Place it on your server. Open in browser. Done.

---

## ⚡ Features

### Database Management
| Feature | Description |
|---|---|
| 🗄️ **Multi-database** | Browse and switch between all accessible databases |
| ➕ **Create Database** | Create new databases with `utf8mb4` charset |
| 🗑️ **Drop Database** | Safely drop databases with modal confirmation |
| 📤 **Export SQL** | Download full database or per-table `.sql` dumps |

### Table Operations
| Feature | Description |
|---|---|
| 📋 **Browse Data** | Paginated table view (50 rows/page) |
| 🔍 **Search / Filter** | Full-text search across all columns |
| 🏗️ **Structure View** | Inspect columns, types, keys, and constraints |
| ✏️ **Rename Table** | Rename tables on the fly |
| ⚡ **Truncate Table** | Clear all rows while keeping the structure |
| 🗑️ **Drop Table** | Permanently delete tables with confirmation |
| 📤 **Export Table** | Export a single table as SQL dump |

### Row Operations
| Feature | Description |
|---|---|
| 👁️ **Browse Rows** | View data with column type hints and truncation |
| ✏️ **Edit Row** | Full edit form per row, with `textarea` for TEXT fields |
| ➕ **Insert Row** | Insert new rows with a schema-aware form |
| 🗑️ **Delete Row** | Delete individual rows by primary key |

### Schema Operations
| Feature | Description |
|---|---|
| 🏗️ **Create Table** | Visual column builder with type/null selectors |
| ➕ **Add Column** | Add new columns with type, nullability, and default |
| 🗑️ **Drop Column** | Remove columns from the structure view |

### SQL
| Feature | Description |
|---|---|
| 💻 **SQL Editor** | Freeform query editor with result table |
| ⌨️ **Keyboard Shortcut** | `Ctrl+Enter` / `Cmd+Enter` to execute |
| 📊 **Result View** | Auto-formatted result set with row count |

---

## 🚀 Quick Start

### 1. Download

```bash
# via wget
wget https://raw.githubusercontent.com/AsaKinSh3ll/NEW-ADMINER-2025/refs/heads/main/adminer.php

# or curl
curl -O https://raw.githubusercontent.com/AsaKinSh3ll/NEW-ADMINER-2025/refs/heads/main/adminer.php
```

### 2. Deploy

```bash
# Apache / Nginx — copy to web root
cp adminer.php /var/www/html/adminer.php

# XAMPP
cp adminer.php C:/xampp/htdocs/adminer.php

# Laragon
cp adminer.php C:/laragon/www/adminer.php

# PHP built-in server (local dev)
php -S localhost:8080
```

### 3. Open

```
http://localhost/adminer.php
http://localhost:8080/adminer.php
```

### 4. Connect

Fill in your credentials and hit **Connect →**

```
Host:     localhost
Port:     3306
Username: root
Password: ••••••••
Database: (optional — leave blank to browse all)
```

---

## 🖥️ Screenshots

> Dark terminal-inspired UI with `JetBrains Mono` and a teal accent palette.

```
┌─────────────────────────────────────────────────────────────────────┐
│  S  SimpleAdmin       ~  /  my_database  /  users                  │
│  v2.0.0 · root@localhost                  + Insert  ↓ Export  ✎ …  │
├─────────────────┬───────────────────────────────────────────────────┤
│  DATABASES      │  ≡ Browse   ✿ Structure   + Insert   ▶ SQL       │
│  ● information… │                                                   │
│  ● my_database  │  128 Rows   8 Columns   3 Pages                  │
│  ● test         │                                                   │
│                 │  ┌─ Search ──────────────────────────────┐       │
│  + New DB       │  │  Search all columns…          [Search]│       │
│                 │  └────────────────────────────────────────┘       │
│  MY_DATABASE    │                                                   │
│  ● users   ←   │  id   name          email          created_at     │
│  ● posts        │  1    Alice Smith   alice@…        2024-01-15     │
│  ● comments     │  2    Bob Jones     bob@…          2024-01-16     │
│                 │  3    Carol White   carol@…        2024-01-17     │
│  + New Table    │                                                   │
│                 │  ← 1  [2]  3 →                   128 rows total  │
│                 │                                                   │
│  ← Disconnect   │                                                   │
└─────────────────┴───────────────────────────────────────────────────┘
```

---

## 🔒 Security

> ⚠️ **SimpleAdmin is a developer tool. Do not expose it on public-facing production servers.**

### Recommended practices

**Protect with HTTP Basic Auth (Apache)**
```apache
# .htaccess — same directory as adminer.php
AuthType Basic
AuthName "Restricted"
AuthUserFile /path/to/.htpasswd
Require valid-user
```

**Restrict by IP (Nginx)**
```nginx
location /adminer.php {
    allow 127.0.0.1;
    allow YOUR.IP.ADDRESS.HERE;
    deny all;
}
```

**Remove after use**
```bash
# Best practice on production: delete when not needed
rm /var/www/html/adminer.php
```

**Use a non-obvious filename**
```bash
mv adminer.php db_panel_xK92m.php
```

### What SimpleAdmin does NOT do
- ❌ Store credentials server-side (sessions only, cleared on disconnect)
- ❌ Log queries
- ❌ Execute shell commands
- ❌ Access the filesystem

---

## 📋 Requirements

| Requirement | Version |
|---|---|
| PHP | `7.4` or higher |
| PHP Extension | `pdo_mysql` (usually enabled by default) |
| MySQL | `5.7` or higher |
| MariaDB | `10.0` or higher |
| Web Server | Apache, Nginx, Caddy, or PHP built-in server |

### Check if `pdo_mysql` is enabled

```bash
php -m | grep pdo_mysql
```

Or in PHP:
```php
<?php var_dump(extension_loaded('pdo_mysql')); ?>
```

---

## 📁 File Structure

```
adminer.php      ← the entire application (single file)
README.md        ← you are here
```

That's it.

---

## 🎨 UI / Design

SimpleAdmin uses a terminal-dark aesthetic:

```
Background:   #0d0e11    (near-black)
Surface:      #13151a    (card background)
Accent:       #00d4aa    (teal — interactive elements)
Text:         #c8ccd8    (off-white body text)
Numbers:      #0099ff    (blue — numeric values)
Danger:       #ff4757    (red — destructive actions)

Fonts:        JetBrains Mono (body/code)
              Space Mono (headings/labels)
```

No external CSS frameworks. No bundlers. All styles are inlined in the single PHP file.

---

## 🧩 Keyboard Shortcuts

| Shortcut | Action |
|---|---|
| `Ctrl` + `Enter` | Execute SQL query |
| `Esc` | Close modal |

---

## 🔧 Customization

SimpleAdmin is intentionally minimal — but easy to extend. All logic and UI live in one file.

**Change rows per page:**
```php
// Line ~30 in adminer.php
$perPage = 50;  // ← change this
```

**Change default host/port:**
```php
// In the login form HTML
<input ... name="host" value="localhost">   // ← change default host
<input ... name="port" value="3306">        // ← change default port
```

---

## 🆚 Comparison

| | SimpleAdmin | Adminer | phpMyAdmin |
|---|:---:|:---:|:---:|
| Single file | ✅ | ✅ | ❌ |
| No dependencies | ✅ | ✅ | ❌ |
| Dark theme built-in | ✅ | ❌ | ❌ |
| Lightweight (<1MB) | ✅ | ✅ | ❌ |
| Row edit/insert | ✅ | ✅ | ✅ |
| SQL editor | ✅ | ✅ | ✅ |
| Export SQL | ✅ | ✅ | ✅ |
| Schema builder | ✅ | ✅ | ✅ |
| Multiple DB engines | ❌ MySQL only | ✅ | ✅ |
| User management | ❌ | ✅ | ✅ |
| Import SQL | ❌ | ✅ | ✅ |

SimpleAdmin is intentionally scoped — it does the **80% most common tasks** with zero setup friction.

---

## 📜 License

```
MIT License — free to use, modify, and distribute.
```

---

## 🤝 Contributing

1. Fork the repo
2. Make your changes to `adminer.php`
3. Test against MySQL 5.7+ and MariaDB 10+
4. Open a PR with a clear description

**Keep it single-file.** That's the whole point.

---

<div align="center">

Made with `<?php` and dark mode.

**[⬆ back to top](#)**

</div>
