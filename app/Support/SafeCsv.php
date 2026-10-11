<?php

namespace App\Support;

class SafeCsv
{
    public static function write($stream, array $cells): int|false
    {
        return fputcsv($stream, array_map(static function ($cell) {
            // Spreadsheet applications evaluate formulas even inside quoted CSV cells.
            return is_string($cell) && preg_match('/^(?:[\x00-\x20]*[=+@-]|[\t\r\n])/', $cell)
                ? "'".$cell : $cell;
        }, $cells), ',', '"', '');
    }
}
