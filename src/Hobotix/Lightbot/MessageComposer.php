<?php

namespace Hobotix\Lightbot;

use DateTimeImmutable;

final class MessageComposer
{
    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function footer(string $domain, array $config, array $snapshot): array
    {
        $dtek = $config['dtek'] ?? [];
        $group = preg_replace('/^GPV/', '', $dtek['group'] ?? '—');
        $location = self::escape(($dtek['address'] ?? $domain) . ' · група ' . $group);
        $updated = $snapshot['data']['plan_updated_at'] ?? null;
        $details = $location . ($updated ? "\nПлан ДТЕК: " . self::escape($updated) : '');
        $details .= "\nПлан може змінитися. Фактичний стан визначає бот.";
        $source = Schedule::SOURCES[$dtek['region'] ?? ''] ?? null;
        $links = $source ? '<a href="' . $source . '">ДТЕК</a>' : '';
        if (preg_match('/\A[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?\z/i', $domain)) {
            $links .= ($links ? ' · ' : '') . '<a href="https://' . $domain . '">Стан світла</a>';
        }
        $links .= ($links ? ' · ' : '') . '<a href="https://github.com/Baskerville42/outage-data-ua">Дзеркало даних</a>';
        return ['rich' => '<footer>' . nl2br($details, false) . '</footer><p>' . $links . '</p>',
            'basic' => '<i>' . $details . '</i>' . "\n" . $links];
    }

    public function event(string $domain, array $config, bool $on, int $hours, int $minutes, array $snapshot, DateTimeImmutable $now): array
    {
        $title = ($on ? '🟢 СВІТЛО Є' : '🔴 СВІТЛА НЕМАЄ') . ' · ' . self::escape($config['label'] ?? $domain);
        $copy = Texts::get_random_text($on, $config['texts_block'] ?? 'default');
        $duration = Texts::duration($on, $hours, $minutes, $config['texts_block'] ?? 'default');
        $forecast = self::escape(Schedule::forecast($snapshot, $on, $now));
        $time = Schedule::local($now)->format('d.m.Y H:i');
        $footer = $this->footer($domain, $config, $snapshot);
        return [
            'rich' => '<h2>' . $title . '</h2><p>' . $time . '</p><p>' . nl2br($copy, false)
                . '</p><blockquote><b>' . $forecast . '</b></blockquote><p>' . $duration . '</p><hr>' . $footer['rich'],
            'basic' => '<b>' . $title . '</b>' . "\n" . $time . "\n\n" . $copy . "\n\n<b>" . $forecast
                . "</b>\n\n" . $duration . "\n\n" . $footer['basic'],
        ];
    }

    public function digest(string $domain, array $config, array $snapshot, DateTimeImmutable $now): array
    {
        $title = '📅 ДТЕК · ' . Schedule::local($now)->format('d.m.Y') . ' · ' . self::escape($config['label'] ?? $domain);
        $openers = ($config['texts_block'] ?? '') === 'charmpole'
            ? ['Плануємо танці, а не цю їбану темряву.', 'Графік для пілона: де світло, а де пиздець.', 'Розминайтесь, дівчата. ДТЕК уже розімʼяв нам нерви.']
            : ['Ось коли розетки планують піти нахуй.', 'Ранковий план виживання. Кава, зарядка і матюки.', 'ДТЕК намалював день. Тримайте розклад цієї хуйні.'];
        $opener = $openers[array_rand($openers)];
        $day = Schedule::day($snapshot, $now);
        $rich = '<h2>' . $title . '</h2><p>' . $opener . '</p>';
        $basic = '<b>' . $title . "</b>\n" . $opener . "\n\n";
        if ($day['status'] !== 'published') {
            $text = $day['status'] === 'unpublished' ? 'ДТЕК ще не опублікував графік на сьогодні.'
                : 'Свіжий графік ДТЕК недоступний. Час відключень не підтверджено.';
            $rich .= '<blockquote>' . $text . '</blockquote>';
            $basic .= $text;
        } else {
            if ($day['off'] === []) {
                $rich .= '<p><b>Планових відключень сьогодні не заплановано.</b></p>';
                $basic .= "Планових відключень сьогодні не заплановано.\n";
            }
            foreach (['off' => '🔴 Без світла', 'possible' => '🟡 Можливі відключення'] as $key => $label) {
                if ($day[$key] === []) {
                    continue;
                }
                $rich .= '<h3>' . $label . '</h3><table compact><tr><th>Від</th><th>До</th></tr>';
                $basic .= "\n<b>" . $label . "</b>\n";
                foreach ($day[$key] as $slot) {
                    $start = Schedule::instant($slot['start'])->format('H:i');
                    $end = Schedule::instant($slot['end'])->format('H:i');
                    if ($end === '00:00') {
                        $end = '24:00';
                    }
                    $rich .= '<tr><td>' . $start . '</td><td>' . $end . '</td></tr>';
                    $basic .= $start . '–' . $end . "\n";
                }
                $rich .= '</table>';
            }
            $total = sprintf('Разом планово без світла: %d год %d хв', intdiv($day['off_seconds'], 3600), intdiv($day['off_seconds'] % 3600, 60));
            $rich .= '<p><b>' . $total . '</b></p>';
            $basic .= "\n" . $total;
        }
        $footer = $this->footer($domain, $config, $snapshot);
        return ['rich' => $rich . '<hr>' . $footer['rich'], 'basic' => $basic . "\n\n" . $footer['basic']];
    }

    /** Explicit, manually requested verification; example events never change monitoring state. */
    public function verification(string $domain, array $config, array $snapshot, DateTimeImmutable $now): array
    {
        $greeting = 'Вітаю! Мене звати Хоботун, я найрозумніший AI-агент в світі. Пишу вам за дорученням свого повелителя Віктора Найвеличнішого.';
        $notice = 'ПЕРЕВІРКА ОФОРМЛЕННЯ: ON/OFF — ПРИКЛАДИ, НЕ РЕАЛЬНІ ПОДІЇ. Тривалості умовні. Графік ДТЕК нижче — отриманий зараз план на сьогодні.';
        $on = $this->event($domain, $config, true, 2, 30, $snapshot, $now);
        $off = $this->event($domain, $config, false, 3, 15, $snapshot, $now);
        $digest = $this->digest($domain, $config, $snapshot, $now);
        return [
            'rich' => '<p>' . $greeting . '</p><blockquote><b>' . $notice . '</b></blockquote>'
                . '<h3>1. Приклад: світло зʼявилось</h3>' . $on['rich']
                . '<h3>2. Приклад: світло зникло</h3>' . $off['rich']
                . '<h3>3. Справжній план ДТЕК на сьогодні</h3>' . $digest['rich'],
            'basic' => $greeting . "\n\n<b>" . $notice . "</b>\n\n1. ПРИКЛАД ON\n" . $on['basic']
                . "\n\n2. ПРИКЛАД OFF\n" . $off['basic'] . "\n\n3. ПЛАН НА СЬОГОДНІ\n" . $digest['basic'],
        ];
    }
}
