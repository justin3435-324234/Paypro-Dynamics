# PAY PRO DYNAMICS — Setup & Testing Guide

## 1. XAMPP Setup
1. Install XAMPP (PHP 8.x + MariaDB). Start **Apache** and **MySQL** in XAMPP Control Panel.
2. Copy this folder to: `C:\xampp\htdocs\pay-pro-dynamics\`
   (If your folder is named `Paypro Dynamics`, rename to `pay-pro-dynamics` for clean URLs.)
3. Open phpMyAdmin: `http://localhost/phpmyadmin`
4. Create/import:
   - Click **Import** → choose `database/pay_pro_dynamics.sql` → **Go**.
   - This creates DB `pay_pro_dynamics` + tables + seed departments/positions/employees/settings.
5. Set real admin passwords (the .sql has placeholder hashes):
   - Visit `http://localhost/pay-pro-dynamics/database/seed_admin.php` **once**.
   - Then **DELETE** `database/seed_admin.php`.
6. Open the app: `http://localhost/pay-pro-dynamics/login.php`

## 2. Default Accounts
| Username | Password | Role |
|---|---|---|
| admin | Admin123! | ADMIN (full access) |
| manager | Manager123! | MANAGER (attendance+payroll+reports) |
| employee | Employee123! | EMPLOYEE (own data only, linked to EMP-0001) |

Change passwords after first login via Settings → Security.

## 3. Project URL Map
- Login `/login.php`, Dashboard `/dashboard.php`
- Employees `/employees/` (index/add/edit/view/delete)
- Departments `/departments/`, Positions `/positions/`
- Attendance `/attendance/`, Deductions `/deductions/`
- Payroll `/payroll/` (index/create/view/process/payslip)
- Reports `/reports/` (+ `export.php` CSV), Users `/users/`, Settings `/settings/`

## 4. Payroll Calculation (server-authoritative)
- Monthly: regular = basic/2 per cut-off. Daily: basic × days_worked. Hourly: hours × hourly_rate.
- Manual "Regular Base" field overrides (for adjustments); 0 = auto-resolve.
- overtime_pay = overtime_hours × overtime_rate; gross = regular + OT; net = gross − total_deductions (pending deductions in period auto-applied, then marked applied).
- Net < 0 rejected. Draft → Processed → Paid (locked); reopen logged. Only Draft deletable.

## 5. Testing Checklist
- LOGIN: correct / wrong password / disabled account / remember-me / logout / direct dashboard URL without login redirects.
- EMPLOYEES: add (duplicate code/email blocked), edit, view profile, deactivate, search + dept/status filter, validation (bad email, negative salary).
- DEPARTMENTS/POSITIONS: create, duplicate blocked, delete blocked when employees assigned.
- ATTENDANCE: add (duplicate employee+date blocked), edit, delete, filter by employee/date/month, auto hours from Time In/Out.
- DEDUCTIONS: add Tax/SSS/PhilHealth/Pag-IBIG/Loan lines, amount ≤ 0 rejected, filter.
- PAYROLL: create → verify gross/ded/net math, duplicate period blocked, negative rejected, Review → Process (confirm) → locked edit, Paid, reopen (admin), payslip print, history per employee.
- USERS/RBAC: create roles, login as manager (no Users/Settings-company), employee sees only own data, direct URL to /users as manager → 403.
- REPORTS: generate each of 6 tabs, Print, Export CSV matches DB.
- RESPONSIVE: 1366px laptop, 768px tablet (hamburger), tables scroll, payslip prints clean (nav hidden).

## 6. Implemented Modules (all functional, no placeholders)
Auth + Dashboard (Chart.js) + Employees + Profile + Departments + Positions + Attendance + Deductions + Payroll + Payslip + Reports + Users + Settings + Activity logs + Toasts/confirms/empty states + PDO prepared statements + password_hash + CSRF on deletes/process/user-save.
