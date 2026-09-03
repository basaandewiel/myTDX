<?php
declare(strict_types=1);

namespace MyTDX;

class ExportHandler
{
    public static function export(): void
    {
        $format = $_GET['format'] ?? 'csv';
        $listId = (int)($_GET['list'] ?? 0);

        $onlyPublishedList = false;
        if (is_readonly()) {
            $onlyPublishedList = true;
        }

        $db = getDB();
        $listData = $db->sqa(
            "SELECT * FROM " . $db->table('lists') . " WHERE id=?" . ($onlyPublishedList ? " AND published=1" : ""),
            [$listId]
        );

        if (!$listData) {
            header('HTTP/1.1 404 Not Found');
            die('List not found');
        }

        if ($format === 'csv') {
            self::exportCSV($listData, $db);
        } elseif ($format === 'ical') {
            self::exportICAL($listData, $db);
        } else {
            header('HTTP/1.1 400 Bad Request');
            die('Unknown format');
        }
    }

    private static function exportCSV(array $listData, Database $db): void
    {
        $rows = $db->dq(
            "SELECT * FROM " . $db->table('todolist') . " WHERE list_id=? ORDER BY ow ASC",
            [(int)$listData['id']]
        )->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $listData['name']) . '.csv"');

        $out = fopen('php://output', 'w');

        // BOM for Excel
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, [$listData['name']]);
        fputcsv($out, []);
        fputcsv($out, ['Title', 'Note', 'Priority', 'Completed', 'Due Date', 'Tags', 'Date Created', 'Date Completed']);

        foreach ($rows as $row) {
            $completed = $row['compl'] ? 'yes' : 'no';
            fputcsv($out, [
                stripTagsContent($row['title']),
                stripTagsContent($row['note']),
                $row['prio'],
                $completed,
                $row['duedate'] ?: '',
                $row['tags'] ?: '',
                $row['d_created'] ? date('Y-m-d H:i', (int)$row['d_created']) : '',
                $row['d_completed'] ? date('Y-m-d H:i', (int)$row['d_completed']) : '',
            ]);
        }
        fclose($out);
        exit;
    }

    private static function exportICAL(array $listData, Database $db): void
    {
        $rows = $db->dq(
            "SELECT * FROM " . $db->table('todolist') . " WHERE list_id=? ORDER BY ow ASC",
            [(int)$listData['id']]
        )->fetchAll();

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $listData['name']) . '.ics"');

        $output = "BEGIN:VCALENDAR\r\n";
        $output .= "VERSION:2.0\r\n";
        $output .= "PRODID:-//myTDX//EN\r\n";
        $output .= "CALSCALE:GREGORIAN\r\n";
        $output .= "X-WR-CALNAME:" . self::icalText($listData['name']) . "\r\n";

        foreach ($rows as $row) {
            $output .= "BEGIN:VTODO\r\n";
            $output .= "UID:" . ($row['uuid'] ?: 'task-' . $row['id']) . '@mytdx\r\n';
            $output .= "DTSTAMP:" . gmdate('Ymd\THis\Z', (int)$row['d_edited']) . "\r\n";
            if ($row['d_created']) {
                $output .= "CREATED:" . gmdate('Ymd\THis\Z', (int)$row['d_created']) . "\r\n";
            }
            $output .= "SUMMARY:" . self::icalText(stripTagsContent($row['title'])) . "\r\n";
            if (!empty($row['note'])) {
                $output .= "DESCRIPTION:" . self::icalText(stripTagsContent($row['note'])) . "\r\n";
            }
            if ($row['compl']) {
                $output .= "STATUS:COMPLETED\r\n";
                if ($row['d_completed']) {
                    $output .= "COMPLETED:" . gmdate('Ymd\THis\Z', (int)$row['d_completed']) . "\r\n";
                }
            } else {
                $output .= "STATUS:NEEDS-ACTION\r\n";
            }
            if (!empty($row['duedate'])) {
                $output .= "DUE;VALUE=DATE:" . date('Ymd', strtotime($row['duedate'])) . "\r\n";
            }
            $output .= "END:VTODO\r\n";
        }

        $output .= "END:VCALENDAR\r\n";

        // Line folding at 75 octets (RFC 5545)
        $folded = '';
        foreach (preg_split('/\r\n/', $output) as $line) {
            $len = strlen($line);
            if ($len <= 75) {
                $folded .= $line . "\r\n";
            } else {
                $folded .= substr($line, 0, 75) . "\r\n";
                $line = substr($line, 75);
                while (strlen($line) > 0) {
                    $chunk = substr($line, 0, 74);
                    $folded .= ' ' . $chunk . "\r\n";
                    $line = substr($line, 74);
                }
            }
        }

        echo $folded;
        exit;
    }

    private static function icalText(string $s): string
    {
        return str_replace(["\r\n", "\r", "\n", ','], ['\\n', '', '\\n', '\\,'], $s);
    }
}