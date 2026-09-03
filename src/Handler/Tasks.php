<?php
declare(strict_types=1);

namespace MyTDX;

class TasksHandler
{
    public static function loadTasks(): void
    {
        $db = getDB();
        $listId = (int)($_GET['list'] ?? 0);
        $compl = isset($_GET['compl']) ? (int)$_GET['compl'] : null;
        $sort = isset($_GET['sort']) ? (int)$_GET['sort'] : 0;
        $search = trim($_GET['s'] ?? '');
        $tagFilter = trim($_GET['t'] ?? '');
        $taskId = isset($_GET['i']) ? (int)$_GET['i'] : null;

        if ($listId > 0) {
            check_read_access($listId);
        } else {
            check_read_access();
        }

        // Handle setCompl
        if (isset($_GET['setCompl']) && have_write_access($listId)) {
            $showCompl = (int)$_GET['setCompl'];
            if ($showCompl) {
                $db->ex("UPDATE " . $db->table('lists') . " SET taskview = taskview | 1 WHERE id=?", [$listId]);
            } else {
                $db->ex("UPDATE " . $db->table('lists') . " SET taskview = taskview & ~1 WHERE id=?", [$listId]);
            }
        }

        $conditions = [];
        $params = [];

        if ($listId == -1) {
            // All tasks - no list filter
        } elseif ($listId > 0) {
            $conditions[] = "t.list_id=?";
            $params[] = $listId;
        }

        if ($compl !== null) {
            $conditions[] = "t.compl=?";
            $params[] = $compl;
        }

        if ($taskId !== null) {
            $conditions[] = "t.id=?";
            $params[] = $taskId;
        }

        if ($search !== '') {
            if (preg_match('/^#(\d+)$/', $search, $m)) {
                $conditions[] = "t.id=?";
                $params[] = (int)$m[1];
            } else {
                $conditions[] = "(t.title LIKE ? OR t.note LIKE ?)";
                $like = '%' . $search . '%';
                $params[] = $like;
                $params[] = $like;
            }
        }

        if ($tagFilter !== '') {
            $excludeTags = [];
            $includeTags = [];
            $tags = array_filter(explode(',', $tagFilter));
            foreach ($tags as $tag) {
                $tag = trim($tag);
                if ($tag === '') continue;
                if ($tag[0] === '^') {
                    $excludeTags[] = substr($tag, 1);
                } else {
                    $includeTags[] = $tag;
                }
            }

            foreach ($includeTags as $tag) {
                $conditions[] = "EXISTS (SELECT 1 FROM " . $db->table('tag2task') . " tt2 JOIN " . $db->table('tags') . " tg ON tt2.tag_id=tg.id WHERE tt2.task_id=t.id AND tg.name=?)";
                $params[] = $tag;
            }
            foreach ($excludeTags as $tag) {
                $conditions[] = "NOT EXISTS (SELECT 1 FROM " . $db->table('tag2task') . " tt2 JOIN " . $db->table('tags') . " tg ON tt2.tag_id=tg.id WHERE tt2.task_id=t.id AND tg.name=?)";
                $params[] = $tag;
            }
        }

        $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $orderBy = self::getSortOrder($sort);

        // Count total
        $total = $db->sq("SELECT COUNT(*) FROM " . $db->table('todolist') . " t $where", $params);

        // Fetch tasks
        $rows = $db->dq("SELECT t.* FROM " . $db->table('todolist') . " t $where ORDER BY $orderBy", $params)->fetchAll();

        $list = [];
        foreach ($rows as $row) {
            $list[] = self::formatTaskRow($row, $db);
        }

        jsonExit(['total' => (int)$total, 'list' => $list]);
    }

    public static function newTask(): void
    {
        $listId = (int)($_POST['list'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $tag = trim($_POST['tag'] ?? '');
        check_write_access($listId);

        if ($title === '' || $listId <= 0) {
            jsonExit(['total' => 0, 'list' => []]);
        }

        $db = getDB();
        $prio = 0;
        $tagsStr = '';

        // Smart syntax
        if (Config::get('smartsyntax')) {
            $parsed = parseSmartSyntax($title);
            if ($parsed !== false) {
                $prio = $parsed['prio'];
                $title = $parsed['title'];
                $tagsStr = $parsed['tags'];
            }
        }

        // Autotag
        if ($tag !== '' && Config::get('autotag')) {
            $tagsStr = $tagsStr !== '' ? $tagsStr . ',' . $tag : $tag;
        }

        $maxOw = $db->sq("SELECT COALESCE(MAX(ow),0) FROM " . $db->table('todolist') . " WHERE list_id=? AND compl=0", [$listId]);
        $uuid = generateUUID();
        $now = time();

        $db->ex(
            "INSERT INTO " . $db->table('todolist') . " (uuid, list_id, d_created, d_edited, compl, title, note, prio, ow, tags, tags_ids) VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            [$uuid, $listId, $now, $now, 0, $title, '', $prio, (int)$maxOw + 1, '', '']
        );
        $taskId = (int)$db->lastInsertId();

        // Handle tags
        $tagsData = null;
        if ($tagsStr !== '') {
            $tagsData = self::setTaskTags($taskId, $listId, $tagsStr, $db);
        }

        $row = $db->sqa("SELECT * FROM " . $db->table('todolist') . " WHERE id=?", [$taskId]);
        $task = self::formatTaskRow($row, $db);

        jsonExit(['total' => 1, 'list' => [$task]]);
    }

    public static function fullNewTask(): void
    {
        $listId = (int)($_POST['list'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $note = str_replace("\r\n", "\n", $_POST['note'] ?? '');
        $prio = max(-1, min(2, (int)($_POST['prio'] ?? 0)));
        $duedate = trim($_POST['duedate'] ?? '');
        $tagsStr = trim($_POST['tags'] ?? '');
        $tag = trim($_POST['tag'] ?? '');
        check_write_access($listId);

        if ($title === '' || $listId <= 0) {
            jsonExit(['total' => 0, 'list' => []]);
        }

        $db = getDB();
        $maxOw = $db->sq("SELECT COALESCE(MAX(ow),0) FROM " . $db->table('todolist') . " WHERE list_id=? AND compl=0", [$listId]);
        $uuid = generateUUID();
        $now = time();
        $dueDateVal = parseDuedate($duedate);

        // Autotag
        if ($tag !== '' && Config::get('autotag')) {
            $tagsStr = $tagsStr !== '' ? $tagsStr . ',' . $tag : $tag;
        }

        $db->ex(
            "INSERT INTO " . $db->table('todolist') . " (uuid, list_id, d_created, d_edited, compl, title, note, prio, ow, tags, tags_ids, duedate) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
            [$uuid, $listId, $now, $now, 0, $title, $note, $prio, (int)$maxOw + 1, '', '', $dueDateVal]
        );
        $taskId = (int)$db->lastInsertId();

        if ($tagsStr !== '') {
            self::setTaskTags($taskId, $listId, $tagsStr, $db);
        }

        $row = $db->sqa("SELECT * FROM " . $db->table('todolist') . " WHERE id=?", [$taskId]);
        jsonExit(['total' => 1, 'list' => [self::formatTaskRow($row, $db)]]);
    }

    public static function editTask(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $note = str_replace("\r\n", "\n", $_POST['note'] ?? '');
        $prio = max(-2, min(2, (int)($_POST['prio'] ?? 0)));
        $duedate = trim($_POST['duedate'] ?? '');
        $tagsStr = trim($_POST['tags'] ?? '');
        check_write_access();

        if ($title === '' || $id <= 0) {
            jsonExit(['total' => 0, 'list' => []]);
        }

        $db = getDB();
        $row = $db->sqa("SELECT list_id FROM " . $db->table('todolist') . " WHERE id=?", [$id]);
        if (!$row) {
            jsonExit(['total' => 0, 'list' => []]);
        }
        $listId = (int)$row['list_id'];
        $dueDateVal = parseDuedate($duedate);
        $now = time();

        $db->beginTransaction();
        try {
            $db->ex("DELETE FROM " . $db->table('tag2task') . " WHERE task_id=?", [$id]);
            $db->ex(
                "UPDATE " . $db->table('todolist') . " SET title=?, note=?, prio=?, duedate=?, d_edited=?, tags=?, tags_ids=? WHERE id=?",
                [$title, $note, $prio, $dueDateVal, $now, '', '', $id]
            );

            if ($tagsStr !== '') {
                self::setTaskTags($id, $listId, $tagsStr, $db);
            }

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        $row = $db->sqa("SELECT * FROM " . $db->table('todolist') . " WHERE id=?", [$id]);
        jsonExit(['total' => 1, 'list' => [self::formatTaskRow($row, $db)]]);
    }

    public static function deleteTask(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        check_write_access();

        if ($id <= 0) {
            jsonExit(['total' => 0, 'list' => [['id' => 0]]]);
        }

        $db = getDB();
        $db->beginTransaction();
        try {
            $db->ex("DELETE FROM " . $db->table('tag2task') . " WHERE task_id=?", [$id]);
            $stmt = $db->ex("DELETE FROM " . $db->table('todolist') . " WHERE id=?", [$id]);
            $count = $stmt->rowCount();
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
        jsonExit(['total' => $count, 'list' => [['id' => $id]]]);
    }

    public static function completeTask(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $compl = (int)($_POST['compl'] ?? 0);
        check_write_access();

        $db = getDB();
        $row = $db->sqa("SELECT * FROM " . $db->table('todolist') . " WHERE id=?", [$id]);
        if (!$row) {
            jsonExit(['total' => 0, 'list' => []]);
        }

        $listId = (int)$row['list_id'];
        $now = time();

        if ($compl) {
            $maxOw = $db->sq("SELECT COALESCE(MAX(ow),0) FROM " . $db->table('todolist') . " WHERE list_id=? AND compl=1", [$listId]);
            $db->ex("UPDATE " . $db->table('todolist') . " SET compl=1, ow=?, d_completed=?, d_edited=? WHERE id=?", [(int)$maxOw + 1, $now, $now, $id]);
        } else {
            $maxOw = $db->sq("SELECT COALESCE(MAX(ow),0) FROM " . $db->table('todolist') . " WHERE list_id=? AND compl=0", [$listId]);
            $db->ex("UPDATE " . $db->table('todolist') . " SET compl=0, ow=?, d_completed=0, d_edited=? WHERE id=?", [(int)$maxOw + 1, $now, $id]);
        }

        $row = $db->sqa("SELECT * FROM " . $db->table('todolist') . " WHERE id=?", [$id]);
        jsonExit(['total' => 1, 'list' => [self::formatTaskRow($row, $db)]]);
    }

    public static function editNote(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $note = str_replace("\r\n", "\n", $_POST['note'] ?? '');
        check_write_access();

        if ($id <= 0) {
            jsonExit(['total' => 0, 'list' => []]);
        }

        $db = getDB();
        $now = time();
        $db->ex("UPDATE " . $db->table('todolist') . " SET note=?, d_edited=? WHERE id=?", [$note, $now, $id]);

        $row = $db->sqa("SELECT id, note FROM " . $db->table('todolist') . " WHERE id=?", [$id]);
        jsonExit([
            'total' => 1,
            'list' => [[
                'id' => (int)$row['id'],
                'note' => Config::get('markdown') ? markdownToHtml($row['note']) : nl2brHtml($row['note']),
                'noteText' => $row['note'],
            ]]
        ]);
    }

    public static function setPrio(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $prio = max(-2, min(2, (int)($_POST['prio'] ?? 0)));
        check_write_access();

        if ($id <= 0) {
            jsonExit(['total' => 0, 'list' => []]);
        }

        $db = getDB();
        $now = time();
        $db->ex("UPDATE " . $db->table('todolist') . " SET prio=?, d_edited=? WHERE id=?", [$prio, $now, $id]);
        jsonExit(['total' => 1, 'list' => [['id' => $id, 'prio' => $prio]]]);
    }

    public static function setDuedate(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $duedate = trim($_POST['duedate'] ?? '');
        check_write_access();

        if ($id <= 0) {
            jsonExit(['total' => 0, 'list' => []]);
        }

        $db = getDB();
        $dueDateVal = parseDuedate($duedate);
        $now = time();
        $db->ex("UPDATE " . $db->table('todolist') . " SET duedate=?, d_edited=? WHERE id=?", [$dueDateVal, $now, $id]);

        $row = $db->sqa("SELECT * FROM " . $db->table('todolist') . " WHERE id=?", [$id]);
        if (!$row) {
            jsonExit(['total' => 0, 'list' => []]);
        }
        $task = self::formatTaskRow($row, $db);
        jsonExit(['total' => 1, 'list' => [$task]]);
    }

    public static function changeOrder(): void
    {
        check_write_access();
        $orderStr = $_POST['order'] ?? '';
        if (!is_string($orderStr) || $orderStr === '') {
            jsonExit(['total' => 0]);
        }

        parse_str($orderStr, $parsed);
        if (empty($parsed)) {
            jsonExit(['total' => 0]);
        }

        $db = getDB();
        $db->beginTransaction();
        try {
            foreach ($parsed as $taskId => $delta) {
                $taskId = (int)$taskId;
                $delta = (int)$delta;
                if ($taskId <= 0) continue;
                if ($delta > 0) {
                    $db->ex("UPDATE " . $db->table('todolist') . " SET ow=ow+?, d_edited=? WHERE id=?", [$delta, time(), $taskId]);
                } elseif ($delta < 0) {
                    $db->ex("UPDATE " . $db->table('todolist') . " SET ow=ow-?, d_edited=? WHERE id=?", [abs($delta), time(), $taskId]);
                }
            }
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
        jsonExit(['total' => 1]);
    }

    public static function moveTask(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $fromId = (int)($_POST['from'] ?? 0);
        $toId = (int)($_POST['to'] ?? 0);
        check_write_access();

        if ($id <= 0 || $toId <= 0) {
            jsonExit(['total' => 0]);
        }

        $db = getDB();
        $row = $db->sqa("SELECT list_id FROM " . $db->table('todolist') . " WHERE id=?", [$id]);
        if (!$row) {
            jsonExit(['total' => 0]);
        }
        if ((int)$row['list_id'] === $toId) {
            jsonExit(['total' => 0]);
        }

        // Verify target list exists
        $listExists = $db->sq("SELECT COUNT(*) FROM " . $db->table('lists') . " WHERE id=?", [$toId]);
        if (!$listExists) {
            jsonExit(['total' => 0]);
        }

        $maxOw = $db->sq("SELECT COALESCE(MAX(ow),0) FROM " . $db->table('todolist') . " WHERE list_id=? AND compl=0", [$toId]);
        $now = time();

        $db->beginTransaction();
        try {
            $db->ex("UPDATE " . $db->table('tag2task') . " SET list_id=? WHERE task_id=?", [$toId, $id]);
            $db->ex("UPDATE " . $db->table('todolist') . " SET list_id=?, ow=?, d_edited=? WHERE id=?", [$toId, (int)$maxOw + 1, $now, $id]);
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        if ($fromId == -1) {
            $row = $db->sqa("SELECT * FROM " . $db->table('todolist') . " WHERE id=?", [$id]);
            jsonExit(['total' => 1, 'list' => [self::formatTaskRow($row, $db)]]);
        }
        jsonExit(['total' => 1]);
    }

    public static function parseTaskStr(): void
    {
        check_write_access();
        $title = $_POST['title'] ?? '';

        $result = ['title' => $title, 'prio' => 0, 'tags' => ''];
        if (Config::get('smartsyntax')) {
            $parsed = parseSmartSyntax($title);
            if ($parsed !== false) {
                $result = ['title' => $parsed['title'], 'prio' => $parsed['prio'], 'tags' => $parsed['tags']];
            }
        }
        jsonExit($result);
    }

    private static function setTaskTags(int $taskId, int $listId, string $tagsStr, Database $db): ?array
    {
        $tags = prepareTags($tagsStr);
        if ($tags === false) return null;

        $tagNames = [];
        $tagIds = [];
        foreach ($tags as $tagName) {
            $tag = self::getOrCreateTag($tagName, $db);
            $tagNames[] = $tag['name'];
            $tagIds[] = $tag['id'];
            $exists = $db->sq("SELECT COUNT(*) FROM " . $db->table('tag2task') . " WHERE tag_id=? AND task_id=?", [$tag['id'], $taskId]);
            if (!$exists) {
                $db->ex("INSERT INTO " . $db->table('tag2task') . " (tag_id, task_id, list_id) VALUES (?,?,?)", [$tag['id'], $taskId, $listId]);
            }
        }

        $tagsStr = implode(',', $tagNames);
        $idsStr = implode(',', $tagIds);
        $db->ex("UPDATE " . $db->table('todolist') . " SET tags=?, tags_ids=? WHERE id=?", [$tagsStr, $idsStr, $taskId]);

        return ['tags' => $tagNames, 'ids' => $tagIds];
    }

    private static function getOrCreateTag(string $name, Database $db): array
    {
        $row = $db->sqa("SELECT id, name FROM " . $db->table('tags') . " WHERE name=?", [$name]);
        if ($row) {
            return ['id' => (int)$row['id'], 'name' => $row['name']];
        }
        $db->ex("INSERT INTO " . $db->table('tags') . " (name) VALUES (?)", [$name]);
        $id = (int)$db->lastInsertId();
        return ['id' => $id, 'name' => $name];
    }

    private static function getSortOrder(int $sort): string
    {
        $complSort = 'compl ASC,';
        return match ($sort) {
            // original: compl, prio DESC, due ASC, ow ASC
            1 => "$complSort prio DESC, CASE WHEN COALESCE(duedate,'')='' THEN '9999-99-99' ELSE duedate END ASC, ow ASC",
            // original: compl, prio ASC, due DESC, ow DESC
            101 => "$complSort prio ASC, CASE WHEN COALESCE(duedate,'')='' THEN '9999-99-99' ELSE duedate END DESC, ow DESC",
            // original: compl, due ASC, prio DESC, ow ASC
            2 => "$complSort CASE WHEN COALESCE(duedate,'')='' THEN '9999-99-99' ELSE duedate END ASC, prio DESC, ow ASC",
            // original: compl, due DESC, prio ASC, ow DESC
            102 => "$complSort CASE WHEN COALESCE(duedate,'')='' THEN '9999-99-99' ELSE duedate END DESC, prio ASC, ow DESC",
            // original: compl, d_created ASC, prio DESC, ow ASC
            3 => "$complSort d_created ASC, prio DESC, ow ASC",
            // original: compl, d_created DESC, prio ASC, ow DESC
            103 => "$complSort d_created DESC, prio ASC, ow DESC",
            // original: compl, d_edited ASC, prio DESC, ow ASC
            4 => "$complSort d_edited ASC, prio DESC, ow ASC",
            // original: compl, d_edited DESC, prio ASC, ow DESC
            104 => "$complSort d_edited DESC, prio ASC, ow DESC",
            default => "$complSort ow ASC",
        };
    }

    public static function formatTaskRow(array $row, Database $db): array
    {
        $lang = \Lang::instance();

        $date = !empty($row['d_created']) ? timestampToDatetime((int)$row['d_created']) : '';
        $dateInt = !empty($row['d_created']) ? date2int(date('Y-m-d', (int)$row['d_created'])) : 0;
        $dateEditedInt = !empty($row['d_edited']) ? date2int(date('Y-m-d', (int)$row['d_edited'])) : 0;

        $dateInline = !empty($row['d_created']) ? sprintf($lang->get('taskdate_inline_created'), formatTime(Config::get('dateformatshort', 'j M'), (int)$row['d_created'])) : '';

        $dateCompleted = '';
        $dateCompletedInline = '';
        $dateCompletedInlineTitle = '';
        if (!empty($row['d_completed'])) {
            $dateCompleted = timestampToDatetime((int)$row['d_completed']);
            $dateCompletedInline = sprintf($lang->get('taskdate_inline_completed'), formatTime(Config::get('dateformatshort', 'j M'), (int)$row['d_completed']));
            $dateCompletedInlineTitle = $lang->get('taskdate_completed') . ': ' . $dateCompleted;
        }

        $note = !empty($row['note']) ? (Config::get('markdown') ? markdownToHtml($row['note']) : nl2brHtml($row['note'])) : '';
        $noteText = $row['note'] ?? '';

        $due = prepareDuedate($row['duedate'] ?? null);

        // Fetch tags from tag2task if tags field is empty
        $tags = $row['tags'] ?? '';
        $tagsIds = $row['tags_ids'] ?? '';
        if ($tags === '' && !empty($row['id'])) {
            $tagRows = $db->dq(
                "SELECT t.name, t.id FROM " . $db->table('tag2task') . " tt JOIN " . $db->table('tags') . " t ON tt.tag_id=t.id WHERE tt.task_id=? ORDER BY t.name",
                [(int)$row['id']]
            )->fetchAll();
            if (!empty($tagRows)) {
                $tagNames = [];
                $tagIdList = [];
                foreach ($tagRows as $tr) {
                    $tagNames[] = $tr['name'];
                    $tagIdList[] = $tr['id'];
                }
                $tags = implode(',', $tagNames);
                $tagsIds = implode(',', $tagIdList);
                // Update the cached fields
                $db->ex("UPDATE " . $db->table('todolist') . " SET tags=?, tags_ids=? WHERE id=?", [$tags, $tagsIds, (int)$row['id']]);
            }
        }

        return [
            'id' => (int)$row['id'],
            'title' => e($row['title'] ?? ''),
            'listId' => (int)$row['list_id'],
            'date' => $date,
            'dateInt' => $dateInt,
            'dateInline' => $dateInline,
            'dateInlineTitle' => $dateInline,
            'dateEditedInt' => $dateEditedInt,
            'dateCompleted' => $dateCompleted,
            'dateCompletedInline' => $dateCompletedInline,
            'dateCompletedInlineTitle' => $dateCompletedInlineTitle,
            'compl' => (int)$row['compl'],
            'prio' => (int)$row['prio'],
            'note' => $note,
            'noteText' => $noteText,
            'ow' => (int)$row['ow'],
            'tags' => $tags,
            'tags_ids' => $tagsIds,
            'duedate' => $due['formatted'],
            'dueClass' => $due['class'],
            'dueStr' => $due['str'],
            'dueInt' => $due['timestamp'] ? date2int(date('Y-m-d', $due['timestamp'])) : 33330000,
            'dueTitle' => $due['str'],
        ];
    }
}
