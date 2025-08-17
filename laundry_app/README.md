# Laundry Fixed App (v2)
This ZIP contains a full project adjusted to your fixed DB schema.

## Steps
1. Import `sql/create_db.sql` into MySQL:
   `mysql -u root -p < sql/create_db.sql`
2. Edit `app/init.php` if needed to set DB credentials.
3. Run `php seed.php` to create an admin user (admin/admin123).
4. Serve `public/` folder with PHP builtin server:
   `php -S localhost:8000 -t public`
5. Visit `http://localhost:8000/login.php` and login.
