<?php

namespace App\Support;

class CsvCell
{
    // Excel で開いたとき、数式として動かないようにする（R1）
    public static function safe(string $value): string
    {
        // 先頭がこれらの文字だと、Excel は数式として扱う
        $dangerous = ['=', '+', '-', '@'];   // 1つ目は、数式の始まりを表す文字（=）

        if ($value !== '' && in_array($value[0], $dangerous, true)) {
            return "'" . $value;   // 先頭に付けて、文字として扱わせる記号（'）
        }

        return $value;
    }
}
