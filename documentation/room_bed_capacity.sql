-- Room bed capacity migration (MySQL/MariaDB)
-- Goal:
-- 1) Add capacity per room
-- 2) Enforce max capacity of 4 beds per room
-- 3) Prevent inserts/updates in beds beyond room capacity at DB level

ALTER TABLE rooms
    ADD COLUMN bed_capacity TINYINT UNSIGNED NOT NULL DEFAULT 4 AFTER room_number;

UPDATE rooms
SET bed_capacity = 4
WHERE bed_capacity IS NULL OR bed_capacity < 1 OR bed_capacity > 4;

ALTER TABLE rooms
    MODIFY bed_capacity TINYINT UNSIGNED NOT NULL DEFAULT 4;

-- Optional but recommended: capacity range guard
-- (MySQL 8.0+ and modern MariaDB enforce CHECK constraints)
ALTER TABLE rooms
    ADD CONSTRAINT chk_rooms_bed_capacity CHECK (bed_capacity BETWEEN 1 AND 4);

-- Optional but recommended: avoid duplicate bed numbers inside one room
ALTER TABLE beds
    ADD UNIQUE KEY uq_beds_room_bed_number (room_id, bed_number);

DROP TRIGGER IF EXISTS trg_beds_before_insert_capacity;
DELIMITER $$
CREATE TRIGGER trg_beds_before_insert_capacity
BEFORE INSERT ON beds
FOR EACH ROW
BEGIN
    DECLARE room_capacity INT DEFAULT 4;
    DECLARE current_beds INT DEFAULT 0;

    SELECT COALESCE(r.bed_capacity, 4)
      INTO room_capacity
      FROM rooms r
     WHERE r.id = NEW.room_id
     LIMIT 1;

    IF room_capacity < 1 THEN
        SET room_capacity = 1;
    END IF;
    IF room_capacity > 4 THEN
        SET room_capacity = 4;
    END IF;

    SELECT COUNT(*)
      INTO current_beds
      FROM beds b
     WHERE b.room_id = NEW.room_id;

    IF current_beds >= room_capacity THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Room capacity exceeded.';
    END IF;
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_beds_before_update_capacity;
DELIMITER $$
CREATE TRIGGER trg_beds_before_update_capacity
BEFORE UPDATE ON beds
FOR EACH ROW
BEGIN
    DECLARE room_capacity INT DEFAULT 4;
    DECLARE current_beds INT DEFAULT 0;

    SELECT COALESCE(r.bed_capacity, 4)
      INTO room_capacity
      FROM rooms r
     WHERE r.id = NEW.room_id
     LIMIT 1;

    IF room_capacity < 1 THEN
        SET room_capacity = 1;
    END IF;
    IF room_capacity > 4 THEN
        SET room_capacity = 4;
    END IF;

    SELECT COUNT(*)
      INTO current_beds
      FROM beds b
     WHERE b.room_id = NEW.room_id
       AND b.id <> OLD.id;

    IF (current_beds + 1) > room_capacity THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Room capacity exceeded.';
    END IF;
END$$
DELIMITER ;
