<?php
declare(strict_types=1);

namespace MyTDX;

class FeedHandler
{
    public static function feed(): void
    {
        $listId = (int)($_GET['list'] ?? 0);
        $feedType = $_GET['type'] ?? 'current';

        $db = getDB();
        $needAuth = (Config::get('password_hash') !== '') ? 1 : 0;

        $listData = $db->sqa("SELECT * FROM " . $db->table('lists') . " WHERE id=?", [$listId]);
        if (!$listData) {
            header('HTTP/1.1 404 Not Found');
            die('List not found');
        }

        if ($needAuth && !is_logged() && (!$listData['published'])) {
            die('Access denied!<br> List is not published.');
        }

        $title = htmlspecialchars(Config::get('title', 'myTDX') . ' - ' . $listData['name'], ENT_QUOTES, 'UTF-8');
        $self = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');

        header('Content-Type: application/rss+xml; charset=utf-8');

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo "<rss version=\"2.0\" xmlns:atom=\"http://www.w3.org/2005/Atom\">\n";
        echo "<channel>\n";
        echo "<title>" . $title . "</title>\n";
        echo "<link>" . getBaseUrl() . "</link>\n";
        echo "<atom:link href=\"" . htmlspecialchars($self, ENT_QUOTES, 'UTF-8') . "\" rel=\"self\" type=\"application/rss+xml\" />\n";
        echo "<description>" . $title . "</description>\n";

        // Determine query based on feed type
        switch ($feedType) {
            case 'completed':
                $where = "compl=1";
                break;
            case 'modified':
                $where = "d_edited > d_created";
                break;
            default:
                $where = "compl=0";
                break;
        }

        $rows = $db->dq(
            "SELECT * FROM " . $db->table('todolist') . " WHERE list_id=? AND $where ORDER BY d_edited DESC LIMIT 30",
            [$listId]
        )->fetchAll();

        foreach ($rows as $row) {
            $time = (int)$row['d_edited'];
            $pubDate = gmdate('D, d M Y H:i:s', $time) . ' GMT';
            $guid = $db->table('todolist') . '-' . $row['uuid'] . '@mytdx';

            echo "<item>\n";
            echo "<title><![CDATA[" . stripTagsContent($row['title']) . "]]></title>\n";
            echo "<link>" . getBaseUrl() . "#list/" . $listId . "</link>\n";
            echo "<guid isPermaLink=\"false\">" . htmlspecialchars($guid, ENT_QUOTES, 'UTF-8') . "</guid>\n";
            echo "<pubDate>" . $pubDate . "</pubDate>\n";
            if (!empty($row['note'])) {
                echo "<description><![CDATA[" . stripTagsContent($row['note']) . "]]></description>\n";
            }
            echo "</item>\n";
        }

        echo "</channel>\n";
        echo "</rss>\n";
        exit;
    }
}