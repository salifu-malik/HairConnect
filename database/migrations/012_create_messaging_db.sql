CREATE DATABASE IF NOT EXISTS messaging_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE messaging_db;

-- Stores conversations between customers, service providers,
-- vendors, administrators, and finance managers.
CREATE TABLE IF NOT EXISTS conversations (
                                             id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                                             conversation_type ENUM(
                                             'PRODUCT_ORDER',
                                             'HOME_SERVICE',
                                             'SHOP_SERVICE',
                                             'COMPLAINT'
) NOT NULL,

    participant_one_id INT NOT NULL,
    participant_two_id INT NOT NULL,

    -- References are validated by the application because
    -- the related records live in other databases.
    related_order_id INT NULL,
    related_appointment_id INT NULL,

    created_by INT NOT NULL,

    -- Set once when the first message is successfully created.
    first_message_at DATETIME NULL,
    expires_at DATETIME NULL,

    -- Administrative deletion metadata.
    deleted_at DATETIME NULL,
    deleted_by INT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_conversation_participant_one (
                                               participant_one_id,
                                               deleted_at
                                           ),

    INDEX idx_conversation_participant_two (
                                               participant_two_id,
                                               deleted_at
                                           ),

    INDEX idx_conversation_expiry (
                                      conversation_type,
                                      expires_at
                                  ),

    INDEX idx_conversation_order (
                                     related_order_id
                                 ),

    INDEX idx_conversation_appointment (
                                           related_appointment_id
                                       ),

    INDEX idx_conversation_deleted_at (
                                          deleted_at
                                      ),

    CONSTRAINT chk_conversation_participants
    CHECK (participant_one_id <> participant_two_id),

    CONSTRAINT chk_conversation_expiry
    CHECK (
(
              conversation_type = 'COMPLAINT'
              AND expires_at IS NULL
)
    OR
(
    conversation_type <> 'COMPLAINT'
    AND (
(first_message_at IS NULL AND expires_at IS NULL)
    OR
(first_message_at IS NOT NULL AND expires_at IS NOT NULL)
    )
    )
    )
    ) ENGINE=InnoDB;


-- Stores individual messages.
CREATE TABLE IF NOT EXISTS messages (
                                        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                                        conversation_id BIGINT UNSIGNED NOT NULL,
                                        sender_id INT NOT NULL,

                                        message_type ENUM('TEXT') NOT NULL DEFAULT 'TEXT',
    content TEXT NOT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_messages_conversation (
                                        conversation_id,
                                        created_at,
                                        id
                                    ),

    INDEX idx_messages_sender (
                                  sender_id,
                                  created_at
                              ),

    CONSTRAINT fk_messages_conversation
    FOREIGN KEY (conversation_id)
    REFERENCES conversations(id)
    ON DELETE CASCADE
    ) ENGINE=InnoDB;


-- Retains a record of administrative deletions without retaining
-- the deleted conversation's message contents.
CREATE TABLE IF NOT EXISTS conversation_deletion_audit (
                                                           id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                                                           original_conversation_id BIGINT UNSIGNED NOT NULL,
                                                           deleted_by INT NOT NULL,

                                                           deleted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                                                           INDEX idx_deletion_audit_conversation (
                                                           original_conversation_id
),

    INDEX idx_deletion_audit_actor (
                                       deleted_by,
                                       deleted_at
                                   )
    ) ENGINE=InnoDB;


ALTER TABLE messages
    ADD COLUMN delivered_at DATETIME NULL DEFAULT NULL,
    ADD COLUMN read_at DATETIME NULL DEFAULT NULL,
    ADD INDEX idx_messages_conversation_read (conversation_id, read_at);