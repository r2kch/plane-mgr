CREATE TABLE roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE
);

INSERT INTO roles (name) VALUES ('admin'), ('pilot'), ('accounting');

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  street VARCHAR(150) NULL,
  house_number VARCHAR(20) NULL,
  postal_code VARCHAR(20) NULL,
  city VARCHAR(100) NULL,
  country_code CHAR(2) NOT NULL DEFAULT 'CH',
  phone VARCHAR(50) NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE user_roles (
  user_id INT NOT NULL,
  role_id INT NOT NULL,
  PRIMARY KEY (user_id, role_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);

CREATE TABLE aircraft (
  id INT AUTO_INCREMENT PRIMARY KEY,
  immatriculation VARCHAR(30) NOT NULL UNIQUE,
  type VARCHAR(100) NOT NULL,
  aircraft_group_id INT NULL,
  status ENUM('active', 'disabled', 'maintenance') NOT NULL DEFAULT 'active',
  start_hobbs DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  start_landings INT NOT NULL DEFAULT 1,
  base_hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE aircraft_groups (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE user_aircraft_groups (
  user_id INT NOT NULL,
  group_id INT NOT NULL,
  PRIMARY KEY (user_id, group_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (group_id) REFERENCES aircraft_groups(id) ON DELETE CASCADE
);

CREATE TABLE aircraft_user_rates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  aircraft_id INT NOT NULL,
  user_id INT NOT NULL,
  hourly_rate DECIMAL(10,2) NOT NULL,
  UNIQUE KEY uniq_aircraft_user (aircraft_id, user_id),
  FOREIGN KEY (aircraft_id) REFERENCES aircraft(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE reservations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  aircraft_id INT NOT NULL,
  user_id INT NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  hours DECIMAL(8,2) NOT NULL,
  notes VARCHAR(500) NULL,
  status ENUM('booked', 'cancelled', 'completed') NOT NULL DEFAULT 'booked',
  invoice_id INT NULL,
  created_by INT NOT NULL,
  cancelled_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (aircraft_id) REFERENCES aircraft(id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  FOREIGN KEY (cancelled_by) REFERENCES users(id)
);

CREATE TABLE reservation_flights (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reservation_id INT NOT NULL,
  pilot_user_id INT NOT NULL,
  from_airfield VARCHAR(10) NOT NULL,
  to_airfield VARCHAR(10) NOT NULL,
  start_time DATETIME NOT NULL,
  landing_time DATETIME NOT NULL,
  landings_count INT NOT NULL DEFAULT 1,
  hobbs_start DECIMAL(10,2) NOT NULL,
  hobbs_end DECIMAL(10,2) NOT NULL,
  hobbs_hours DECIMAL(10,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
  FOREIGN KEY (pilot_user_id) REFERENCES users(id)
);

CREATE TABLE invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_number VARCHAR(50) NOT NULL UNIQUE,
  user_id INT NOT NULL,
  period_from DATE NOT NULL,
  period_to DATE NOT NULL,
  total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  payment_status ENUM('open', 'part_paid', 'paid', 'overdue') NOT NULL DEFAULT 'open',
  pdf_path VARCHAR(255) NULL,
  mailed_at DATETIME NULL,
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE invoice_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT NOT NULL,
  reservation_id INT NOT NULL,
  description VARCHAR(255) NOT NULL,
  hours DECIMAL(8,2) NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  line_total DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
  FOREIGN KEY (reservation_id) REFERENCES reservations(id)
);

ALTER TABLE reservations
  ADD CONSTRAINT fk_reservations_invoice
  FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL;

ALTER TABLE aircraft
  ADD CONSTRAINT fk_aircraft_group
  FOREIGN KEY (aircraft_group_id) REFERENCES aircraft_groups(id) ON DELETE SET NULL;

CREATE TABLE audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  actor_user_id INT NOT NULL,
  action VARCHAR(120) NOT NULL,
  entity VARCHAR(80) NOT NULL,
  entity_id INT NULL,
  meta_json TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (actor_user_id) REFERENCES users(id)
);
