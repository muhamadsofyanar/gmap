CREATE TABLE IF NOT EXISTS whatsapp_inbox (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 device_id VARCHAR(100) NOT NULL,
 message_id VARCHAR(191) NOT NULL,
 contact_phone VARCHAR(20) NULL,
 direction VARCHAR(10) NOT NULL,
 push_name VARCHAR(200) NOT NULL,
 message MEDIUMTEXT NOT NULL,
 file_url TEXT NULL,
 sent_at DATETIME NOT NULL,
 received_at DATETIME NOT NULL,
 lead_id INT UNSIGNED NULL,
 reviewed_at DATETIME NULL,
 reviewed_by INT UNSIGNED NULL,
 UNIQUE KEY inbox_device_message (device_id,message_id),
 KEY inbox_lead_time (lead_id,sent_at),
 CONSTRAINT fk_inbox_lead FOREIGN KEY (lead_id) REFERENCES project_leads(id) ON DELETE CASCADE,
 CONSTRAINT fk_inbox_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS whatsapp_inbox_contacts (
 device_id VARCHAR(100) NOT NULL,
 contact_phone VARCHAR(20) NOT NULL,
 lead_id INT UNSIGNED NOT NULL,
 linked_by INT UNSIGNED NULL,
 linked_at DATETIME NOT NULL,
 PRIMARY KEY (device_id,contact_phone),
 CONSTRAINT fk_inbox_contact_lead FOREIGN KEY (lead_id) REFERENCES project_leads(id) ON DELETE CASCADE,
 CONSTRAINT fk_inbox_contact_actor FOREIGN KEY (linked_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
