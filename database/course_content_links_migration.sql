-- Add links from courses to existing LMS resources and quizzes.
-- Content linked to a course is accessed through that course's student groups.
-- Unlinked content continues to use its existing group-access rules.

CREATE TABLE IF NOT EXISTS course_resources (
    course_id INT NOT NULL,
    resource_id INT NOT NULL,
    display_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (course_id, resource_id),
    KEY idx_course_resources_resource (resource_id),
    KEY idx_course_resources_order (course_id, display_order, resource_id),
    CONSTRAINT fk_course_resources_course
        FOREIGN KEY (course_id) REFERENCES courses(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS course_quizzes (
    course_id INT NOT NULL,
    quiz_id INT NOT NULL,
    display_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (course_id, quiz_id),
    KEY idx_course_quizzes_quiz (quiz_id),
    KEY idx_course_quizzes_order (course_id, display_order, quiz_id),
    CONSTRAINT fk_course_quizzes_course
        FOREIGN KEY (course_id) REFERENCES courses(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
