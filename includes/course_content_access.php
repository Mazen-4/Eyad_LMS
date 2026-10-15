<?php

function courseContentLinksReady(mysqli $connection): bool
{
    $statement = $connection->prepare("
        SELECT COUNT(*) AS table_count
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name IN ('course_resources', 'course_quizzes')
    ");
    $statement->execute();
    $row = $statement->get_result()->fetch_assoc();

    return (int) ($row['table_count'] ?? 0) === 2;
}

function getAccessibleResource(
    mysqli $connection,
    int $resourceId,
    array $groupIds,
    bool $isAdmin,
    int $courseId = 0
): ?array {
    if ($resourceId <= 0) {
        return null;
    }

    $linksReady = courseContentLinksReady($connection);
    if ($courseId > 0) {
        if (!$linksReady) {
            return null;
        }
        $params = [$resourceId, $courseId];
        $groupFilter = '';
        if (!$isAdmin) {
            $groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds))));
            if ($groupIds === []) {
                return null;
            }
            $placeholders = implode(', ', array_fill(0, count($groupIds), '?'));
            $groupFilter = "AND cga.group_id IN ($placeholders)";
            $params = array_merge($params, $groupIds);
        }

        $statement = $connection->prepare("
            SELECT r.id, r.title, r.description, r.pdf_path
            FROM resources r
            INNER JOIN course_resources cr ON cr.resource_id = r.id
            INNER JOIN courses c ON c.id = cr.course_id AND c.status = 'active'
            INNER JOIN course_group_access cga ON cga.course_id = c.id
            WHERE r.id = ?
              AND r.status = 'active'
              AND c.id = ?
              $groupFilter
            LIMIT 1
        ");
        bindPreparedParams($statement, $params);
        $statement->execute();
        return $statement->get_result()->fetch_assoc() ?: null;
    }

    if ($isAdmin) {
        $statement = $connection->prepare('SELECT id, title, description, pdf_path FROM resources WHERE id = ? AND status = "active" LIMIT 1');
        $statement->bind_param('i', $resourceId);
        $statement->execute();
        return $statement->get_result()->fetch_assoc() ?: null;
    }

    $groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds))));
    if ($groupIds === []) {
        return null;
    }

    $placeholders = implode(', ', array_fill(0, count($groupIds), '?'));
    $notInCourse = $linksReady
        ? 'AND NOT EXISTS (SELECT 1 FROM course_resources cr WHERE cr.resource_id = r.id)'
        : '';
    $statement = $connection->prepare("
        SELECT DISTINCT r.id, r.title, r.description, r.pdf_path
        FROM resources r
        LEFT JOIN resource_group_access rga ON rga.resource_id = r.id
        WHERE r.id = ?
          AND r.status = 'active'
          $notInCourse
          AND (r.group_id IN ($placeholders) OR rga.group_id IN ($placeholders))
        LIMIT 1
    ");
    bindPreparedParams($statement, array_merge([$resourceId], $groupIds, $groupIds));
    $statement->execute();
    return $statement->get_result()->fetch_assoc() ?: null;
}

function getAccessibleResources(
    mysqli $connection,
    array $groupIds,
    bool $isAdmin,
    int $courseId = 0
): array {
    $linksReady = courseContentLinksReady($connection);
    if ($courseId > 0) {
        if (!$linksReady) {
            return [];
        }
        $params = [$courseId];
        $groupFilter = '';
        if (!$isAdmin) {
            $groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds))));
            if ($groupIds === []) {
                return [];
            }
            $placeholders = implode(', ', array_fill(0, count($groupIds), '?'));
            $groupFilter = "AND cga.group_id IN ($placeholders)";
            $params = array_merge($params, $groupIds);
        }

        $statement = $connection->prepare("
            SELECT DISTINCT r.id, r.title, r.description, r.pdf_path
            FROM course_resources cr
            INNER JOIN resources r ON r.id = cr.resource_id AND r.status = 'active'
            INNER JOIN courses c ON c.id = cr.course_id AND c.status = 'active'
            INNER JOIN course_group_access cga ON cga.course_id = c.id
            WHERE c.id = ?
              $groupFilter
            ORDER BY cr.display_order, r.id
        ");
        bindPreparedParams($statement, $params);
        $statement->execute();
        return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    if ($isAdmin) {
        $statement = $connection->prepare('SELECT id, title, description, pdf_path FROM resources WHERE status = "active" ORDER BY created_at DESC');
        $statement->execute();
        return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    $groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds))));
    if ($groupIds === []) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($groupIds), '?'));
    $notInCourse = $linksReady
        ? 'AND NOT EXISTS (SELECT 1 FROM course_resources cr WHERE cr.resource_id = r.id)'
        : '';
    $statement = $connection->prepare("
        SELECT DISTINCT r.id, r.title, r.description, r.pdf_path
        FROM resources r
        LEFT JOIN resource_group_access rga ON rga.resource_id = r.id
        WHERE r.status = 'active'
          $notInCourse
          AND (r.group_id IN ($placeholders) OR rga.group_id IN ($placeholders))
        ORDER BY r.created_at DESC
    ");
    bindPreparedParams($statement, array_merge($groupIds, $groupIds));
    $statement->execute();
    return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getAccessibleQuizzes(
    mysqli $connection,
    array $groupIds,
    bool $isAdmin,
    int $courseId = 0
): array {
    $linksReady = courseContentLinksReady($connection);
    if ($courseId > 0) {
        if (!$linksReady) {
            return [];
        }
        $params = [$courseId];
        $groupFilter = '';
        if (!$isAdmin) {
            $groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds))));
            if ($groupIds === []) {
                return [];
            }
            $placeholders = implode(', ', array_fill(0, count($groupIds), '?'));
            $groupFilter = "AND cga.group_id IN ($placeholders)";
            $params = array_merge($params, $groupIds);
        }

        $statement = $connection->prepare("
            SELECT DISTINCT q.id, q.title, q.time_limit_minutes, q.max_attempts
            FROM course_quizzes cq
            INNER JOIN quizzes q ON q.id = cq.quiz_id AND q.status = 'active'
            INNER JOIN courses c ON c.id = cq.course_id AND c.status = 'active'
            INNER JOIN course_group_access cga ON cga.course_id = c.id
            WHERE c.id = ?
              $groupFilter
            ORDER BY q.created_at DESC, q.id
        ");
        bindPreparedParams($statement, $params);
        $statement->execute();
        return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    if ($isAdmin) {
        $statement = $connection->prepare('SELECT id, title, time_limit_minutes, max_attempts FROM quizzes WHERE status = "active" ORDER BY created_at DESC');
        $statement->execute();
        return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    $groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds))));
    if ($groupIds === []) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($groupIds), '?'));
    $notInCourse = $linksReady
        ? 'AND NOT EXISTS (SELECT 1 FROM course_quizzes cq WHERE cq.quiz_id = q.id)'
        : '';
    $statement = $connection->prepare("
        SELECT DISTINCT q.id, q.title, q.time_limit_minutes, q.max_attempts
        FROM quizzes q
        LEFT JOIN quiz_group_access qga ON qga.quiz_id = q.id
        WHERE q.status = 'active'
          $notInCourse
          AND (q.group_id IN ($placeholders) OR qga.group_id IN ($placeholders))
        ORDER BY q.created_at DESC, q.id
    ");
    bindPreparedParams($statement, array_merge($groupIds, $groupIds));
    $statement->execute();
    return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getAccessibleQuiz(
    mysqli $connection,
    int $quizId,
    array $groupIds,
    bool $isAdmin,
    int $courseId = 0
): ?array {
    if ($quizId <= 0) {
        return null;
    }

    $linksReady = courseContentLinksReady($connection);
    if ($courseId > 0) {
        if (!$linksReady) {
            return null;
        }
        $params = [$quizId, $courseId];
        $groupFilter = '';
        if (!$isAdmin) {
            $groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds))));
            if ($groupIds === []) {
                return null;
            }
            $placeholders = implode(', ', array_fill(0, count($groupIds), '?'));
            $groupFilter = "AND cga.group_id IN ($placeholders)";
            $params = array_merge($params, $groupIds);
        }

        $statement = $connection->prepare("
            SELECT q.id, q.title, q.time_limit_minutes, q.max_attempts
            FROM course_quizzes cq
            INNER JOIN quizzes q ON q.id = cq.quiz_id AND q.status = 'active'
            INNER JOIN courses c ON c.id = cq.course_id AND c.status = 'active'
            INNER JOIN course_group_access cga ON cga.course_id = c.id
            WHERE q.id = ?
              AND c.id = ?
              $groupFilter
            LIMIT 1
        ");
        bindPreparedParams($statement, $params);
        $statement->execute();
        return $statement->get_result()->fetch_assoc() ?: null;
    }

    if ($isAdmin) {
        $statement = $connection->prepare('SELECT id, title, time_limit_minutes, max_attempts FROM quizzes WHERE id = ? AND status = "active" LIMIT 1');
        $statement->bind_param('i', $quizId);
        $statement->execute();
        return $statement->get_result()->fetch_assoc() ?: null;
    }

    $groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds))));
    if ($groupIds === []) {
        return null;
    }

    $placeholders = implode(', ', array_fill(0, count($groupIds), '?'));
    $notInCourse = $linksReady
        ? 'AND NOT EXISTS (SELECT 1 FROM course_quizzes cq WHERE cq.quiz_id = q.id)'
        : '';
    $statement = $connection->prepare("
        SELECT DISTINCT q.id, q.title, q.time_limit_minutes, q.max_attempts
        FROM quizzes q
        LEFT JOIN quiz_group_access qga ON qga.quiz_id = q.id
        WHERE q.id = ?
          AND q.status = 'active'
          $notInCourse
          AND (q.group_id IN ($placeholders) OR qga.group_id IN ($placeholders))
        LIMIT 1
    ");
    bindPreparedParams($statement, array_merge([$quizId], $groupIds, $groupIds));
    $statement->execute();
    return $statement->get_result()->fetch_assoc() ?: null;
}
