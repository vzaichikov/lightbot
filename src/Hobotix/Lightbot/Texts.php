<?php

namespace Hobotix\Lightbot;

final class Texts{
   public const BLOCKS = [

        /*
         |--------------------------------------------------------------------------
         | DEFAULT — є генератор
         |--------------------------------------------------------------------------
         */
        'default' => [

            'light_on_first_line' => [
                '🙂 <b>Ну шо, дочекались.</b>',
                '🙂 <b>Світло вернулось, алілуя.</b>',
                '🙂 <b>Перемога локального масштабу.</b>',
                '🙂 <b>Електричні боги сьогодні добрі.</b>',
            ],

            'light_on_second_line' => [
                '⚡ Електрика з мережі знову з нами.',
                '⚡ Генератор можна трохи відпустити.',
                '⚡ Розетки знову не декоративні.',
                '⚡ Живемо як нормальні люди. Тимчасово.',
            ],

            'light_off_first_line' => [
                '😐 <b>Ну от і все.</b>',
                '😐 <b>Світло сказало «па-па».</b>',
                '😐 <b>Темрява знову виграла цей раунд.</b>',
                '😐 <b>Хто там казав «та вже точно не вимкнуть»?</b>',
            ],

            'light_off_second_line' => [
                '🔌 Мережа впала. Генератор, виходь.',
                '🔌 Світла нема. Зате є характер.',
                '🔌 Знову автономка, знову виживаємо.',
                '🔌 Електрика пішла, але ми ще тримаємось.',
            ],

            'light_on_duration' => [
                '🕒 Світла не було %d год %d хв. Ми це пережили.',
                '🕒 %d год %d хв без світла. Досвід зараховано.',
                '🕒 Темрява тривала %d:%02d. Не найгірший результат.',
            ],

            'light_off_duration' => [
                '🕒 Світло трималось %d год %d хв. Непогано.',
                '🕒 %d год %d хв нормального життя. Запамʼятаємо.',
                '🕒 %d:%02d зі світлом. Було приємно.',
            ],
        ],

        /*
         |--------------------------------------------------------------------------
         | CHARMPLE — студія pole dance
         |--------------------------------------------------------------------------
         */
        'charmpole' => [

            'light_on_first_line' => [
                '💃 <b>ЄЄЄ! Світло знову з нами!</b>',
                '💃 <b>Чудо! Дівчата, ми знову бачимо одне одного!</b>',
                '💃 <b>Світло є! Лампи горять, життя налагоджується.</b>',
                '💃 <b>Ну шо, простітутки, світло включили.</b>',
            ],

            'light_on_second_line' => [
                '🔥 Електричні пушки знову гріють, не тільки серця.',
                '🔥 Газову пушку можна трохи образити і вимкнути.',
                '🔥 Танцюємо з теплом і світлом, як цивілізація.',
                '🔥 Зал знову не як підвал у фільмі жахів.',
            ],

            'light_off_first_line' => [
                '🕯 <b>Пічалька. Світло знову пішло.</b>',
                '🕯 <b>Світло вимкнули. Романтика від Укренерго.</b>',
                '🕯 <b>Ну шо, темна сторона знову перемагає.</b>',
                '🕯 <b>Зрада! Дівчата, приглушуємо драму, включаємо характер.</b>',
            ],

            'light_off_second_line' => [
                '🔥 Переходимо на газ і акум. Танцюємо виживання.',
                '🔥 Світла нема, але жердина є. Пріоритети.',
                '🔥 Темно, тепло і трохи злі. Класика.',
                '🔥 Танцюємо в напівтемряві. Атмосферно, бляха.',
            ],

            'light_on_duration' => [
                '🕒 Темрява тривала %d год %d хв. Але ми витримали.',
                '🕒 %d:%02d без світла. Дівчата — сталеві.',
                '🕒 %d год %d хв танців майже наосліп.',
            ],

            'light_off_duration' => [
                '🕒 Світло було %d год %d хв. Запамʼятали.',
                '🕒 %d:%02d нормальних тренувань. Цінуємо.',
                '🕒 %d год %d хв тепла і світла. Було кайфово.',
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
            . PHP_EOL . PHP_EOL
            . $durationLine;
    }
}
