<?php

namespace Hobotix\Lightbot;

final class Texts{
    public const light_on_first_line = [
        '🙂 <b>Перемога!</b>',
        '🙂 <b>Щастя прийшло в наш дім!</b>',
        '🙂 <b>Вітаю вас, пані та панове!</b>',
    ];

    public const light_on_second_line = [
        '⚡ Електрохарчування від мережі увімкнено!',
        '⚡ Оце світло увімкнули тільки шо, нічого собі',
        '⚡ Тепер маєм електрохарчування з мережі',
        '⚡ Бігом виключайте генератора, пожалуйста, світло цейво есть вже',
        '⚡ Я вже сам оце не вірю в чудєса, але електрохарчування включилось'
    ];

    public const light_off_first_line = [
        '😢 <b>  Зрада!  </b>',
        '🌩️ <b> Bobr Kurwa!!! </b>',
        '🤕 <b> O kurwa, ja perdole! </b>',
        '😢 <b> Ніколи такого не було, і от знову </b>'
    ];

    public const light_off_second_line = [
        '⚡ Світло вимкнули, якщо бажаєте мати електрохарчування - вмикайте генератора!',
        '⚡ Знову немає світла, оце прям тільки шо взяли й вимкнули',
        '⚡ Електрохарчування відсутнє з цієї хвилини',
        '⚡ Знову іти оце генератора вмикати треба, світло вимкнули',
        '⚡ Мені це вже не нравиться, скільки оце можна вимикати електрохарчування',
    ];

    public static function get_random_text($status){
        if ($status){
            return self::light_on_first_line[array_rand(self::light_on_first_line)] . PHP_EOL . self::light_on_second_line[array_rand(self::light_on_second_line)] ;
        } else {
            return self::light_off_first_line[array_rand(self::light_off_first_line)] . PHP_EOL . self::light_off_second_line[array_rand(self::light_off_second_line)] ;
        }
    }
}