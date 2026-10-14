-- =====================================================
-- PAY PRO DYNAMICS — Database Script
-- Import via phpMyAdmin or: mysql -u root < pay_pro_dynamics.sql
-- =====================================================
CREATE DATABASE IF NOT EXISTS pay_pro_dynamics
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE pay_pro_dynamics;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS payroll_items;
DROP TABLE IF EXISTS payroll;
DROP TABLE IF EXISTS employee_deductions;
DROP TABLE IF EXISTS deduction_types;
DROP TABLE IF EXISTS attendance;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS employees;
DROP TABLE IF EXISTS positions;
DROP TABLE IF EXISTS departments;
DROP TABLE IF EXISTS settings;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------- Departments ----------
CREATE TABLE departments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  description TEXT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------- Positions ----------
CREATE TABLE positions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  department_id INT NOT NULL,
  title VARCHAR(100) NOT NULL,
  description TEXT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pos_dept_title (department_id, title),
  CONSTRAINT fk_pos_dept FOREIGN KEY (department_id)
    REFERENCES departments(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------- Employees ----------
CREATE TABLE employees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_code VARCHAR(20) NOT NULL UNIQUE,
  first_name VARCHAR(60) NOT NULL,
  middle_name VARCHAR(60) NULL,
  last_name VARCHAR(60) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  phone VARCHAR(20) NULL,
  address TEXT NULL,
  date_of_birth DATE NULL,
  date_hired DATE NOT NULL,
  department_id INT NOT NULL,
  position_id INT NOT NULL,
  employment_status ENUM('Regular','Probationary','Contractual','Resigned') NOT NULL DEFAULT 'Regular',
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  salary_type ENUM('monthly','daily','hourly') NOT NULL DEFAULT 'monthly',
  basic_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
  hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_emp_dept FOREIGN KEY (department_id)
    REFERENCES departments(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_emp_pos FOREIGN KEY (position_id)
    REFERENCES positions(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CHECK (basic_salary >= 0),
  CHECK (hourly_rate >= 0)
) ENGINE=InnoDB;

-- ---------- Users ----------
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('ADMIN','MANAGER','EMPLOYEE') NOT NULL DEFAULT 'EMPLOYEE',
  employee_id INT NULL,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  remember_token VARCHAR(64) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_emp FOREIGN KEY (employee_id)
    REFERENCES employees(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------- Attendance ----------
CREATE TABLE attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  date DATE NOT NULL,
  time_in TIME NULL,
  time_out TIME NULL,
  regular_hours DECIMAL(5,2) NOT NULL DEFAULT 0,
  overtime_hours DECIMAL(5,2) NOT NULL DEFAULT 0,
  late_hours DECIMAL(5,2) NOT NULL DEFAULT 0,
  status ENUM('Present','Absent','Late','Leave') NOT NULL DEFAULT 'Present',
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_att_emp_date (employee_id, date),
  CONSTRAINT fk_att_emp FOREIGN KEY (employee_id)
    REFERENCES employees(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CHECK (regular_hours >= 0),
  CHECK (overtime_hours >= 0),
  CHECK (late_hours >= 0)
) ENGINE=InnoDB;

-- ---------- Deduction types ----------
CREATE TABLE deduction_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(30) NOT NULL UNIQUE,
  description VARCHAR(255) NULL
) ENGINE=InnoDB;

INSERT INTO deduction_types (name, description) VALUES
('Tax','Withholding tax'),
('SSS','Social Security System'),
('PhilHealth','PhilHealth contribution'),
('Pag-IBIG','Pag-IBIG / HDMF contribution'),
('Loan','Salary / company loan'),
('Absence','Absence deduction'),
('Late','Late / undertime deduction'),
('Other','Other deduction');

-- ---------- Employee deductions ----------
CREATE TABLE employee_deductions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  deduction_type_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  description TEXT NULL,
  deduction_date DATE NOT NULL,
  status ENUM('pending','applied','cancelled') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ed_emp FOREIGN KEY (employee_id)
    REFERENCES employees(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_ed_type FOREIGN KEY (deduction_type_id)
    REFERENCES deduction_types(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CHECK (amount > 0)
) ENGINE=InnoDB;

-- ---------- Payroll ----------
CREATE TABLE payroll (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  period_label VARCHAR(40) NOT NULL,
  basic_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
  days_worked DECIMAL(5,2) NOT NULL DEFAULT 0,
  hours_per_day DECIMAL(4,2) NOT NULL DEFAULT 8,
  regular_hours DECIMAL(7,2) NOT NULL DEFAULT 0,
  regular_pay DECIMAL(12,2) NOT NULL DEFAULT 0,
  overtime_hours DECIMAL(7,2) NOT NULL DEFAULT 0,
  overtime_rate DECIMAL(10,2) NOT NULL DEFAULT 0,
  overtime_pay DECIMAL(12,2) NOT NULL DEFAULT 0,
  allowances DECIMAL(12,2) NOT NULL DEFAULT 0,
  gross_pay DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
  net_pay DECIMAL(12,2) NOT NULL DEFAULT 0,
  status ENUM('Draft','Processed','Paid') NOT NULL DEFAULT 'Draft',
  processed_by INT NULL,
  processed_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pay_emp_period (employee_id, period_start, period_end),
  CONSTRAINT fk_pay_emp FOREIGN KEY (employee_id)
    REFERENCES employees(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_pay_user FOREIGN KEY (processed_by)
    REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
  CHECK (days_worked >= 0),
  CHECK (regular_hours >= 0),
  CHECK (overtime_hours >= 0),
  CHECK (allowances >= 0),
  CHECK (net_pay >= 0)
) ENGINE=InnoDB;

-- ---------- Payroll items (breakdown) ----------
CREATE TABLE payroll_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  payroll_id INT NOT NULL,
  item_type ENUM('earning','deduction') NOT NULL,
  description VARCHAR(150) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  CONSTRAINT fk_pi_pay FOREIGN KEY (payroll_id)
    REFERENCES payroll(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CHECK (amount >= 0)
) ENGINE=InnoDB;

-- ---------- Settings (key/value) ----------
CREATE TABLE settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(50) NOT NULL UNIQUE,
  setting_value TEXT NULL
) ENGINE=InnoDB;

INSERT INTO settings (setting_key, setting_value) VALUES
('company_name','Pay Pro Dynamics Inc.'),
('company_address','Makati City, Metro Manila, Philippines'),
('contact_number','(02) 8555-0100'),
('company_email','hr@paypro-dynamics.local'),
('default_working_hours','8'),
('default_overtime_rate','150'),
('payroll_frequency','semi-monthly');

-- ---------- Activity logs ----------
CREATE TABLE activity_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(100) NOT NULL,
  details TEXT NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_log_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- SEED DATA
-- =====================================================
INSERT INTO departments (name, description, status) VALUES
('Human Resources','Recruitment and employee relations','active'),
('Information Technology','Systems and infrastructure','active'),
('Finance','Accounting and payroll','active'),
('Operations','Daily business operations','active');

INSERT INTO positions (department_id, title, description, status) VALUES
(1,'HR Officer','Handles hiring and records','active'),
(2,'Software Developer','Builds and maintains systems','active'),
(2,'IT Support','Helpdesk and hardware','active'),
(3,'Accountant','Payroll and bookkeeping','active'),
(4,'Operations Staff','Frontline operations','active'),
(4,'Team Leader','Supervises operations team','active');

INSERT INTO employees (employee_code, first_name, middle_name, last_name, email, phone, address, date_of_birth, date_hired, department_id, position_id, employment_status, status, salary_type, basic_salary, hourly_rate) VALUES
('EMP-0001','Juan','Santos','Dela Cruz','juan.delacruz@example.com','09171234567','Quezon City, Metro Manila','1990-05-12','2022-01-15',2,2,'Regular','active','monthly',45000.00,281.25),
('EMP-0002','Maria','Reyes','Santos','maria.santos@example.com','09181234567','Makati City, Metro Manila','1992-08-20','2022-06-01',3,4,'Regular','active','monthly',38000.00,237.50),
('EMP-0003','Jose','Mendoza','Rizal','jose.rizal@example.com','09191234567','Caloocan City, Metro Manila','1995-03-10','2023-02-01',4,5,'Probationary','active','daily',750.00,93.75),
('EMP-0004','Ana','Bautista','Garcia','ana.garcia@example.com','09201234567','Pasig City, Metro Manila','1993-11-25','2021-09-10',1,1,'Regular','active','monthly',32000.00,200.00),
('EMP-0005','Mark','Villanueva','Torres','mark.torres@example.com','09211234567','Taguig City, Metro Manila','1998-07-07','2024-01-08',2,3,'Contractual','active','hourly',0.00,150.00);

-- Default users (password: Admin123! / Manager123! / Employee123!)
-- Hashes generated with PHP password_hash('...', PASSWORD_DEFAULT) — ready to login right after import.
INSERT INTO users (username, email, password_hash, role, employee_id, status) VALUES
('admin','admin@paypro.local','$2y$10$ci.qZ0WSg8xWzNbVDCH2BeM0hUU3RJKT0es.m/EnDqnzhSJolgBOi', 'ADMIN', NULL, 'active'),
('manager','manager@paypro.local','$2y$10$GILJ9DTSQJiS2LzRw.HOVu6wm1jsaQ15yXl/kRz1Y/quM5fmi/56K','MANAGER', NULL, 'active'),
('employee','employee@paypro.local','$2y$10$MmWAJNX2pZB.LrwdVMqDA.EC8uICoSCjM/atGOLVL7kv6NrAZW.KW','EMPLOYEE', NULL, 'active');

-- OPTIONAL: database/seed_admin.php can still be run once to reset passwords if needed.
