-- =============================================================
-- Course Sessions & Modules Migration
-- =============================================================
-- This migration adds a hierarchical structure for lectures without
-- removing the existing lectures and lecture_folder_access tables.
--
-- IMPORTANT:
-- In this project, the legacy lectures.drive_folder_id is generally a
-- direct Google Drive video URL, not a folder URL. For the first item of
-- each migrated session, we keep the direct video URL as the default item
-- source and add supporting document/quiz items as needed.
-- =============================================================

CREATE TABLE IF NOT EXISTS course_modules (
    id INT NOT NULL AUTO_INCREMENT,
    lecture_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    display_order INT NOT NULL DEFAULT 0,
    status ENUM('active', 'inactive', 'draft', 'archived') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_course_modules_lecture (lecture_id),
    KEY idx_course_modules_order (display_order, id),
    CONSTRAINT fk_course_modules_lecture
        FOREIGN KEY (lecture_id) REFERENCES lectures(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_items (
    id INT NOT NULL AUTO_INCREMENT,
    module_id INT NOT NULL,
    item_type ENUM('video', 'quiz', 'document') NOT NULL,
    title VARCHAR(255) NOT NULL,
    content_url TEXT NOT NULL,
    display_order INT NOT NULL DEFAULT 0,
    status ENUM('active', 'inactive', 'draft', 'archived') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_module_items_order (module_id, display_order, id),
    KEY idx_module_items_type (item_type),
    CONSTRAINT fk_module_items_module
        FOREIGN KEY (module_id) REFERENCES course_modules(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_item_progress (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    completed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_item_progress (user_id, item_id),
    KEY idx_user_item_progress_item (item_id),
    KEY idx_user_item_progress_user (user_id, completed_at),
    CONSTRAINT fk_user_item_progress_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_user_item_progress_item
        FOREIGN KEY (item_id) REFERENCES module_items(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- Backfill sessions from existing lectures.
-- Each lecture becomes a parent module/session. When the lecture already
-- contains a direct Google Drive video URL, create the first module item as
-- a video item using the same URL.
-- =============================================================

INSERT INTO course_modules (lecture_id, title, display_order, status)
SELECT
    l.id,
    l.title,
    l.display_order,
    CASE WHEN l.status = 'active' THEN 'active' ELSE 'inactive' END
FROM lectures l
WHERE NOT EXISTS (
    SELECT 1
    FROM course_modules cm
    WHERE cm.lecture_id = l.id
)
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    display_order = VALUES(display_order),
    status = VALUES(status);

INSERT INTO module_items (module_id, item_type, title, content_url, display_order, status)
SELECT
    cm.id,
    'video',
    CONCAT('Step 1: ', l.title),
    l.drive_folder_id,
    1,
    l.status
FROM lectures l
INNER JOIN course_modules cm ON cm.lecture_id = l.id
WHERE l.drive_folder_id IS NOT NULL
  AND TRIM(l.drive_folder_id) <> ''
  AND NOT EXISTS (
      SELECT 1
      FROM module_items mi
      WHERE mi.module_id = cm.id
        AND mi.display_order = 1
        AND mi.item_type = 'video'
  );

-- =============================================================
-- Example seed data representing two migrated sessions.
-- The sample entries are intentionally based on existing lecture rows,
-- so they work even when IDs are not yet known in the live database.
-- Replace the sample Drive IDs with real video IDs for your LMS.
-- Keep this example disabled during migration to avoid changing live data.
-- =============================================================
/*

INSERT INTO course_modules (lecture_id, title, display_order, status)
SELECT l.id,
       CONCAT('Session ', COALESCE(l.display_order, 1), ' - ', l.title),
       COALESCE(l.display_order, 1),
       l.status
FROM lectures l
WHERE l.title IN ('Basics', 'Revision')
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    display_order = VALUES(display_order),
    status = VALUES(status);

INSERT INTO module_items (module_id, item_type, title, content_url, display_order, status)
SELECT cm.id, 'video', 'Step 1: Intro Lesson',
       'https://drive.google.com/file/d/VIDEO_ID_1/preview?rm=minimal',
       1, 'active'
FROM course_modules cm
INNER JOIN lectures l ON l.id = cm.lecture_id
WHERE l.title = 'Basics'
  AND NOT EXISTS (
      SELECT 1 FROM module_items mi WHERE mi.module_id = cm.id AND mi.display_order = 1 AND mi.item_type = 'video'
  );

INSERT INTO module_items (module_id, item_type, title, content_url, display_order, status)
SELECT cm.id, 'document', 'Step 2: Basics Handbook',
       '/uploads/handbooks/basics-handbook.pdf',
       2, 'active'
FROM course_modules cm
INNER JOIN lectures l ON l.id = cm.lecture_id
WHERE l.title = 'Basics'
  AND NOT EXISTS (
      SELECT 1 FROM module_items mi WHERE mi.module_id = cm.id AND mi.display_order = 2 AND mi.item_type = 'document'
  );

INSERT INTO module_items (module_id, item_type, title, content_url, display_order, status)
SELECT cm.id, 'video', 'Step 1: Revision Lesson',
       'https://drive.google.com/file/d/VIDEO_ID_2/preview?rm=minimal',
       1, 'active'
FROM course_modules cm
INNER JOIN lectures l ON l.id = cm.lecture_id
WHERE l.title = 'Revision'
  AND NOT EXISTS (
      SELECT 1 FROM module_items mi WHERE mi.module_id = cm.id AND mi.display_order = 1 AND mi.item_type = 'video'
  );

INSERT INTO module_items (module_id, item_type, title, content_url, display_order, status)
SELECT cm.id, 'document', 'Step 2: Revision Worksheet',
       '/uploads/worksheets/revision-worksheet.pdf',
       2, 'active'
FROM course_modules cm
INNER JOIN lectures l ON l.id = cm.lecture_id
WHERE l.title = 'Revision'
  AND NOT EXISTS (
      SELECT 1 FROM module_items mi WHERE mi.module_id = cm.id AND mi.display_order = 2 AND mi.item_type = 'document'
  );
*/

-- =============================================================
-- Example progress rows: use valid user/item IDs from your instance.
-- These are intentionally commented out to avoid breaking migrations in
-- a database where the sample IDs do not exist.
-- =============================================================
-- INSERT INTO user_item_progress (user_id, item_id)
-- VALUES
--     (1, 1)
-- ON DUPLICATE KEY UPDATE
--     completed_at = VALUES(completed_at);
