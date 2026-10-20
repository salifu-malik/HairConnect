CREATE DATABASE IF NOT EXISTS booking_db;
USE booking_db;

CREATE TABLE IF NOT EXISTS shops (
                                     id INT AUTO_INCREMENT PRIMARY KEY,
                                     owner_id INT NOT NULL,
                                     name VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

CREATE TABLE IF NOT EXISTS barbers (
                                       id INT AUTO_INCREMENT PRIMARY KEY,
                                       user_id INT NOT NULL,
                                       shop_id INT NOT NULL,
                                       experience INT DEFAULT 0,
                                       speciality VARCHAR(255),
    status ENUM('active', 'inactive', 'on_leave') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

CREATE TABLE IF NOT EXISTS services (
                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                        shop_id INT NOT NULL,
                                        barber_id INT NULL,
                                        name VARCHAR(255) NOT NULL,
    description TEXT,
    duration_minutes INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

CREATE TABLE IF NOT EXISTS barber_schedule (
                                               id INT AUTO_INCREMENT PRIMARY KEY,
                                               barber_id INT NOT NULL,
                                               day ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL
    );

CREATE TABLE IF NOT EXISTS appointments (
                                            id INT AUTO_INCREMENT PRIMARY KEY,
                                            customer_id INT NOT NULL,
                                            shop_id INT NOT NULL,
                                            barber_id INT NOT NULL,
                                            service_id INT NOT NULL,
                                            appointment_date DATE NOT NULL,
                                            appointment_time TIME NOT NULL,
                                            status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

CREATE TABLE IF NOT EXISTS queues (
                                      id INT AUTO_INCREMENT PRIMARY KEY,
                                      shop_id INT NOT NULL,
                                      customer_id INT NOT NULL,
                                      queue_number INT NOT NULL,
                                      status ENUM('waiting', 'in_progress', 'completed', 'cancelled') DEFAULT 'waiting',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );


USE booking_db;

# Update Shops
ALTER TABLE shops
    ADD COLUMN approval_status ENUM(
        'pending',
        'approved',
        'rejected'
        ) NOT NULL DEFAULT 'pending'
        AFTER description;

ALTER TABLE shops
    ADD COLUMN approved_at TIMESTAMP NULL DEFAULT NULL
        AFTER approval_status;

ALTER TABLE shops
    ADD COLUMN approved_by INT NULL DEFAULT NULL
        AFTER approved_at;

ALTER TABLE shops
    MODIFY COLUMN status ENUM(
    'active',
    'inactive',
    'suspended'
    ) NOT NULL DEFAULT 'inactive';

ALTER TABLE shops
    ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP
    AFTER created_at;

ALTER TABLE shops
    ADD INDEX idx_shops_owner (owner_id);

ALTER TABLE shops
    ADD INDEX idx_shops_approval (approval_status);

ALTER TABLE shops
    ADD INDEX idx_shops_status (status);


#Update Barbers
ALTER TABLE barbers
    ADD COLUMN approval_status ENUM(
        'pending',
        'approved',
        'rejected'
        ) NOT NULL DEFAULT 'pending'
        AFTER speciality;

ALTER TABLE barbers
    ADD COLUMN approved_at TIMESTAMP NULL DEFAULT NULL
        AFTER approval_status;

ALTER TABLE barbers
    ADD COLUMN approved_by INT NULL DEFAULT NULL
        AFTER approved_at;

ALTER TABLE barbers
    MODIFY COLUMN status ENUM(
    'active',
    'inactive',
    'on_leave'
    ) NOT NULL DEFAULT 'inactive';

ALTER TABLE barbers
    ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP
    AFTER created_at;

ALTER TABLE barbers
    ADD INDEX idx_barbers_user (user_id);

ALTER TABLE barbers
    ADD INDEX idx_barbers_shop (shop_id);

ALTER TABLE barbers
    ADD INDEX idx_barbers_approval (approval_status);

ALTER TABLE barbers
    ADD INDEX idx_barbers_status (status);


#Update Services
ALTER TABLE services
    ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP
    AFTER created_at;

ALTER TABLE services
    ADD INDEX idx_services_shop (shop_id);

ALTER TABLE services
    ADD INDEX idx_services_barber (barber_id);

ALTER TABLE services
    ADD INDEX idx_services_status (status);


#Udate Barber Schedule
ALTER TABLE barber_schedule
    ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    AFTER end_time;

ALTER TABLE barber_schedule
    ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP
    AFTER created_at;

ALTER TABLE barber_schedule
    ADD INDEX idx_schedule_barber (barber_id);

ALTER TABLE barber_schedule
    ADD INDEX idx_schedule_day (day);



#Update Appointments
ALTER TABLE appointments
    ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP
    AFTER created_at;

ALTER TABLE appointments
    ADD INDEX idx_appointments_customer (customer_id);

ALTER TABLE appointments
    ADD INDEX idx_appointments_shop (shop_id);

ALTER TABLE appointments
    ADD INDEX idx_appointments_barber (barber_id);

ALTER TABLE appointments
    ADD INDEX idx_appointments_service (service_id);

ALTER TABLE appointments
    ADD INDEX idx_appointments_datetime (
                                         barber_id,
                                         appointment_date,
                                         appointment_time
        );

ALTER TABLE appointments
    ADD INDEX idx_appointments_status (status);



# Update Queues
ALTER TABLE queues
    ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP
    AFTER created_at;

ALTER TABLE queues
    ADD INDEX idx_queues_shop (shop_id);

ALTER TABLE queues
    ADD INDEX idx_queues_customer (customer_id);

ALTER TABLE queues
    ADD INDEX idx_queues_status (status);


#Changing shop_id to null
USE booking_db;

ALTER TABLE barbers
    MODIFY COLUMN shop_id INT NULL;


#Creating a table for independent barber home service
USE booking_db;

CREATE TABLE IF NOT EXISTS barber_home_services (
                                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                                    barber_id INT NOT NULL,
                                                    name VARCHAR(255) NOT NULL,
    description TEXT,
    duration_minutes INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_home_service_barber
    FOREIGN KEY (barber_id)
    REFERENCES barbers(id)
    ON DELETE CASCADE,

    INDEX idx_home_services_barber_id (barber_id),
    INDEX idx_home_services_status (status)
    );


#Add service location
USE booking_db;

ALTER TABLE barber_schedule
    ADD COLUMN service_location
        ENUM('SHOP', 'HOME')
        NOT NULL DEFAULT 'SHOP'
        AFTER day;


#Prevent duplicate schedule
USE booking_db;

ALTER TABLE barber_schedule
    ADD UNIQUE KEY unique_barber_schedule (
    barber_id,
    day,
    service_location

    );




#Prevent multiple baberber profile
USE booking_db;

ALTER TABLE barbers
    ADD UNIQUE KEY unique_barber_user (user_id);

DESCRIBE barbers;


#Making shop_id nullable
ALTER TABLE appointments
    MODIFY COLUMN shop_id INT NULL;


ALTER TABLE appointments
    ADD COLUMN service_location ENUM('SHOP', 'HOME')
                                    NOT NULL DEFAULT 'SHOP'
        AFTER service_id,
    ADD COLUMN duration_minutes INT NOT NULL DEFAULT 0
        AFTER service_location;


ALTER TABLE appointments
    ADD COLUMN booking_code CHAR(5) NULL AFTER status,
    ADD COLUMN checked_in_at TIMESTAMP NULL AFTER booking_code,
    ADD COLUMN checked_in_by INT NULL AFTER checked_in_at,
    ADD INDEX idx_booking_verification (
                                        shop_id,
                                        appointment_date,
                                        booking_code
        );


#Created barber booking locks
SELECT
    b.id AS barber_id,
    b.user_id
FROM booking_db.barbers AS b
WHERE b.id <> 1
    LIMIT 5;

USE booking_db;

ALTER TABLE appointments
    MODIFY COLUMN status ENUM(
    'pending',
    'confirmed',
    'checked_in',
    'in_progress',
    'completed',
    'cancelled'
    ) NOT NULL DEFAULT 'pending';

USE booking_db;

ALTER TABLE appointments
    ADD COLUMN completed_at TIMESTAMP NULL AFTER checked_in_by;


CREATE TABLE IF NOT EXISTS customer_booking_restrictions (
                                                             id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                                                             customer_id BIGINT UNSIGNED NOT NULL,
                                                             restriction_start_date DATE NOT NULL,
                                                             restriction_end_date DATE NOT NULL,
                                                             reason VARCHAR(255) NOT NULL DEFAULT 'Appointment cancellation',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customer_restriction_dates (
                                             customer_id,
                                             restriction_start_date,
                                             restriction_end_date
                                         )
    );


USE booking_db;

ALTER TABLE shops
    ADD COLUMN no_show_grace_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 15;

