<?php

namespace Hobotix\Lightbot;

use DateTimeImmutable;
use DateTimeZone;

final class Schedule
{
    public const SOURCES = [
        'kyiv-region' => 'https://www.dtek-krem.com.ua/ua/shutdowns',
        'kyiv' => 'https://www.dtek-kem.com.ua/ua/shutdowns',
    ];
    private const STATES = [
        'yes' => ['on', 'on'], 'no' => ['off', 'off'], 'maybe' => ['possible', 'possible'],
        'first' => ['off', 'on'], 'second' => ['on', 'off'],
        'mfirst' => ['possible', 'on'], 'msecond' => ['on', 'possible'],
    ];

    public static function local(DateTimeImmutable $time): DateTimeImmutable
    {
        return $time->setTimezone(new DateTimeZone('Europe/Kyiv'));
    }

    public static function instant(int $epoch): DateTimeImmutable
    {
        return self::local(new DateTimeImmutable('@' . $epoch));
    }

    public static function normalize(array $raw, string $region, string $group): array
    {
        if (!isset(self::SOURCES[$region]) || ($raw['regionId'] ?? null) !== $region
            || !preg_match('/\AGPV\d{1,2}\.[12]\z/', $group)
            || !is_bool($raw['lastUpdateStatus']['ok'] ?? null)
            || !is_array($raw['fact']['data'] ?? null)
            || !is_string($raw['lastUpdated'] ?? null)) {
            throw new \UnexpectedValueException('Invalid outage feed');
        }
        $updated = strtotime($raw['lastUpdated']);
        if ($updated === false) {
            throw new \UnexpectedValueException('Invalid feed timestamp');
        }
        $days = $raw['fact']['data'];
        ksort($days, SORT_NUMERIC);
        if (count($days) > 4) {
            throw new \UnexpectedValueException('Too many schedule days');
        }
        $slots = [];
        foreach ($days as $epoch => $groups) {
            if (!is_array($groups)) {
                throw new \UnexpectedValueException('Invalid schedule groups');
            }
            $hours = $groups[$group] ?? [];
            if ($hours === []) {
                continue; // Unpublished is not an all-ON day.
            }
            if (!is_array($hours) || count($hours) !== 24 || !ctype_digit((string)$epoch)) {
                throw new \UnexpectedValueException('Invalid schedule day');
            }
            $midnight = self::instant((int)$epoch);
            if ($midnight->format('H:i:s') !== '00:00:00') {
                throw new \UnexpectedValueException('Schedule day must start at Kyiv midnight');
            }
            for ($hour = 1; $hour <= 24; $hour++) {
                $value = $hours[$hour] ?? null;
                $zone = $raw['preset']['time_zone'][$hour] ?? null;
                if (!is_string($value) || !isset(self::STATES[$value]) || !is_array($zone)
                    || ($zone[1] ?? null) !== sprintf('%02d:00', $hour - 1)
                    || ($zone[2] ?? null) !== sprintf('%02d:00', $hour)) {
                    throw new \UnexpectedValueException('Unknown or missing schedule slot');
                }
            }
            $end = $midnight->modify('+1 day')->getTimestamp();
            for ($start = (int)$epoch; $start < $end; $start += 1800) {
                $local = self::instant($start);
                $state = self::STATES[$hours[(int)$local->format('G') + 1]][(int)$local->format('i') < 30 ? 0 : 1];
                $last = array_key_last($slots);
                if ($last !== null && $slots[$last]['state'] === $state && $slots[$last]['end'] === $start) {
                    $slots[$last]['end'] = $start + 1800;
                } else {
                    $slots[] = ['start' => $start, 'end' => $start + 1800, 'state' => $state];
                }
            }
        }
        $planUpdated = $raw['fact']['update'] ?? null;
        return [
            'region' => $region, 'group' => $group, 'source_url' => self::SOURCES[$region],
            'feed_updated_at' => $updated, 'collector_ok' => $raw['lastUpdateStatus']['ok'],
            'plan_updated_at' => is_string($planUpdated) && preg_match('/\A\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}\z/', $planUpdated) ? $planUpdated : null,
            'slots' => $slots,
        ];
    }

    public static function forecast(array $snapshot, bool $on, DateTimeImmutable $now): string
    {
        if (empty($snapshot['data'])) {
            return 'Графік ДТЕК недоступний — час наступної зміни не підтверджено.';
        }
        if ($snapshot['stale'] ?? true) {
            return 'Графік застарів — час ' . ($on ? 'вимкнення' : 'увімкнення') . ' не підтверджено.';
        }
        $slots = $snapshot['data']['slots'];
        $epoch = $now->getTimestamp();
        $index = null;
        foreach ($slots as $i => $slot) {
            if ($slot['start'] <= $epoch && $epoch < $slot['end']) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            return 'ДТЕК ще не опублікував графік на цей час.';
        }
        $current = $slots[$index];
        if ($current['state'] === 'possible') {
            return 'За планом можливе відключення. Час наступної зміни не підтверджено.';
        }
        if (!$on && $current['state'] === 'on') {
            return 'За графіком світло вже мало бути. Час фактичного повернення невідомий.';
        }
        if ($on && $current['state'] === 'off') {
            return 'Світло є попри планове відключення. Час наступної зміни не підтверджено.';
        }
        $next = $slots[$index + 1] ?? null;
        if ($next === null || $next['start'] !== $current['end']) {
            return $on ? 'Наступних відключень в опублікованому графіку немає.'
                : 'Відключення триває до кінця опублікованого графіка. Час повернення ще невідомий.';
        }
        $when = self::instant($next['start']);
        $label = $when->format('Y-m-d') === self::local($now)->format('Y-m-d') ? $when->format('H:i') : $when->format('d.m H:i');
        if ($next['state'] === 'possible') {
            return 'З ' . $label . ' — можливе відключення. Час ' . ($on ? 'вимкнення' : 'увімкнення') . ' не підтверджено.';
        }
        return 'За планом ДТЕК: ' . ($on ? 'наступне вимкнення о ' : 'увімкнення о ') . $label;
    }

    public static function day(array $snapshot, DateTimeImmutable $now): array
    {
        $result = ['status' => 'unpublished', 'off' => [], 'possible' => [], 'off_seconds' => 0];
        if (empty($snapshot['data']) || ($snapshot['stale'] ?? true)) {
            $result['status'] = 'unavailable';
            return $result;
        }
        $start = self::local($now)->setTime(0, 0)->getTimestamp();
        $end = self::local($now)->setTime(0, 0)->modify('+1 day')->getTimestamp();
        $cursor = $start;
        foreach ($snapshot['data']['slots'] as $slot) {
            $a = max($start, $slot['start']);
            $b = min($end, $slot['end']);
            if ($a >= $b) {
                continue;
            }
            if ($a !== $cursor) {
                return ['status' => 'unpublished', 'off' => [], 'possible' => [], 'off_seconds' => 0];
            }
            $cursor = $b;
            if ($slot['state'] !== 'on') {
                $result[$slot['state']][] = ['start' => $a, 'end' => $b];
                if ($slot['state'] === 'off') {
                    $result['off_seconds'] += $b - $a;
                }
            }
        }
        if ($cursor !== $end) {
            return ['status' => 'unpublished', 'off' => [], 'possible' => [], 'off_seconds' => 0];
        }
        $result['status'] = 'published';
        return $result;
    }
}
