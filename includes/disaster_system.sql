
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(255) NOT NULL,
    id_number VARCHAR(50) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('citizen', 'admin', 'authority') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE authorities (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    contact_phone VARCHAR(20),
    coverage_area TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE disasters (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    authority_id INT,
    type ENUM('fire', 'flood', 'landslide', 'earthquake', 'other') NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    description TEXT,
    image_path VARCHAR(500),
    status ENUM('pending', 'approved', 'in_progress', 'resolved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (authority_id) REFERENCES authorities(id)
);

CREATE TABLE announcements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE message_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    disaster_id INT,
    phone_number VARCHAR(20) NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (disaster_id) REFERENCES disasters(id)
);-- Demo admin account (password: "password")

INSERT INTO users VALUES (1, 'System Admin', 'ADMIN001', '0712345678', 'admin@system.ke',
'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NOW());-- Sample authorities
INSERT INTO authorities (name, contact_phone, coverage_area, status) VALUES
('Nairobi Fire Dept', '0722345678', 'Nairobi County', 'active'),
('Kenya Red Cross', '0733456789', 'Nationwide', 'active'),
('Nairobi Police', '0744567890', 'Nairobi CBD', 'active');

-- This is a test