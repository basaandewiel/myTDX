<?php
declare(strict_types=1);

namespace MyTDX;

class ListsHandler
{
    public static function loadLists(): void
    {
        $db = getDB();
        $needAuth = (Config::get('password_hash') !== '') ? 1 : 0;

        if ($needAuth && !is_logged()) {
            $rows = $db->dq("SELECT * FROM " . $db->table('lists') . " WHERE published=1 ORDER BY ow ASC, id ASC")->fetchAll();
        } else {
            $rows = $db->dq("SELECT * FROM " . $db->table('lists') . " ORDER BY ow ASC, id ASC")->fetchAll();
        }

        $list = [];
        foreach ($rows as $row) {
            $list[] = self::formatList($row);
        }
        jsonExit(['total' => count($list), 'list' => $list]);
    }

    public static function addList(): void
    {
        check_write_access();
        $name = trim(str_replace(['"', "'", '<', '>', '&'], '', $_POST['name'] ?? ''));
        if ($name === '') {
            jsonExit(['total' => 0, 'list' => []]);
        }

        $db = getDB();
        $maxOw = $db->sq("SELECT COALESCE(MAX(ow),0) FROM " . $db->table('lists'));
        $uuid = generateUUID();
        $now = time();

        $db->ex(
            "INSERT INTO " . $db->table('lists') . " (uuid, ow, name, d_created, d_edited, sorting, published, taskview) VALUES (?,?,?,?,?,?,?,?)",
            [$uuid, (int)$maxOw + 1, $name, $now, $now, 0, 0, 0]
        );
        $id = (int)$db->lastInsertId();

        $row = $db->sqa("SELECT * FROM " . $db->table('lists') . " WHERE id=?", [$id]);
        jsonExit(['total' => 1, 'list' => [self::formatList($row)]]);
    }

    public static function renameList(): void
    {
        $listId = (int)($_POST['list'] ?? 0);
        check_write_access($listId);

        $name = trim(str_replace(['"', "'", '<', '>', '&'], '', $_POST['name'] ?? ''));
        if ($name === '' || $listId <= 0) {
            jsonExit(['total' => 0, 'list' => []]);
        }

        $db = getDB();
        $db->ex("UPDATE " . $db->table('lists') . " SET name=?, d_edited=? WHERE id=?", [$name, time(), $listId]);
        $row = $db->sqa("SELECT * FROM " . $db->table('lists') . " WHERE id=?", [$listId]);
        jsonExit(['total' => 1, 'list' => $row ? [self::formatList($row)] : []]);
    }

    public static function deleteList(): void
    {
        $listId = (int)($_POST['list'] ?? 0);
        check_write_access($listId);
        if ($listId <= 0) {
            jsonExit(['total' => 0]);
        }

        $db = getDB();
        $db->beginTransaction();
        try {
            $taskIds = $db->dq("SELECT id FROM " . $db->table('todolist') . " WHERE list_id=?", [$listId])->fetchAll(\PDO::FETCH_COLUMN);
            if (!empty($taskIds)) {
                $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
                $db->ex("DELETE FROM " . $db->table('tag2task') . " WHERE task_id IN ($placeholders)", $taskIds);
                $db->ex("DELETE FROM " . $db->table('todolist') . " WHERE list_id=?", [$listId]);
            }
            $db->ex("DELETE FROM " . $db->table('lists') . " WHERE id=?", [$listId]);
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
        jsonExit(['total' => 1]);
    }

    public static function setSort(): void
    {
        $listId = (int)($_POST['list'] ?? 0);
        $sort = (int)($_POST['sort'] ?? 0);
        check_write_access($listId);

        if (!in_array($sort, [0,1,2,3,4,101,102,103,104])) {
            $sort = 0;
        }

        $db = getDB();
        $db->ex("UPDATE " . $db->table('lists') . " SET sorting=?, d_edited=? WHERE id=?", [$sort, time(), $listId]);
        jsonExit(['total' => 1]);
    }

    public static function publishList(): void
    {
        $listId = (int)($_POST['list'] ?? 0);
        $publish = (int)($_POST['publish'] ?? 0);
        check_write_access($listId);

        $db = getDB();
        $db->ex("UPDATE " . $db->table('lists') . " SET published=?, d_edited=? WHERE id=?", [$publish ? 1 : 0, time(), $listId]);
        jsonExit(['total' => 1]);
    }

    public static function changeListOrder(): void
    {
        check_write_access();
        $order = $_POST['order'] ?? [];
        if (!is_array($order)) {
            jsonExit(['total' => 0]);
        }

        $db = getDB();
        $db->beginTransaction();
        try {
            foreach ($order as $ow => $id) {
                $ow = (int)$ow;
                $id = (int)$id;
                $db->ex("UPDATE " . $db->table('lists') . " SET ow=?, d_edited=? WHERE id=?", [$ow, time(), $id]);
            }
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
        jsonExit(['total' => 1]);
    }

    public static function setShowNotesInList(): void
    {
        $listId = (int)($_POST['list'] ?? 0);
        $shownotes = (int)($_POST['shownotes'] ?? 0);
        check_write_access($listId);

        $db = getDB();
        if ($shownotes) {
            $db->ex("UPDATE " . $db->table('lists') . " SET taskview = taskview | 2 WHERE id=?", [$listId]);
        } else {
            $db->ex("UPDATE " . $db->table('lists') . " SET taskview = taskview & ~2 WHERE id=?", [$listId]);
        }
        jsonExit(['total' => 1]);
    }

    public static function setHideList(): void
    {
        $listId = (int)($_POST['list'] ?? 0);
        $hide = (int)($_POST['hide'] ?? 0);
        check_write_access($listId);

        $db = getDB();
        if ($hide) {
            $db->ex("UPDATE " . $db->table('lists') . " SET taskview = taskview | 4 WHERE id=?", [$listId]);
        } else {
            $db->ex("UPDATE " . $db->table('lists') . " SET taskview = taskview & ~4 WHERE id=?", [$listId]);
        }
        jsonExit(['total' => 1]);
    }

    public static function clearCompletedInList(): void
    {
        $listId = (int)($_POST['list'] ?? 0);
        check_write_access($listId);

        $db = getDB();
        $db->beginTransaction();
        try {
            $taskIds = $db->dq("SELECT id FROM " . $db->table('todolist') . " WHERE list_id=? AND compl=1", [$listId])->fetchAll(\PDO::FETCH_COLUMN);
            $count = 0;
            if (!empty($taskIds)) {
                $count = count($taskIds);
                $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
                $db->ex("DELETE FROM " . $db->table('tag2task') . " WHERE task_id IN ($placeholders)", $taskIds);
                $db->ex("DELETE FROM " . $db->table('todolist') . " WHERE id IN ($placeholders)", $taskIds);
            }
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
        jsonExit(['total' => $count]);
    }

    public static function formatList(array $row): array
    {
        $taskview = (int)$row['taskview'];
        return [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'sort' => (int)$row['sorting'],
            'published' => (int)$row['published'],
            'showCompl' => ($taskview & 1) ? 1 : 0,
            'showNotes' => ($taskview & 2) ? 1 : 0,
            'hidden' => ($taskview & 4) ? 1 : 0,
        ];
    }
}
