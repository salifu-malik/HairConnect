CREATE DATABASE IF NOT EXISTS users_db;
USE users_db;

CREATE TABLE IF NOT EXISTS roles (
                                     id INT AUTO_INCREMENT PRIMARY KEY,
                                     name VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS users (
                                     id INT AUTO_INCREMENT PRIMARY KEY,
                                     first_name VARCHAR(100) NOT NULL,
                                     last_name VARCHAR(100) NOT NULL,
                                     email VARCHAR(255) NOT NULL UNIQUE,
                                     phone VARCHAR(20),
                                     password_hash VARCHAR(255) NOT NULL,
                                     profile_image VARCHAR(255),
                                     status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
                                     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                     updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS permissions (
                                           id INT AUTO_INCREMENT PRIMARY KEY,
                                           name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS user_roles (
                                          id INT AUTO_INCREMENT PRIMARY KEY,
                                          user_id INT NOT NULL,
                                          role_id INT NOT NULL
);

CREATE TABLE IF NOT EXISTS sessions (
                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                        user_id INT NOT NULL,
                                        refresh_token VARCHAR(255) NOT NULL,
                                        expires_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS audit_logs (
                                          id INT AUTO_INCREMENT PRIMARY KEY,
                                          user_id INT,
                                          action VARCHAR(255) NOT NULL,
                                          ip_address VARCHAR(45),
                                          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE  IF NOT EXISTS password_reset_requests (
                                                        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                                                        user_id INT  NULL,

                                                        email VARCHAR(255) NOT NULL,

                                                        code_hash VARCHAR(255) NOT NULL,

                                                        expires_at DATETIME NOT NULL,

                                                        last_sent_at DATETIME NOT NULL,

                                                        attempts INT NOT NULL DEFAULT 0,

                                                        blocked_until DATETIME NULL,

                                                        verified_at DATETIME NULL,

                                                        reset_token_hash VARCHAR(255) NULL,

                                                        reset_token_expires_at DATETIME NULL,

                                                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                                                        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                            ON UPDATE CURRENT_TIMESTAMP,

                                                        INDEX idx_password_reset_email (email),
                                                        INDEX idx_password_reset_user_id (user_id)

);



CREATE TABLE  IF NOT EXISTS professional_profiles (
                                                      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                                                      user_id INT NOT NULL,

                                                      professional_type VARCHAR(50) NOT NULL,

                                                      bio TEXT DEFAULT NULL,
                                                      years_experience SMALLINT UNSIGNED DEFAULT NULL,
                                                      profile_photo VARCHAR(255) DEFAULT NULL,

                                                      verification_status ENUM(
                                                          'pending',
                                                          'approved',
                                                          'rejected',
                                                          'suspended'
                                                          ) NOT NULL DEFAULT 'pending',

                                                      is_public TINYINT(1) NOT NULL DEFAULT 0,

                                                      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                                      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                          ON UPDATE CURRENT_TIMESTAMP,

                                                      PRIMARY KEY (id),
                                                      UNIQUE KEY uq_professional_user (user_id),

                                                      CONSTRAINT fk_professional_user
                                                          FOREIGN KEY (user_id)
                                                              REFERENCES users(id)
                                                              ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS professional_types (
                                                  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                                                  name VARCHAR(100) NOT NULL,
                                                  slug VARCHAR(100) NOT NULL,
                                                  is_active TINYINT(1) NOT NULL DEFAULT 1,

                                                  PRIMARY KEY (id),
                                                  UNIQUE KEY uq_professional_type_slug (slug)
);


CREATE TABLE  IF NOT EXISTS businesses (
                                           id INT UNSIGNED NOT NULL AUTO_INCREMENT,

                                           owner_user_id INT NOT NULL,

                                           business_type_id INT UNSIGNED NOT NULL,

                                           name VARCHAR(255) NOT NULL,
                                           description TEXT DEFAULT NULL,

                                           phone VARCHAR(20) DEFAULT NULL,
                                           email VARCHAR(255) DEFAULT NULL,

                                           address TEXT DEFAULT NULL,

                                           status ENUM(
                                               'pending',
                                               'approved',
                                               'rejected',
                                               'suspended'
                                               ) NOT NULL DEFAULT 'pending',

                                           is_public TINYINT(1) NOT NULL DEFAULT 0,

                                           created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                           updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,

                                           PRIMARY KEY (id),

                                           CONSTRAINT fk_business_owner
                                               FOREIGN KEY (owner_user_id)
                                                   REFERENCES users(id)
                                                   ON DELETE CASCADE
);


CREATE TABLE  IF NOT EXISTS  business_types (
                                                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                                                name VARCHAR(100) NOT NULL,
                                                slug VARCHAR(100) NOT NULL,
                                                is_active TINYINT(1) NOT NULL DEFAULT 1,

                                                PRIMARY KEY (id),
                                                UNIQUE KEY uq_business_type_slug (slug)
);



CREATE TABLE IF NOT EXISTS verification_applications (
                                                         id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

                                                         user_id INT NOT NULL,

                                                         status ENUM(
                                                             'pending',
                                                             'approved',
                                                             'rejected',
                                                             'resubmission_required'
                                                             ) NOT NULL DEFAULT 'pending',

                                                         submitted_at DATETIME DEFAULT NULL,
                                                         reviewed_at DATETIME DEFAULT NULL,
                                                         reviewed_by INT DEFAULT NULL,

                                                         rejection_reason TEXT DEFAULT NULL,

                                                         created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                                         updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                             ON UPDATE CURRENT_TIMESTAMP,

                                                         PRIMARY KEY (id),

                                                         KEY idx_verification_user (user_id),
                                                         KEY idx_verification_status (status),

                                                         CONSTRAINT fk_verification_user
                                                             FOREIGN KEY (user_id)
                                                                 REFERENCES users(id)
                                                                 ON DELETE CASCADE,

                                                         CONSTRAINT fk_verification_reviewer
                                                             FOREIGN KEY (reviewed_by)
                                                                 REFERENCES users(id)
                                                                 ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS verification_documents (
                                                      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

                                                      verification_application_id BIGINT UNSIGNED NOT NULL,

                                                      document_type ENUM(
                                                          'ghana_card_front',
                                                          'ghana_card_back',
                                                          'facial_photo',
                                                          'business_certificate',
                                                          'business_front_photo'
                                                          ) NOT NULL,

                                                      file_path VARCHAR(500) NOT NULL,

                                                      uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                                                      PRIMARY KEY (id),

                                                      KEY idx_verification_documents_application
                                                          (verification_application_id),

                                                      CONSTRAINT fk_verification_document_application
                                                          FOREIGN KEY (verification_application_id)
                                                              REFERENCES verification_applications(id)
                                                              ON DELETE CASCADE
);



CREATE TABLE IF NOT EXISTS professional_portfolio (
                                                      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

                                                      professional_id INT UNSIGNED NOT NULL,

                                                      image_path VARCHAR(500) NOT NULL,
                                                      caption VARCHAR(255) DEFAULT NULL,
                                                      display_order TINYINT UNSIGNED NOT NULL DEFAULT 1,

                                                      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                                      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                          ON UPDATE CURRENT_TIMESTAMP,

                                                      PRIMARY KEY (id),

                                                      KEY idx_portfolio_professional (professional_id),

                                                      CONSTRAINT fk_portfolio_professional
                                                          FOREIGN KEY (professional_id)
                                                              REFERENCES professional_profiles(id)
                                                              ON DELETE CASCADE
);






