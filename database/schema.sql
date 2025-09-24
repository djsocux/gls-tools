-- GLS Package Pickup System Database Schema
-- SQLite Database

-- Clients table - for customers who request pickups
CREATE TABLE clients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token VARCHAR(64) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    address TEXT NOT NULL,
    city VARCHAR(100) NOT NULL,
    postal_code VARCHAR(10) NOT NULL,
    active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Users table - for admin and delivery drivers
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL CHECK (role IN ('admin', 'delivery')),
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(20),
    active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Pickups table - main pickup requests
CREATE TABLE pickups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER NOT NULL,
    status VARCHAR(30) DEFAULT 'pendiente_confirmar' CHECK (status IN ('pendiente_confirmar', 'confirmada', 'sin_asignar', 'asignada', 'en_ruta', 'no_mercancia', 'hecho', 'incidencia', 'vehiculo_no_apropiado')),
    assigned_to INTEGER NULL,
    pickup_date DATE,
    pickup_time VARCHAR(20),
    notes TEXT,
    confirmed_at DATETIME NULL,
    assigned_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id)
);

-- Packages table - individual packages within a pickup
CREATE TABLE packages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    pickup_id INTEGER NOT NULL,
    tracking_number VARCHAR(50) NULL,
    recipient_name VARCHAR(255) NOT NULL,
    recipient_phone VARCHAR(20) NOT NULL,
    recipient_address TEXT NOT NULL,
    recipient_city VARCHAR(100) NOT NULL,
    recipient_postal_code VARCHAR(10) NOT NULL,
    weight DECIMAL(10,2),
    dimensions VARCHAR(50),
    quantity INTEGER DEFAULT 1,
    service_type VARCHAR(50),
    observations TEXT,
    barcode_pickup VARCHAR(50) UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pickup_id) REFERENCES pickups(id)
);

-- Pickup status history
CREATE TABLE pickup_status_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    pickup_id INTEGER NOT NULL,
    previous_status VARCHAR(50),
    new_status VARCHAR(50) NOT NULL,
    changed_by INTEGER,
    changed_by_type VARCHAR(20) NOT NULL CHECK (changed_by_type IN ('client', 'admin', 'delivery')),
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pickup_id) REFERENCES pickups(id),
    FOREIGN KEY (changed_by) REFERENCES users(id)
);

-- System configuration
CREATE TABLE config (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    config_key VARCHAR(100) UNIQUE NOT NULL,
    config_value TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Insert default admin user (password: admin123 - should be changed in production)
INSERT INTO users (username, password, role, name, email) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Administrador', 'admin@gls-tools.com');

-- Insert default configuration
INSERT INTO config (config_key, config_value) VALUES 
('company_name', 'GLS Tools'),
('company_address', 'Dirección de la oficina'),
('company_phone', '123-456-789'),
('pickup_time_slots', '09:00-10:00,10:00-11:00,11:00-12:00,14:00-15:00,15:00-16:00,16:00-17:00');