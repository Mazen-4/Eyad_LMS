-- Add explicit course ownership and course-to-student-group access.
-- This is an additive, one-time migration. It deliberately leaves existing
-- course_modules.course_id values NULL: assign sessions to courses through
-- the admin workflow after reviewing their intended course memberships.
--
-- Existing unassigned sessions remain available in the existing group-filtered
-- legacy Sessions listing until an admin assigns them to a course.

CREATE TABLE IF NOT EXISTS courses (
    id INT NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    display_order INT NOT NULL DEFAULT 0,
    status ENUM('active', 'draft', 'archived') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_courses_status_order (status, display_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS course_group_access (
    course_id INT NOT NULL,
    group_id INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (course_id, group_id),
    KEY idx_course_group_access_group (group_id, course_id),
    CONSTRAINT fk_course_group_access_course
        FOREIGN KEY (course_id) REFERENCES courses(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_course_group_access_group
        FOREIGN KEY (group_id) REFERENCES `groups`(id)
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

ALTER TABLE course_modules
    MODIFY COLUMN status ENUM('active', 'inactive', 'draft', 'archived') NOT NULL DEFAULT 'active';

ALTER TABLE module_items
    MODIFY COLUMN status ENUM('active', 'inactive', 'draft', 'archived') NOT NULL DEFAULT 'active';

UPDATE course_modules cm
INNER JOIN lectures l ON l.id = cm.lecture_id
SET cm.status = CASE WHEN l.status = 'active' THEN 'active' ELSE 'inactive' END;

ALTER TABLE course_modules
    ADD COLUMN course_id INT NULL AFTER lecture_id,
    ADD KEY idx_course_modules_course_order (course_id, display_order, id),
    ADD CONSTRAINT fk_course_modules_course
        FOREIGN KEY (course_id) REFERENCES courses(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;
