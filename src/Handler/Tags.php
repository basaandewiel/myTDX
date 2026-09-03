<?php
declare(strict_types=1);

namespace MyTDX;

class TagsHandler
{
    public static function tagCloud(): void
    {
        $listId = (int)($_GET['list'] ?? 0);
        $db = getDB();

        // Tag cloud spans all lists; require an authenticated session (not readonly).
        if (is_readonly()) {
            jsonExit(['total' => 0, 'cloud' => []]);
        }

        $rows = $db->dq(
            "SELECT t.id, t.name, COUNT(tt.task_id) as cnt FROM " . $db->table('tags') . " t JOIN " . $db->table('tag2task') . " tt ON t.id=tt.tag_id GROUP BY t.id, t.name ORDER BY cnt DESC, t.name ASC"
        )->fetchAll();

        $total = count($rows);
        if ($total === 0) {
            jsonExit(['total' => 0, 'cloud' => []]);
        }

        $maxCnt = max(array_column($rows, 'cnt'));
        $minCnt = min(array_column($rows, 'cnt'));
        $step = max(1, (int)ceil(($maxCnt - $minCnt) / 10));

        $cloud = [];
        foreach ($rows as $row) {
            $cnt = (int)$row['cnt'];
            $w = $step > 0 ? (int)ceil(($cnt - $minCnt) / $step) : 0;
            if ($w > 9) $w = 9;

            // Check if tag belongs to current list
            $alien = 0;
            if ($listId > 0) {
                $inList = $db->sq("SELECT COUNT(*) FROM " . $db->table('tag2task') . " WHERE tag_id=? AND list_id=?", [(int)$row['id'], $listId]);
                $alien = $inList ? 0 : 1;
            }

            $cloud[] = [
                'tag' => e($row['name']),
                'id' => (int)$row['id'],
                'w' => $w,
                'alien' => $alien,
            ];
        }

        jsonExit(['total' => count($cloud), 'cloud' => $cloud]);
    }

    public static function suggestTags(): void
    {
        $listId = (int)($_GET['list'] ?? 0);
        $q = ltrim(trim($_GET['q'] ?? ''), '@=~!#');
        $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 8;

        if ($q === '') {
            jsonExit(['suggestions' => []]);
        }

        $db = getDB();
        $rows = $db->dq(
            "SELECT t.name, t.id, COUNT(tt.task_id) as cnt FROM " . $db->table('tags') . " t JOIN " . $db->table('tag2task') . " tt ON t.id=tt.tag_id WHERE tt.list_id=? AND TRIM(t.name, '@=~!#') LIKE ? GROUP BY t.id, t.name ORDER BY cnt DESC LIMIT ?",
            [$listId, $q . '%', $limit]
        )->fetchAll();

        $output = [];
        foreach ($rows as $row) {
            $output[] = ['name' => $row['name'], 'id' => (int)$row['id']];
        }

        jsonExit(['suggestions' => $output]);
    }
}
