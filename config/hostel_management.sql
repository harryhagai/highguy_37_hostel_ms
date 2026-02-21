-- Hostel Management schema
-- Model: hostel -> room -> bed
-- Booking target: bed (not room), for a date range.

CREATE DATABASE IF NOT EXISTS hostel_management
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE hostel_management;

-- Users
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    profile_photo VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Hostels
CREATE TABLE IF NOT EXISTS hostels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    location VARCHAR(150),
    gender ENUM('male', 'female', 'all') NOT NULL DEFAULT 'all',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    hostel_image VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Shared Room Image Library
-- One image can be linked to many rooms via rooms.room_image_id
CREATE TABLE IF NOT EXISTS room_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image_path VARCHAR(255) NOT NULL,
    image_label VARCHAR(120) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Rooms
CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hostel_id INT NOT NULL,
    room_number VARCHAR(20) NOT NULL,
    room_type VARCHAR(50),
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    description TEXT,
    room_image_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_room_per_hostel UNIQUE (hostel_id, room_number),
    CONSTRAINT fk_rooms_hostel
        FOREIGN KEY (hostel_id) REFERENCES hostels(id) ON DELETE CASCADE,
    CONSTRAINT fk_rooms_room_image
        FOREIGN KEY (room_image_id) REFERENCES room_images(id) ON DELETE SET NULL
);

-- Beds
CREATE TABLE IF NOT EXISTS beds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    bed_number VARCHAR(20) NOT NULL,
    status ENUM('active', 'maintenance', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_bed_per_room UNIQUE (room_id, bed_number),
    CONSTRAINT fk_beds_room
        FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);

CREATE INDEX idx_beds_room_status ON beds (room_id, status);

-- Bookings (bed-level)
-- end_date is treated as checkout date (exclusive)
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    bed_id INT NOT NULL,
    status ENUM('pending', 'confirmed', 'cancelled', 'completed') NOT NULL DEFAULT 'pending',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_booking_dates CHECK (end_date > start_date),
    CONSTRAINT fk_bookings_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_bookings_bed
        FOREIGN KEY (bed_id) REFERENCES beds(id) ON DELETE CASCADE
);

CREATE INDEX idx_booking_bed_dates ON bookings (bed_id, start_date, end_date);
CREATE INDEX idx_booking_user_dates ON bookings (user_id, start_date, end_date);

-- Notices
CREATE TABLE IF NOT EXISTS notices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Prevent overlapping bookings for the same bed
DROP TRIGGER IF EXISTS trg_bookings_prevent_overlap_insert;
DROP TRIGGER IF EXISTS trg_bookings_prevent_overlap_update;

DELIMITER $$

CREATE TRIGGER trg_bookings_prevent_overlap_insert
BEFORE INSERT ON bookings
FOR EACH ROW
BEGIN
    IF NEW.end_date <= NEW.start_date THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Invalid booking range: end_date must be after start_date.';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM beds
        WHERE id = NEW.bed_id
          AND status <> 'active'
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Cannot book a bed that is not active.';
    END IF;

    IF NEW.status IN ('pending', 'confirmed') AND EXISTS (
        SELECT 1
        FROM bookings b
        WHERE b.bed_id = NEW.bed_id
          AND b.status IN ('pending', 'confirmed')
          AND NEW.start_date < b.end_date
          AND NEW.end_date > b.start_date
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Bed already booked for the selected date range.';
    END IF;
END$$

CREATE TRIGGER trg_bookings_prevent_overlap_update
BEFORE UPDATE ON bookings
FOR EACH ROW
BEGIN
    IF NEW.end_date <= NEW.start_date THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Invalid booking range: end_date must be after start_date.';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM beds
        WHERE id = NEW.bed_id
          AND status <> 'active'
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Cannot book a bed that is not active.';
    END IF;

    IF NEW.status IN ('pending', 'confirmed') AND EXISTS (
        SELECT 1
        FROM bookings b
        WHERE b.bed_id = NEW.bed_id
          AND b.id <> NEW.id
          AND b.status IN ('pending', 'confirmed')
          AND NEW.start_date < b.end_date
          AND NEW.end_date > b.start_date
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Bed already booked for the selected date range.';
    END IF;
END$$

DELIMITER ;

-- Optional helper view: bed summary per room
CREATE OR REPLACE VIEW v_room_bed_summary AS
SELECT
    r.id AS room_id,
    r.hostel_id,
    r.room_number,
    COUNT(b.id) AS total_beds,
    SUM(CASE WHEN b.status = 'active' THEN 1 ELSE 0 END) AS active_beds
FROM rooms r
LEFT JOIN beds b ON b.room_id = r.id
GROUP BY r.id, r.hostel_id, r.room_number;

