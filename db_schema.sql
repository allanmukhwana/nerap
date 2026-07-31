-- =========================================================================
-- NERAP Cloud — MySQL Database Schema
-- Import this file into a database named `nerap_cloud` (see config.php).
-- =========================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------------------
-- admin_users — moderators / admins who can log into the dashboard
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin','moderator') NOT NULL DEFAULT 'moderator',
    region VARCHAR(100) DEFAULT NULL, -- optional: county/region a moderator is responsible for
    reset_token VARCHAR(64) DEFAULT NULL,
    reset_expires DATETIME DEFAULT NULL,
    status ENUM('active','disabled') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- facilities — registered health facilities / shelters / distribution points
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS facilities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    type ENUM('hospital','clinic','pharmacy','shelter','distribution_point','other') NOT NULL DEFAULT 'hospital',
    region VARCHAR(100) NOT NULL,       -- county / region
    address VARCHAR(255) DEFAULT NULL,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    is_verified TINYINT(1) NOT NULL DEFAULT 0, -- trusted / semi-trusted source
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_latlng (latitude, longitude),
    INDEX idx_region (region)
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- resources — resource types (antivenom, blood type, RIG, etc.) + synonyms
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(100) NOT NULL,     -- e.g. "Antivenom", "Blood", "ICU Bed"
    subtype VARCHAR(100) DEFAULT NULL,  -- e.g. "Polyvalent (Snake)", "O-Negative"
    synonyms VARCHAR(255) DEFAULT NULL, -- comma separated keywords incl. Swahili/Somali/Amharic (e.g. "damu,blood,dam")
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- facility_resources — junction: stock level per facility per resource
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS facility_resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facility_id INT NOT NULL,
    resource_id INT NOT NULL,
    status ENUM('confirmed','low','out','unverified') NOT NULL DEFAULT 'unverified',
    quantity INT DEFAULT NULL,
    last_verified_at DATETIME DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_facility_resource (facility_id, resource_id),
    FOREIGN KEY (facility_id) REFERENCES facilities(id) ON DELETE CASCADE,
    FOREIGN KEY (resource_id) REFERENCES resources(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- submissions — raw incoming reports (WhatsApp crowdsourced + web) awaiting moderation
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source ENUM('whatsapp','web') NOT NULL DEFAULT 'whatsapp',
    phone VARCHAR(30) DEFAULT NULL,
    facility_id INT DEFAULT NULL,       -- NULL if reporting a brand-new facility/shelter
    facility_name_raw VARCHAR(150) DEFAULT NULL, -- name typed by reporter if new facility
    resource_id INT DEFAULT NULL,
    reported_status ENUM('confirmed','low','out') DEFAULT NULL,
    quantity INT DEFAULT NULL,
    latitude DECIMAL(10,7) DEFAULT NULL,
    longitude DECIMAL(10,7) DEFAULT NULL,
    attachment_url VARCHAR(500) DEFAULT NULL, -- photo of stock sheet, etc.
    notes TEXT DEFAULT NULL,
    review_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    reviewed_by INT DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (facility_id) REFERENCES facilities(id) ON DELETE SET NULL,
    FOREIGN KEY (resource_id) REFERENCES resources(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- moderation_log — audit trail of every moderation decision
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS moderation_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    admin_id INT NOT NULL,
    action ENUM('approved','rejected','flagged') NOT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- wa_sessions — WhatsApp conversation state machine, keyed by phone number
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS wa_sessions (
    phone VARCHAR(30) PRIMARY KEY,
    state VARCHAR(60) NOT NULL DEFAULT 'idle', -- e.g. idle, awaiting_resource_type, awaiting_report_status
    data TEXT DEFAULT NULL,                     -- JSON blob of in-progress conversation data
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- subscribers — alert subscription preferences (WhatsApp and/or email)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(30) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    name VARCHAR(100) DEFAULT NULL,
    region VARCHAR(100) DEFAULT NULL,   -- alert scope: only notify for this region (NULL = all)
    resource_id INT DEFAULT NULL,       -- alert scope: only this resource (NULL = all)
    channel ENUM('whatsapp','email','both') NOT NULL DEFAULT 'whatsapp',
    status ENUM('active','unsubscribed') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (resource_id) REFERENCES resources(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- alert_log — sent alerts, used for de-duplication + the activity histogram
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS alert_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subscriber_id INT DEFAULT NULL,
    facility_id INT DEFAULT NULL,
    resource_id INT DEFAULT NULL,
    channel ENUM('whatsapp','email') NOT NULL,
    message TEXT DEFAULT NULL,
    status ENUM('sent','failed') NOT NULL DEFAULT 'sent',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE SET NULL,
    FOREIGN KEY (facility_id) REFERENCES facilities(id) ON DELETE SET NULL,
    FOREIGN KEY (resource_id) REFERENCES resources(id) ON DELETE SET NULL
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- -------------------------------------------------------------------------
-- NOTE: The first super_admin account is NOT seeded here with a hardcoded
-- password hash (fragile/insecure). Instead, run setup_admin.php once in
-- your browser after importing this schema — it creates the account using
-- PHP's own password_hash() at runtime, then should be deleted.
-- -------------------------------------------------------------------------

-- Seed a handful of resource types with multilingual synonyms
INSERT INTO resources (category, subtype, synonyms) VALUES
('Antivenom', 'Polyvalent (Snake)', 'snake,nyoka,antivenom,polyvalent'),
('Antivenom', 'Scorpion', 'scorpion,nge'),
('Antivenom', 'Spider', 'spider,buibui'),
('Blood', 'O-Negative', 'blood,damu,o negative,o-negative'),
('Blood', 'O-Positive', 'blood,damu,o positive,o-positive'),
('Rabies', 'Rabies Immunoglobulin (RIG)', 'rabies,rig,mbwa,dog bite'),
('ICU', 'ICU Bed', 'icu,icu bed,critical care'),
('Shelter', 'Emergency Shelter', 'shelter,makazi,camp'),
('Water', 'Water Distribution Point', 'water,maji,distribution point')
ON DUPLICATE KEY UPDATE category = category;
