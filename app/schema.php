<?php
// Creates the database, tables and initial catalogue. Called automatically by db():
//  - installDatabase() when the database does not exist yet;
//  - migrateSchema() when app/.schema-version is older than SCHEMA_VERSION.
// Every statement is idempotent. Bump SCHEMA_VERSION (app/db.php) whenever migrateSchema() changes.

function installDatabase(): void {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . DB_NAME . '`');
    migrateSchema($pdo, true);
    seedCatalogue($pdo);
    // After the products exist, so blog posts can link their related product.
    require_once __DIR__ . '/blog-seed.php';
    importBlogSeed($pdo);
}

function migrateSchema(PDO $pdo, bool $freshInstall = false): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `categories` (
        `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `slug`      VARCHAR(100) NOT NULL,
        `name`      VARCHAR(200) NOT NULL,
        `tagline`   VARCHAR(500) DEFAULT NULL,
        `image`     VARCHAR(500) DEFAULT NULL,
        `sortOrder` INT NOT NULL DEFAULT 0,
        `createdAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uqSlug` (`slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `products` (
        `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `slug`            VARCHAR(100) NOT NULL,
        `name`            VARCHAR(200) NOT NULL,
        `categorySlug`    VARCHAR(100) DEFAULT NULL,
        `status`          ENUM('published','draft') NOT NULL DEFAULT 'published',
        `featured`        TINYINT(1) NOT NULL DEFAULT 0,
        `image`           VARCHAR(500) DEFAULT NULL,
        `gallery`         JSON DEFAULT NULL,
        `description`     TEXT,
        `fullDescription` TEXT,
        `features`        JSON DEFAULT NULL,
        `applications`    JSON DEFAULT NULL,
        `specifications`  JSON DEFAULT NULL,
        `certifications`  JSON DEFAULT NULL,
        `documents`       JSON DEFAULT NULL,
        `metaTitle`       VARCHAR(200) DEFAULT NULL,
        `metaDescription` VARCHAR(300) DEFAULT NULL,
        `sortOrder`       INT NOT NULL DEFAULT 0,
        `createdAt`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updatedAt`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uqSlug` (`slug`),
        KEY `idxCategorySlug` (`categorySlug`),
        KEY `idxStatus` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `enquiries` (
        `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `name`      VARCHAR(200) NOT NULL,
        `email`     VARCHAR(200) DEFAULT NULL,
        `phone`     VARCHAR(50)  DEFAULT NULL,
        `subject`   VARCHAR(300) DEFAULT NULL,
        `message`   TEXT,
        `status`    ENUM('new','replied','closed') NOT NULL DEFAULT 'new',
        `ip`        VARCHAR(45)  DEFAULT NULL,
        `createdAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idxStatus` (`status`),
        KEY `idxIpCreated` (`ip`, `createdAt`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // v2: contact form stores the sender IP (rate limiting); document request log.
    addColumnIfMissing($pdo, 'enquiries', 'ip', "VARCHAR(45) DEFAULT NULL AFTER `status`");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `documentRequests` (
        `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `email`         VARCHAR(200) NOT NULL,
        `productSlug`   VARCHAR(100) NOT NULL,
        `productName`   VARCHAR(200) NOT NULL,
        `documentLabel` VARCHAR(200) NOT NULL,
        `documentUrl`   VARCHAR(500) DEFAULT NULL,
        `mailStatus`    ENUM('sent','failed') NOT NULL,
        `mailError`     VARCHAR(500) DEFAULT NULL,
        `ip`            VARCHAR(45)  DEFAULT NULL,
        `createdAt`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idxEmail` (`email`),
        KEY `idxProductSlug` (`productSlug`),
        KEY `idxIpCreated` (`ip`, `createdAt`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // v3: footer newsletter signups.
    $pdo->exec("CREATE TABLE IF NOT EXISTS `newsletterSubscribers` (
        `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `email`      VARCHAR(200) NOT NULL,
        `sourcePage` VARCHAR(200) DEFAULT NULL,
        `ip`         VARCHAR(45)  DEFAULT NULL,
        `createdAt`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uqEmail` (`email`),
        KEY `idxIpCreated` (`ip`, `createdAt`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // v4: optional full-bleed hero image/video for the product page.
    addColumnIfMissing($pdo, 'products', 'heroMediaType', "ENUM('image','video') DEFAULT NULL AFTER `gallery`");
    addColumnIfMissing($pdo, 'products', 'heroMedia', "VARCHAR(500) DEFAULT NULL AFTER `heroMediaType`");

    // v5: blog. RESTRICT on categoryId: a category with posts can't be deleted (posts must be moved first);
    // deactivating a category only hides it from the public filter — its posts stay online.
    $s = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'blogs'");
    $hadBlogs = (bool)(int)$s->fetchColumn();

    $pdo->exec("CREATE TABLE IF NOT EXISTS `blogCategories` (
        `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `name`        VARCHAR(200) NOT NULL,
        `slug`        VARCHAR(100) NOT NULL,
        `description` VARCHAR(500) DEFAULT NULL,
        `image`       VARCHAR(500) DEFAULT NULL,
        `isActive`    TINYINT(1) NOT NULL DEFAULT 1,
        `sortOrder`   INT NOT NULL DEFAULT 0,
        `createdAt`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updatedAt`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uqSlug` (`slug`),
        KEY `idxActiveSort` (`isActive`, `sortOrder`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `blogs` (
        `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `categoryId`       INT UNSIGNED NOT NULL,
        `title`            VARCHAR(255) NOT NULL,
        `slug`             VARCHAR(150) NOT NULL,
        `excerpt`          VARCHAR(500) DEFAULT NULL,
        `content`          MEDIUMTEXT,
        `featuredImage`    VARCHAR(500) DEFAULT NULL,
        `authorName`       VARCHAR(100) NOT NULL DEFAULT 'MarkEn Team',
        `status`           ENUM('published','draft') NOT NULL DEFAULT 'draft',
        `featured`         TINYINT(1) NOT NULL DEFAULT 0,
        `relatedProductId` INT UNSIGNED DEFAULT NULL,
        `publishedAt`      DATETIME DEFAULT NULL,
        `metaTitle`        VARCHAR(200) DEFAULT NULL,
        `metaDescription`  VARCHAR(300) DEFAULT NULL,
        `createdAt`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updatedAt`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uqSlug` (`slug`),
        KEY `idxStatusPublished` (`status`, `publishedAt`),
        KEY `idxCategory` (`categoryId`),
        KEY `idxRelatedProduct` (`relatedProductId`),
        CONSTRAINT `fkBlogsCategory` FOREIGN KEY (`categoryId`) REFERENCES `blogCategories` (`id`)
            ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT `fkBlogsRelatedProduct` FOREIGN KEY (`relatedProductId`) REFERENCES `products` (`id`)
            ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Existing sites get the old static posts once; a fresh install imports them after seedCatalogue().
    if (!$hadBlogs && !$freshInstall) {
        require_once __DIR__ . '/blog-seed.php';
        importBlogSeed($pdo);
    }

    @file_put_contents(SCHEMA_VERSION_FILE, (string)SCHEMA_VERSION);
}

function addColumnIfMissing(PDO $pdo, string $table, string $column, string $definition): void {
    $s = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $s->execute([$table, $column]);
    if (!(int)$s->fetchColumn()) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

function seedCatalogue(PDO $pdo): void {
    $cat = $pdo->prepare('INSERT IGNORE INTO `categories` (slug, name, tagline, image, sortOrder) VALUES (?, ?, ?, ?, ?)');
    $categories = [
        ['medical-cold-chain',   'Medical Cold Chain',   'Precision cold storage for vaccines, plasma and biological samples',    'images/banners/cold-storage.webp'],
        ['blood-bank-equipment', 'Blood Bank Equipment', 'Instruments engineered for the safe collection and processing of blood', 'images/banners/blood-bank-range.webp'],
        ['hospital-furniture',   'Hospital Furniture',   'Beds, trolleys and furniture built for critical care environments',      'images/banners/hospital-furniture-range.webp'],
        ['medical-equipment',    'Medical Equipment',    'Monitoring, respiratory and life-support systems for every ward',       'images/banners/flagship-products.webp'],
    ];
    foreach ($categories as $i => $c) $cat->execute([...$c, $i + 1]);

    $f = fn(array $pairs) => array_map(fn($p) => ['title' => $p[0], 'description' => $p[1]], $pairs);

    // Mirrors the original js/data.js catalogue exactly.
    $products = [
        ['ult-deep-freezer-86', 'ULT Deep Freezer –86°C', 'medical-cold-chain', true,
            'Ultra Low Temperature (–86°C) Deep Freezer is engineered for the critical preservation of high-value biological specimens, including vaccines, plasma, and cell cultures. Its precision-controlled refrigeration, robust insulation, and advanced safety systems ensure ultra-stable temperatures—even under extreme ambient conditions—making it a trusted solution in biomedical research, pharmaceutical, and clinical applications.',
            $f([
                ['ULT Stability (–50°C to –86°C)', 'Ensures safe long-term storage for sensitive biological materials.'],
                ['Advanced Refrigeration System', 'High efficiency, CFC-free compression cycle for rapid cooling and sustainability.'],
                ['Triple Seal Door Design', 'Robust construction with multi-point sealing for superior insulation and temperature recovery.'],
                ['Smart Control Interface', 'Touchscreen programming with secure access and multi-level alarm system.'],
                ['Optional Power Backup Support', 'Enables uninterrupted operation during power outages to protect stored samples.'],
                ['Comprehensive Safety Mechanisms', 'Includes fire resistance, pressure control, and thermal overload protection.'],
            ]),
            ['Biomedical research', 'Pharmaceutical storage', 'Vaccine & plasma banking', 'Clinical laboratories']],
        ['deep-freezer-40', 'Deep Freezer –40°C', 'medical-cold-chain'],
        ['laboratory-refrigerator', 'Laboratory Refrigerator', 'medical-cold-chain'],
        ['ice-lined-refrigerator', 'Ice Lined Refrigerator (Vertical)', 'medical-cold-chain'],
        ['passive-cold-box', 'Passive Cold Box', 'medical-cold-chain'],
        ['solar-walk-in-cooler', 'Solar Walk-in Cooler and Freezer', 'medical-cold-chain'],

        ['refrigerated-centrifuge', 'Refrigerated Centrifuge', 'blood-bank-equipment', true,
            'Our Refrigerated Centrifuge is designed for precise separation of temperature-sensitive samples in clinical, biomedical, pharmaceutical, and research laboratories. Equipped with advanced temperature control, high RPM capability, and intelligent safety features, this laboratory refrigerated centrifuge ensures reliable performance in tropical and continuous-use environments. Ideal for blood banks, pathology labs, molecular biology, and biotech applications.',
            $f([
                ['Advanced Rotor Recognition System', 'Prevents overspeeding and rotor mismatch for enhanced operational safety.'],
                ['Precision Cooling Mechanism', 'Maintains required temperatures for sensitive biological samples during high-speed runs.'],
                ['Microprocessor-Controlled Interface', 'Intuitive digital control panel with programmable settings and diagnostic feedback.'],
                ['Ultra-Quiet Operation', 'Low-noise and low-vibration design preserves sample quality and ensures lab comfort.'],
                ['Integrated Safety Sensors', 'Features imbalance detection, automatic braking, and manual lid release during emergencies.'],
                ['Built for Longevity', 'Durable construction rated for 10+ years of use, with assured spare parts availability.'],
            ]),
            ['Blood banks', 'Pathology labs', 'Molecular biology', 'Biotech research']],
        ['contact-shock-freezer', 'Contact Shock Freezer', 'blood-bank-equipment'],
        ['blood-bank-refrigerator', 'Blood Bank Refrigerator', 'blood-bank-equipment'],
        ['blood-collection-monitor', 'Blood Collection Monitor', 'blood-bank-equipment'],
        ['blood-donor-couch', 'Blood Donor Couch', 'blood-bank-equipment'],
        ['platelet-incubator', 'Platelet Incubator & Agitator', 'blood-bank-equipment'],

        ['icu-7-function-bed', 'ICU 7 Function Motorized Bed', 'hospital-furniture', true,
            "ICU 7 Function Motorized Bed is a state of the art motorized hospital bed, designed to enhance patient comfort, safety, and caregiver efficiency in intensive care environments. With precision motorized functions, aesthetic ergonomics, and a durable build, it delivers a seamless blend of design and critical performance that sets it apart in today's modern medical care settings.",
            $f([
                ['7 Function Electric Adjustments', 'Offers smooth motorized control of backrest, knee-rest, height, Trendelenburg & reverse Trendelenburg positions via handset.'],
                ['4 Section CRCA Platform', 'Robust and corrosion-resistant Cold Rolled Close Annealed frame ensures long-term reliability and hygienic use.'],
                ['Aesthetic Polymer Molded Panels', 'Detachable head & foot boards crafted for ergonomic appeal and easy patient access.'],
                ['Split ABS Side Rails', 'Four-piece collapsible safety rails provide secure enclosure and fall prevention with effortless handling.'],
                ['Advanced Mobility & Safety', 'Fitted with 125mm castors and a diagonal locking system for smooth transport and firm stability.'],
                ['Integrated Utility Enhancements', 'Features saline stand provision, corner bumpers, and mattress retainers for enhanced critical care functionality.'],
            ]),
            ['Intensive care units', 'Post-operative recovery', 'Emergency wards']],
        ['icu-5-function-bed', 'ICU 5 Function Manual Deluxe Bed', 'hospital-furniture'],
        ['stretcher-trolley-ss', 'Stretcher Trolley SS', 'hospital-furniture'],
        ['examination-couch', 'Examination Couch', 'hospital-furniture'],
        ['iv-stand-ss', 'IV Stand SS', 'hospital-furniture'],
        ['bedside-locker-deluxe', 'Bedside Locker Deluxe', 'hospital-furniture'],

        ['ventilator', 'Ventilator', 'medical-equipment', true,
            'Ventilator is a cutting-edge respiratory support system engineered for high-acuity environments such as ICUs, emergency units, and surgical theaters. Designed to deliver both invasive and non-invasive ventilation, it ensures accurate oxygen delivery and carbon dioxide removal for patients in respiratory distress. With intelligent modes, intuitive controls, and robust safety systems, this ventilator stands out for its clinical reliability and patient-centric design.',
            $f([
                ['Multi-Mode Ventilation Support', 'Offers invasive and non-invasive ventilation with volume and pressure control for personalized treatment.'],
                ['High-Resolution Display Interface', 'User-friendly touchscreen interface displays real-time waveforms, respiratory parameters, and system alerts.'],
                ['Precise Respiratory Control', 'Adjustable settings for tidal volume, respiratory rate, PEEP, FiO₂, and inspiratory/expiratory ratio.'],
                ['Integrated Alarm & Safety Systems', 'Intelligent alarms for apnea, disconnection, high pressure, and low volume ensure safe patient management.'],
                ['Battery Backup & Portability', 'Long-lasting battery for uninterrupted operation during transport or power loss.'],
                ['Compact & Mobile Design', 'Mounted on smooth-rolling castors with a lock system, ideal for bedside mobility in intensive care settings.'],
            ]),
            ['ICUs', 'Emergency units', 'Surgical theaters', 'Patient transport']],
        ['patient-monitor', 'Patient Monitor – ME-2000', 'medical-equipment'],
        ['radiant-warmer', 'Radiant Warmer', 'medical-equipment'],
        ['syringe-pump', 'Syringe Pump', 'medical-equipment'],
        ['bipap', 'BIPAP', 'medical-equipment'],
        ['defibrillator', 'Defibrillator', 'medical-equipment'],
    ];

    $stmt = $pdo->prepare('INSERT IGNORE INTO `products`
        (slug, name, categorySlug, featured, image, description, features, applications, sortOrder)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($products as $i => $p) {
        [$slug, $name, $catSlug] = $p;
        $stmt->execute([
            $slug, $name, $catSlug,
            !empty($p[3]) ? 1 : 0,
            "images/products/$slug.webp",
            $p[4] ?? null,
            isset($p[5]) ? json_encode($p[5], JSON_UNESCAPED_UNICODE) : null,
            isset($p[6]) ? json_encode($p[6], JSON_UNESCAPED_UNICODE) : null,
            $i + 1,
        ]);
    }
}
