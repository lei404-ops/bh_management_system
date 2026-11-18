# BHMS on Laravel - setup (XAMPP / Windows)

1. Start **Apache** and **MySQL** in XAMPP.
2. Open http://localhost/phpmyadmin -> New -> create database `bh_management_system` (collation utf8mb4_unicode_ci).
3. In this folder (VS Code terminal):
   ```
   php artisan config:clear
   php artisan migrate:fresh --seed
   php artisan serve
   ```
4. Open http://localhost:8000 and log in (password for all: `Password@123`):
   admin@bhms.test, landlord@bhms.test, tenant1@bhms.test, tenant2@bhms.test, staff@bhms.test
5. Monthly billing: `php artisan bhms:monthly-billing 2026-11` (also scheduled daily in routes/console.php).
