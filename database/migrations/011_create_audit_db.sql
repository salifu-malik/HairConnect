CREATE DATABASE IF NOT EXISTS audit_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE audit_db;

CREATE TABLE IF NOT EXISTS audit_logs (
                                          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                                          user_id BIGINT UNSIGNED NULL,

                                          user_email VARCHAR(255) NULL,

    user_role VARCHAR(100) NULL,

    action VARCHAR(100) NOT NULL,

    module VARCHAR(100) NOT NULL,

    entity_type VARCHAR(100) NULL,

    entity_id BIGINT UNSIGNED NULL,

    description TEXT NULL,

    ip_address VARCHAR(45) NULL,

    user_agent TEXT NULL,

    metadata JSON NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_audit_user_id (user_id),
    INDEX idx_audit_action (action),
    INDEX idx_audit_module (module),
    INDEX idx_audit_entity (entity_type, entity_id),
    INDEX idx_audit_created_at (created_at)
    ) ENGINE=InnoDB
    DEFAULT CHARSET=utf8mb4
    COLLATE=utf8mb4_unicode_ci;