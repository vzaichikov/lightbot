<?php

namespace Hobotix\Lightbot;

final class Texts{
    public const BLOCKS = [
        'default' => [
            'light_on_first_line' => [
                '🙂 <b>Перемога!</b>',
                '🙂 <b>Щастя прийшло в наш дім!</b>',
                '🙂 <b>Вітаю вас, пані та панове!</b>',
            ],
            'light_on_second_line' => [
                '⚡ Електрохарчування від мережі увімкнено!',
                '⚡ Оце світло увімкнули тільки шо, нічого собі',
                '⚡ Тепер маєм електрохарчування з мережі',
                '⚡ Бігом виключайте генератора, пожалуйста, світло цейво есть вже',
                '⚡ Я вже сам оце не вірю в чудєса, але електрохарчування включилось',
            ],
            'light_on_duration' => [
                '⏱️ Ми сиділи без електроенергії %d годин %d хвилин.',
                '⏱️ Без світла було %d годин %d хвилин.',
                '⏱️ Відключення тривало %d годин %d хвилин.',
            ],
            'light_off_first_line' => [
                '😢 <b>  Зрада!  </b>',
                '🌩️ <b> Bobr Kurwa!!! </b>',
                '🤕 <b> O kurwa, ja perdole! </b>',
                '😢 <b> Ніколи такого не було, і от знову </b>',
            ],
            'light_off_second_line' => [
                '⚡ Світло вимкнули, якщо бажаєте мати електрохарчування - вмикайте генератора!',
                '⚡ Знову немає світла, оце прям тільки шо взяли й вимкнули',
                '⚡ Електрохарчування відсутнє з цієї хвилини',
                '⚡ Знову іти оце генератора вмикати треба, світло вимкнули',
                '⚡ Мені це вже не нравиться, скільки оце можна вимикати електрохарчування',
            ],
            'light_off_duration' => [
                '⏱️ Світло було %d годин %d хвилин.',
                '⏱️ Ми мали електроенергію %d годин %d хвилин.',
                '⏱️ Живлення трималося %d годин %d хвилин.',
            ],
        ],
        'charmpole' => [
            'light_on_first_line' => [
                '✨ <b>Світло повернулося!</b>',
                '✨ <b>Енергія знову з нами!</b>',
                '✨ <b>Вітаємо з поверненням світла!</b>',
            ],
            'light_on_second_line' => [
                '⚡ Мережа доступна, можна працювати!',
                '⚡ Світло є, все працює!',
                '⚡ Електрохарчування стабільне, тримаємо темп!',
            ],
            'light_on_duration' => [
                '⏱️ Без світла були %d годин %d хвилин.',
                '⏱️ Відключення тривало %d годин %d хвилин.',
                '⏱️ Сиділи без енергії %d годин %d хвилин.',
            ],
            'light_off_first_line' => [
                '🌑 <b>Темрява повернулась...</b>',
                '🌩️ <b>Світло зникло!</b>',
                '🥀 <b>Немає живлення.</b>',
            ],
            'light_off_second_line' => [
                '⚡ Мережа недоступна, час на резерви.',
                '⚡ Електрохарчування відсутнє, чекаємо на відновлення.',
                '⚡ Світло зникло, готуємо нашу газову пушечку.',
            ],
            'light_off_duration' => [
                '⏱️ Світло було %d годин %d хвилин.',
                '⏱️ Мережа трималася %d годин %d хвилин.',
                '⏱️ Електрохарчування було %d годин %d хвилин.',
            ],
        ],
    ];

    private static function resolveBlock(string $block): array
    {
        if (isset(self::BLOCKS[$block])) {
            return self::BLOCKS[$block];
        }

        return self::BLOCKS['default'];
    }

    public static function get_random_text($status, string $block = 'default')
    {
        $set = self::resolveBlock($block);
        if ($status){
            return $set['light_on_first_line'][array_rand($set['light_on_first_line'])]
                . PHP_EOL
                . $set['light_on_second_line'][array_rand($set['light_on_second_line'])];
        }

        return $set['light_off_first_line'][array_rand($set['light_off_first_line'])]
            . PHP_EOL
            . $set['light_off_second_line'][array_rand($set['light_off_second_line'])];
    }

    public static function get_random_text_with_duration($status, int $hours, int $minutes, string $block = 'default')
    {
        $set = self::resolveBlock($block);
        $durationKey = $status ? 'light_on_duration' : 'light_off_duration';
        $durationLine = sprintf(
            $set[$durationKey][array_rand($set[$durationKey])],
            $hours,
            $minutes
        );

        return self::get_random_text($status, $block)
            . PHP_EOL
            . $durationLine;
    }
}
