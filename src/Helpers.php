<?php
declare(strict_types=1);

namespace MyTDX;

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ejs(string $s): string
{
    return addcslashes($s, "\\\'\"\n\r\t/<>");
}

function generateUUID(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function escapeTags(string $s): string
{
    $c1 = "\x01";
    $c2 = "\x02";
    $s = preg_replace('~<b>([\s\S]*?)</b>~i', "{$c1}b{$c2}$1{$c1}/b{$c2}", $s);
    $s = preg_replace('~<i>([\s\S]*?)</i>~i', "{$c1}i{$c2}$1{$c1}/i{$c2}", $s);
    $s = preg_replace('~<u>([\s\S]*?)</u>~i', "{$c1}u{$c2}$1{$c1}/u{$c2}", $s);
    $s = preg_replace('~<s>([\s\S]*?)</s>~i', "{$c1}s{$c2}$1{$c1}/s{$c2}", $s);
    $s = str_replace([$c1, $c2], ['<', '>'], htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    return $s;
}

function stripTagsContent(string $s): string
{
    $c1 = "\x01";
    $c2 = "\x02";
    $s = preg_replace('~<b>([\s\S]*?)</b>~i', '$1', $s);
    $s = preg_replace('~<i>([\s\S]*?)</i>~i', '$1', $s);
    $s = preg_replace('~<u>([\s\S]*?)</u>~i', '$1', $s);
    $s = preg_replace('~<s>([\s\S]*?)</s>~i', '$1', $s);
    return $s;
}

function nl2brHtml(string $s): string
{
    return nl2br(htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
}

/**
 * Lightweight markdown rendering (no external deps).
 * Supports: inline code, bold, italic, strikethrough, links, headings,
 * unordered/ordered lists, blockquotes, fenced and indented code blocks, hr.
 * Input is treated as plain text (rendered output is HTML-escaped).
 */
function markdownToHtml(string $s): string
{
    $s = str_replace(["\r\n", "\r"], "\n", $s);

    $codeBlocks = [];
    $placeholder = function (int $idx): string {
        return "{###CODE$idx###}";
    };

    // Fenced code blocks ```lang ... ```
    $s = preg_replace_callback('/```([^\n]*)\n([\s\S]*?)(?:```|$)/', function ($m) use (&$codeBlocks, $placeholder) {
        $codeBlocks[] = '<pre><code>' . htmlspecialchars(rtrim($m[2], "\n"), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code></pre>';
        return $placeholder(count($codeBlocks) - 1);
    }, $s);

    // Indented code blocks (4 spaces or tab), must be followed by blank line
    $s = preg_replace_callback('/(?:^|\n)((?:[ \t]{4,}[^\n]*\n?)+)(?=\n|$)/', function ($m) use (&$codeBlocks, $placeholder) {
        $text = rtrim($m[1], "\n");
        $text = preg_replace('/^[ \t]{4,}/m', '', $text);
        $codeBlocks[] = '<pre><code>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code></pre>';
        return "\n" . $placeholder(count($codeBlocks) - 1);
    }, "\n" . $s . "\n");

    $esc = function (string $t): string {
        return htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };

    $inline = function (string $line) use ($esc): string {
        $line = preg_replace_callback('/`([^`]+)`/', function ($m) use ($esc) {
            return '<code>' . $esc($m[1]) . '</code>';
        }, $line);

        $line = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function ($m) use ($esc) {
            $url = filter_var($m[2], FILTER_VALIDATE_URL);
            if ($url === false) {
                return $esc($m[0]);
            }
            return '<a href="' . $esc($url) . '" rel="nofollow" target="_blank">' . $esc($m[1]) . '</a>';
        }, $line);

        $line = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $line);
        $line = preg_replace('/__([^_]+)__/', '<strong>$1</strong>', $line);
        $line = preg_replace('/(?<!\*)\*([^*\s][^*]*)\*(?!\*)/', '<em>$1</em>', $line);
        $line = preg_replace('/(?<!_)_([^_\s][^_]*)_(?!_)/', '<em>$1</em>', $line);
        $line = preg_replace('/~~([^~]+)~~/', '<del>$1</del>', $line);
        return $line;
    };

    $lines = explode("\n", $s);
    $out = [];
    $i = 0;
    $n = count($lines);
    $listType = null;
    $listDepth = 0;

    $escapeLines = array_map($esc, $lines);

    while ($i < $n) {
        $line = $escapeLines[$i];

        if (trim($line) === '') {
            if ($listType !== null) {
                while ($listDepth > 0) {
                    $out[] = '</' . $listType . '>';
                    $listDepth--;
                }
                $out[] = '</' . $listType . '>';
                $listType = null;
            }
            $i++;
            continue;
        }

        // Code block placeholder
        if (preg_match('/^\{###CODE(\d+)###\}$/', trim($line), $m)) {
            if ($listType !== null) {
                while ($listDepth > 0) {
                    $out[] = '</' . $listType . '>';
                    $listDepth--;
                }
                $out[] = '</' . $listType . '>';
                $listType = null;
            }
            $out[] = $codeBlocks[(int)$m[1]];
            $i++;
            continue;
        }

        if (preg_match('/^(#{1,6})\s+(.*)$/', $line, $m)) {
            if ($listType !== null) {
                while ($listDepth > 0) {
                    $out[] = '</' . $listType . '>';
                    $listDepth--;
                }
                $out[] = '</' . $listType . '>';
                $listType = null;
            }
            $level = strlen($m[1]);
            $out[] = '<h' . $level . '>' . $inline($m[2]) . '</h' . $level . '>';
            $i++;
            continue;
        }

        if (preg_match('/^\s*([-*_])\s*(\1\s*){2,}$/', $line)) {
            if ($listType !== null) {
                while ($listDepth > 0) {
                    $out[] = '</' . $listType . '>';
                    $listDepth--;
                }
                $out[] = '</' . $listType . '>';
                $listType = null;
            }
            $out[] = '<hr>';
            $i++;
            continue;
        }

        if (preg_match('/^&gt;\s?(.*)$/', $line, $m)) {
            if ($listType !== null) {
                while ($listDepth > 0) {
                    $out[] = '</' . $listType . '>';
                    $listDepth--;
                }
                $out[] = '</' . $listType . '>';
                $listType = null;
            }
            $block = [];
            while ($i < $n && preg_match('/^&gt;\s?(.*)$/', $escapeLines[$i], $qm)) {
                $block[] = $inline($qm[1]);
                $i++;
            }
            $out[] = '<blockquote>' . implode('<br>', $block) . '</blockquote>';
            continue;
        }

        if (preg_match('/^(\s*)[-*+]\s+(.*)$/', $line, $m)) {
            $indent = strlen($m[1]);
            $depth = intdiv($indent, 2);

            if ($listType !== 'ul') {
                if ($listType !== null) {
                    while ($listDepth > 0) {
                        $out[] = '</' . $listType . '>';
                        $listDepth--;
                    }
                    $out[] = '</' . $listType . '>';
                }
                $listType = 'ul';
                $listDepth = 0;
                $out[] = '<ul>';
            }

            while ($listDepth < $depth) {
                $out[] = '<ul>';
                $listDepth++;
            }
            while ($listDepth > $depth) {
                $out[] = '</ul>';
                $listDepth--;
            }

            $out[] = '<li>' . $inline($m[2]) . '</li>';
            $i++;
            continue;
        }

        if (preg_match('/^(\s*)\d+[.)]\s+(.*)$/', $line, $m)) {
            $indent = strlen($m[1]);
            $depth = intdiv($indent, 2);

            if ($listType !== 'ol') {
                if ($listType !== null) {
                    while ($listDepth > 0) {
                        $out[] = '</' . $listType . '>';
                        $listDepth--;
                    }
                    $out[] = '</' . $listType . '>';
                }
                $listType = 'ol';
                $listDepth = 0;
                $out[] = '<ol>';
            }

            while ($listDepth < $depth) {
                $out[] = '<ol>';
                $listDepth++;
            }
            while ($listDepth > $depth) {
                $out[] = '</ol>';
                $listDepth--;
            }

            $out[] = '<li>' . $inline($m[2]) . '</li>';
            $i++;
            continue;
        }

        if ($listType !== null) {
            while ($listDepth > 0) {
                $out[] = '</' . $listType . '>';
                $listDepth--;
            }
            $out[] = '</' . $listType . '>';
            $listType = null;
        }
        $para = [$inline($line)];
        $i++;
        while ($i < $n && trim($escapeLines[$i]) !== '' && !preg_match('/^(#{1,6})\s|\{###CODE|^\s*([-*_])\s*(\1\s*){2,}$|^&gt;\s|^\s*[-*+]\s|^\s*\d+[.)]\s/', $escapeLines[$i])) {
            $para[] = $inline($escapeLines[$i]);
            $i++;
        }
        $out[] = '<p>' . implode('<br>', $para) . '</p>';
    }

    if ($listType !== null) {
        while ($listDepth > 0) {
            $out[] = '</' . $listType . '>';
            $listDepth--;
        }
        $out[] = '</' . $listType . '>';
    }

    return implode("\n", $out);
}



function jsonExit(mixed $data): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function intParam(mixed $val): int
{
    return (int)$val;
}

function strParam(mixed $val): string
{
    return (string)$val;
}

function getBaseUrl(): string
{
    if (isset($_SERVER['REQUEST_URI'])) {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if ($path !== false && $path !== null && $path !== '') {
            $dir = preg_replace('#/[^/]*\.php$#', '/', $path);
            if (substr($dir, -1) !== '/') {
                $dir .= '/';
            }
            return $dir;
        }
    }
    return '/';
}

function timestampToDatetime(int $timestamp): string
{
    $format = Config::get('dateformat', 'j M Y');
    $clock = Config::get('clock', 24);
    $format .= $clock == 12 ? ' g:i A' : ' H:i';
    return formatTime($format, $timestamp);
}

function formatTime(string $format, int $timestamp = 0): string
{
    $lang = \Lang::instance();
    if ($timestamp === 0) {
        $timestamp = time();
    }
    $newformat = strtr($format, ['F' => '%1', 'M' => '%2']);
    $adate = explode(',', date('n,' . $newformat, $timestamp), 2);
    $s = $adate[1] ?? '';
    if ($newformat !== $format) {
        $am = (int)($adate[0] ?? 1);
        $ml = $lang->get('months_long');
        $ms = $lang->get('months_short');
        $F = $ml[$am - 1] ?? '';
        $M = $ms[$am - 1] ?? '';
        $s = strtr($s, ['%1' => $F, '%2' => $M]);
    }
    return $s;
}

function formatDateForDisplay(string $format, int $year, int $month, int $day): string
{
    $lang = \Lang::instance();
    $ml = $lang->get('months_long');
    $ms = $lang->get('months_short');
    $Y = $year;
    $y = $Y < 2010 ? '0' . ($Y - 2000) : (string)($Y - 2000);
    $n = $month;
    $m = $n < 10 ? '0' . $n : (string)$n;
    $F = $ml[$month - 1] ?? '';
    $M = $ms[$month - 1] ?? '';
    $j = $day;
    $d = $j < 10 ? '0' . $j : (string)$j;
    return strtr($format, ['Y' => $Y, 'y' => $y, 'F' => $F, 'M' => $M, 'n' => $n, 'm' => $m, 'd' => $d, 'j' => $j]);
}

function parseDuedate(string $s): ?string
{
    $s = trim($s);
    if ($s === '') {
        return null;
    }
    $config = Config::all();
    $df = $config['dateformat2'] ?? 'Y-m-d';

    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $s, $m)) {
        return sprintf('%04d-%02d-%02d', (int)$m[1], min((int)$m[2], 12), min((int)$m[3], 31));
    }
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $s, $m)) {
        $a = $m[1]; $b = $m[2];
        if ((int)$a > 12) {
            return sprintf('%04d-%02d-%02d', (int)$m[3], min((int)$b, 12), min((int)$a, 31));
        }
        return sprintf('%04d-%02d-%02d', (int)$m[3], min((int)$a, 12), min((int)$b, 31));
    }
    if (preg_match('#^(\d{1,2})\.(\d{1,2})\.(\d{4})$#', $s, $m)) {
        return sprintf('%04d-%02d-%02d', (int)$m[3], min((int)$m[2], 12), min((int)$m[1], 31));
    }
    if (preg_match('#^(\d{1,2})/(\d{1,2})$#', $s, $m)) {
        $y = (int)date('Y');
        return sprintf('%04d-%02d-%02d', $y, min((int)$m[1], 12), min((int)$m[2], 31));
    }
    if (preg_match('#^(\d{1,2})\.(\d{1,2})$#', $s, $m)) {
        $y = (int)date('Y');
        return sprintf('%04d-%02d-%02d', $y, min((int)$m[1], 12), min((int)$m[2], 31));
    }
    return null;
}

function prepareDuedate(?string $duedate): array
{
    if (empty($duedate)) {
        return ['class' => '', 'str' => '', 'formatted' => '', 'timestamp' => 0];
    }
    $ts = strtotime($duedate);
    if ($ts === false) {
        return ['class' => '', 'str' => '', 'formatted' => '', 'timestamp' => 0];
    }
    $today = strtotime('today');
    $diff = $ts - $today;
    $daySec = 86400;

    if ($diff < 0) {
        $class = 'past';
    } elseif ($diff < $daySec) {
        $class = 'today';
    } elseif ($diff < 3 * $daySec) {
        $class = 'soon';
    } else {
        $class = 'future';
    }

    $days = (int)floor($diff / $daySec);
    $lang = \Lang::instance();
    if ($days < 0) {
        $str = sprintf($lang->get('duedate_past'), abs($days));
    } elseif ($days === 0) {
        $str = $lang->get('duedate_today');
    } elseif ($days === 1) {
        $str = $lang->get('duedate_tomorrow');
    } elseif ($days < 7) {
        $str = sprintf($lang->get('duedate_in_days'), $days);
    } else {
        $fmt = Config::get('dateformat') ?: 'j M Y';
        $year = (int)date('Y', $ts);
        if ($year === (int)date('Y')) {
            $fmt = trim(str_replace('Y', '', str_replace('y', '', $fmt)));
        }
        $str = formatDateForDisplay($fmt, $year, (int)date('n', $ts), (int)date('j', $ts));
    }

    return [
        'class' => $class,
        'str' => $str,
        'formatted' => date(Config::get('dateformat2', 'Y-m-d'), $ts),
        'timestamp' => $ts,
    ];
}

function date2int(string $d): int
{
    if (empty($d)) {
        return 33330000;
    }
    return (int)date('Ymd', strtotime($d));
}

function parseSmartSyntax(string $title): array|false
{
    $prio = 0;
    $tags = '';
    if (preg_match('#^(/(?:\+|-)?(?:1|2)/)?(.+?)(?:\s+(/[^/]+/))?$#s', $title, $m)) {
        if (!empty($m[1])) {
            $p = trim($m[1], '/');
            $prio = (int)$p;
            if ($prio < -1) $prio = -1;
            if ($prio > 2) $prio = 2;
        }
        $title = trim($m[2]);
        if (!empty($m[3])) {
            $tags = trim(trim($m[3]), '/');
        }
        return ['prio' => $prio, 'title' => $title, 'tags' => $tags];
    }
    return false;
}

function prepareTags(string $tagsStr): array|false
{
    $tagsStr = trim($tagsStr);
    if ($tagsStr === '') {
        return false;
    }
    $tags = preg_split('/\s*[;,]\s*|\s+(?=[@=~!#])/', $tagsStr);
    $result = [];
    foreach ($tags as $tag) {
        $tag = trim($tag);
        if ($tag === '') continue;
        $tag = preg_replace('/["\'<>&\/\\\\^]/', '', $tag);
        if ($tag !== '') {
            $result[] = $tag;
        }
    }
    return $result ?: false;
}
