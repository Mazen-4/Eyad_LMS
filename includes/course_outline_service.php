<?php

final class CourseOutlineService
{
    private mysqli $connection;

    public function __construct(mysqli $connection)
    {
        $this->connection = $connection;
    }

    public function hasCourseSchema(): bool
    {
        $statement = $this->connection->prepare("
            SELECT
                (SELECT COUNT(*)
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name IN (
                       'courses',
                       'course_group_access',
                       'module_items',
                       'user_item_progress'
                   )) AS table_count,
                (SELECT COUNT(*)
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = 'course_modules'
                   AND column_name = 'course_id') AS column_count
        ");
        $statement->execute();
        $schema = $statement->get_result()->fetch_assoc();

        return (int) ($schema['table_count'] ?? 0) === 4
            && (int) ($schema['column_count'] ?? 0) === 1;
    }

    public function getCoursesForUser(int $userId, array $groupIds, bool $isAdmin): array
    {
        if ($userId <= 0 || !$this->hasCourseSchema()) {
            return [];
        }

        if ($isAdmin) {
            $statement = $this->connection->prepare("
                SELECT c.id, c.title, c.description, c.display_order
                FROM courses c
                WHERE c.status = 'active'
                ORDER BY c.display_order, c.id
            ");
            $statement->execute();
            return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
        }

        $groupIds = $this->normalizeGroupIds($groupIds);
        if ($groupIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($groupIds), '?'));
        $linkedContentExists = $this->hasCourseContentLinkSchema()
            ? "
                  OR EXISTS (
                      SELECT 1
                      FROM course_resources cr
                      INNER JOIN resources r
                          ON r.id = cr.resource_id
                         AND r.status = 'active'
                      WHERE cr.course_id = c.id
                  )
                  OR EXISTS (
                      SELECT 1
                      FROM course_quizzes cq
                      INNER JOIN quizzes q
                          ON q.id = cq.quiz_id
                         AND q.status = 'active'
                      WHERE cq.course_id = c.id
                  )
            "
            : '';
        $statement = $this->connection->prepare("
            SELECT DISTINCT c.id, c.title, c.description, c.display_order
            FROM courses c
            INNER JOIN course_group_access cga
                ON cga.course_id = c.id
            WHERE c.status = 'active'
              AND cga.group_id IN ($placeholders)
              AND (
                  EXISTS (
                      SELECT 1
                      FROM course_modules cm
                      INNER JOIN lectures l
                          ON l.id = cm.lecture_id
                         AND l.status = 'active'
                      INNER JOIN lecture_folder_access lfa
                          ON lfa.lecture_id = cm.lecture_id
                         AND lfa.group_id = cga.group_id
                      WHERE cm.course_id = c.id
                        AND cm.status = 'active'
                  )
                  $linkedContentExists
              )
            ORDER BY c.display_order, c.id
        ");
        bindPreparedParams($statement, $groupIds);
        $statement->execute();

        return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getSessionsForCourse(
        int $courseId,
        int $userId,
        array $groupIds,
        bool $isAdmin
    ): array {
        if ($courseId <= 0 || $userId <= 0 || !$this->hasCourseSchema()) {
            return [];
        }

        $params = [$userId, $courseId];
        $accessFilter = '';

        if (!$isAdmin) {
            $groupIds = $this->normalizeGroupIds($groupIds);
            if ($groupIds === []) {
                return [];
            }

            $placeholders = implode(', ', array_fill(0, count($groupIds), '?'));
            $accessFilter = "
                AND EXISTS (
                    SELECT 1
                    FROM course_group_access cga
                    INNER JOIN lecture_folder_access lfa
                        ON lfa.group_id = cga.group_id
                    WHERE cga.course_id = cm.course_id
                      AND lfa.lecture_id = cm.lecture_id
                      AND cga.group_id IN ($placeholders)
                )
            ";
            $params = array_merge($params, $groupIds);
        }

        $statement = $this->connection->prepare("
            SELECT
                cm.id AS session_id,
                cm.lecture_id,
                cm.title AS session_title,
                cm.display_order AS session_order,
                mi.id AS item_id,
                mi.item_type,
                mi.title AS item_title,
                mi.content_url,
                mi.display_order AS item_order,
                CASE WHEN uip.item_id IS NULL THEN 0 ELSE 1 END AS is_completed
            FROM course_modules cm
            INNER JOIN lectures l
                ON l.id = cm.lecture_id
               AND l.status = 'active'
            LEFT JOIN module_items mi
                ON mi.module_id = cm.id
               AND mi.status = 'active'
            LEFT JOIN user_item_progress uip
                ON uip.item_id = mi.id
               AND uip.user_id = ?
            WHERE cm.course_id = ?
              AND cm.status = 'active'
            $accessFilter
            ORDER BY cm.display_order, cm.id, mi.display_order, mi.id
        ");
        bindPreparedParams($statement, $params);
        $statement->execute();

        $sessions = [];
        $result = $statement->get_result();
        while ($row = $result->fetch_assoc()) {
            $sessionId = (int) $row['session_id'];
            if (!isset($sessions[$sessionId])) {
                $sessions[$sessionId] = [
                    'session_id' => $sessionId,
                    'lecture_id' => (int) $row['lecture_id'],
                    'title' => $row['session_title'],
                    'progress_percent' => 0,
                    'item_count' => 0,
                    'completed_count' => 0,
                    'items' => [],
                ];
            }

            if ($row['item_id'] === null) {
                continue;
            }

            $completed = (bool) $row['is_completed'];
            $sessions[$sessionId]['items'][] = [
                'item_id' => (int) $row['item_id'],
                'type' => $row['item_type'],
                'title' => $row['item_title'],
                'content_url' => $row['content_url'],
                'step_index' => count($sessions[$sessionId]['items']) + 1,
                'completed' => $completed,
            ];
            $sessions[$sessionId]['item_count']++;

            if ($completed) {
                $sessions[$sessionId]['completed_count']++;
            }
        }

        foreach ($sessions as &$session) {
            if ($session['item_count'] > 0) {
                $session['progress_percent'] = (int) round(
                    ($session['completed_count'] / $session['item_count']) * 100
                );
            }
        }
        unset($session);

        return array_values($sessions);
    }

    public function getResourcesForCourse(int $courseId, array $groupIds, bool $isAdmin): array
    {
        if ($courseId <= 0 || !$this->hasCourseSchema() || !$this->hasCourseContentLinkSchema()) {
            return [];
        }

        $params = [$courseId];
        $accessFilter = '';
        if (!$isAdmin) {
            $groupIds = $this->normalizeGroupIds($groupIds);
            if ($groupIds === []) {
                return [];
            }
            $placeholders = implode(', ', array_fill(0, count($groupIds), '?'));
            $accessFilter = "AND cga.group_id IN ($placeholders)";
            $params = array_merge($params, $groupIds);
        }

        $statement = $this->connection->prepare("
            SELECT DISTINCT r.id, r.title, r.description, r.pdf_path
            FROM course_resources cr
            INNER JOIN resources r
                ON r.id = cr.resource_id
               AND r.status = 'active'
            INNER JOIN courses c
                ON c.id = cr.course_id
               AND c.status = 'active'
            INNER JOIN course_group_access cga
                ON cga.course_id = c.id
            WHERE c.id = ?
              $accessFilter
            ORDER BY cr.display_order, r.id
        ");
        bindPreparedParams($statement, $params);
        $statement->execute();
        return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getQuizzesForCourse(int $courseId, array $groupIds, bool $isAdmin): array
    {
        if ($courseId <= 0 || !$this->hasCourseSchema() || !$this->hasCourseContentLinkSchema()) {
            return [];
        }

        $params = [$courseId];
        $accessFilter = '';
        if (!$isAdmin) {
            $groupIds = $this->normalizeGroupIds($groupIds);
            if ($groupIds === []) {
                return [];
            }
            $placeholders = implode(', ', array_fill(0, count($groupIds), '?'));
            $accessFilter = "AND cga.group_id IN ($placeholders)";
            $params = array_merge($params, $groupIds);
        }

        $statement = $this->connection->prepare("
            SELECT DISTINCT q.id, q.title, q.time_limit_minutes, q.max_attempts
            FROM course_quizzes cq
            INNER JOIN quizzes q
                ON q.id = cq.quiz_id
               AND q.status = 'active'
            INNER JOIN courses c
                ON c.id = cq.course_id
               AND c.status = 'active'
            INNER JOIN course_group_access cga
                ON cga.course_id = c.id
            WHERE c.id = ?
              $accessFilter
            ORDER BY cq.display_order, q.id
        ");
        bindPreparedParams($statement, $params);
        $statement->execute();
        return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    private function hasCourseContentLinkSchema(): bool
    {
        $statement = $this->connection->prepare("
            SELECT COUNT(*) AS table_count
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name IN ('course_resources', 'course_quizzes')
        ");
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();

        return (int) ($row['table_count'] ?? 0) === 2;
    }

    private function normalizeGroupIds(array $groupIds): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $groupIds),
            static function (int $groupId): bool {
                return $groupId > 0;
            }
        )));
    }
}
