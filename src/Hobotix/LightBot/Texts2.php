<?php

namespace Hobotix\Lightbot;

final class Texts2{
    public const light_on_first_line = [
        '🙂 <b> Генератор включений </b>',
    ];

    public const light_on_second_line = [
        '⚡ Ми не з такої сім\'ї, ми з багатої, то працюємо на генераторі',
    ];

    public const light_off_first_line = [
        '😢 <b>  Генератор вимкнено!  </b>',
    ];

    public const light_off_second_line = [
        '⚡ Власне генератор не працює, то може включите?)'
    ];

    public static function get_random_text($status){
        if ($status){
            return self::light_on_first_line[array_rand(self::light_on_first_line)] . PHP_EOL . self::light_on_second_line[array_rand(self::light_on_second_line)] ;
        } else {
            return self::light_off_first_line[array_rand(self::light_off_first_line)] . PHP_EOL . self::light_off_second_line[array_rand(self::light_off_second_line)] ;
        }
    }
}