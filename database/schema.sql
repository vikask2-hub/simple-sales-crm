CREATE TABLE IF NOT EXISTS crm_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('OWNER','BDE') NOT NULL DEFAULT 'BDE',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_leads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    company_name VARCHAR(200) NULL,
    phone VARCHAR(30) NOT NULL,
    email VARCHAR(255) NULL,
    city VARCHAR(100) NULL,
    lead_source ENUM('REFERRAL','WEBSITE','WHATSAPP','SOCIAL_MEDIA','COLD_CALL','WALK_IN','OTHER') NULL,
    assigned_to BIGINT UNSIGNED NULL,
    stage ENUM('NEW','CONTACTED','QUALIFIED','PROPOSAL','NEGOTIATION','WON','LOST') NOT NULL DEFAULT 'NEW',
    estimated_value DECIMAL(12,2) NULL,
    next_follow_up DATETIME NULL,
    notes TEXT NULL,
    lost_reason TEXT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT crm_leads_assigned_to_fk FOREIGN KEY (assigned_to) REFERENCES crm_users(id) ON DELETE SET NULL,
    CONSTRAINT crm_leads_created_by_fk FOREIGN KEY (created_by) REFERENCES crm_users(id),
    INDEX crm_leads_assigned_to_idx (assigned_to),
    INDEX crm_leads_stage_idx (stage),
    INDEX crm_leads_follow_up_idx (next_follow_up),
    INDEX crm_leads_created_at_idx (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_activities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lead_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    activity_type ENUM('CALL','MEETING','WHATSAPP','EMAIL','FOLLOW_UP','NOTE','OTHER') NOT NULL,
    activity_datetime DATETIME NOT NULL,
    outcome VARCHAR(255) NULL,
    notes TEXT NULL,
    next_follow_up DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT crm_activities_lead_fk FOREIGN KEY (lead_id) REFERENCES crm_leads(id) ON DELETE CASCADE,
    CONSTRAINT crm_activities_user_fk FOREIGN KEY (user_id) REFERENCES crm_users(id),
    INDEX crm_activities_lead_idx (lead_id),
    INDEX crm_activities_user_idx (user_id),
    INDEX crm_activities_datetime_idx (activity_datetime)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
