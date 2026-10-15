# 4RMTECH Gadget Shop Website (PHP API + PostgreSQL)

This React/Vite website is designed in a modern navy/electric-blue style inspired by the supplied teammate design. It calls the existing PHP API in XAMPP; it does not connect directly to PostgreSQL and does not contain database credentials.

## Features included
- Live products and categories from `products.php` and `get_categories.php`
- Customer registration/login using `register.php` and `login.php`
- Repair booking and customer appointment list/cancellation
- Admin login using `admin_login.php`
- Admin product/category CRUD, appointment status updates, customer list, inquiries
- Contact inquiry submission

## Run on Windows
1. Keep PostgreSQL and XAMPP Apache running.
2. Copy `.env.example` to `.env` (or create `.env`) and set `VITE_API_BASE_URL=http://localhost/gadget_shop`.
3. Open this folder in VS Code.
4. Open Terminal in VS Code and run:
   ```bash
   npm install
   npm run dev
   ```
5. Open the local URL Vite prints, normally `http://localhost:5173`.

## PHP API CORS setup (required)
Browsers block cross-origin requests unless PHP responds with CORS headers. The `php-api-cors` folder contains `cors.php` plus endpoint copies patched to load it. Copy `cors.php` into `C:\xampp\htdocs\gadget_shop` and copy the patched endpoint files from `php-api-cors/endpoints` into that folder, backing up originals first. Keep your own `db.php`; it is deliberately not included here.

The CORS helper only permits local Vite development origins (`localhost:5173`, `127.0.0.1:5173`, and Vite preview on port 4173). Add your real production origin explicitly when deploying.

## Important notes
- Keep real database credentials only in your local PHP `db.php`. Never put PostgreSQL credentials in `.env` for Vite.
- The customer/employee session in this starter is kept in browser localStorage for convenience; the PHP endpoints currently return no token/session cookie. Do not deploy publicly until server-side authorization is implemented for admin-only write endpoints.
- The website uses your existing PHP endpoint request/response shapes. It does not alter your PostgreSQL schema.
- `categories.php` and `customers.php` in the existing API appear to use mysqli methods while `db.php` is PDO. This site uses the PDO-oriented `get_categories.php` and `get_customers.php` endpoints instead.
- Test first on localhost. To use a phone or another computer, the API base URL must use the host computer's LAN IP, and Apache/firewall/network access must be configured.
