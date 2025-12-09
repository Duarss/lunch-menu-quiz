<?php

namespace App\Helpers;

use Exception;
use Throwable;
use Illuminate\Support\Facades\DB;

/**
 * Kirana stand for "Kumpulan Fungsi, Rumus, dan Lainnya"
 */
class Kirana
{
    /**
     *	Convert int number to Roman numbers.
     *
     *   @param  int  $number number that being converted.
     *   @return string ex: 1 => I, 10 => X
     **/
    public static function intToRoman(int $number): string
    {
        $map = array('M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1);
        $returnValue = '';
        while ($number > 0) {
            foreach ($map as $roman => $int) {
                if ($number >= $int) {
                    $number -= $int;
                    $returnValue .= $roman;
                    break;
                }
            }
        }
        return $returnValue;
    }
    /**
     *	Convert number to Indonesian long day.
     *
     *   @param  int  $day day number that being converted.
     *   @param  string  $sunday M for Minggu, and A for Ahad.
     *   @return string ex: 1 => Senin
     **/
    public static function longDay(int $day, string $sunday = 'M'): string
    {
        if ($sunday == 'M') {
            $sunday = 'Minggu';
        } elseif ($sunday == 'A') {
            $sunday = 'Ahad';
        }

        $longDay = [
            0 => $sunday,
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => $sunday,
        ];

        return $longDay[$day];
    }

    /**
     *	Convert number to Indonesian long month.
     *
     *   @param  int  $month month number that being converted.
     *   @return string ex: 1 => Januari
     **/
    public static function longMonth(int $month): string
    {
        $longMonth = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        return $longMonth[$month];
    }

    /**
     *	Convert number to Indonesian short day.
     *
     *   @param  int  $day day number that being converted.
     *   @param  int  $length total length string.
     *   @param  string  $sunday M for Minggu, and A for Ahad.
     *   @return string ex: 1 => Sen
     **/
    public static function shortDay(int $day, int $length = 3, string $sunday = 'M'): string
    {
        if ($sunday == 'M') {
            $sunday = substr('Minggu', 0, $length);
        } elseif ($sunday == 'A') {
            $sunday = substr('Ahad', 0, $length);
        }

        $longDay = [
            0 => $sunday,
            1 => substr('Senin', 0, $length),
            2 => substr('Selasa', 0, $length),
            3 => substr('Rabu', 0, $length),
            4 => substr('Kamis', 0, $length),
            5 => substr('Jumat', 0, $length),
            6 => substr('Sabtu', 0, $length),
            7 => $sunday,
        ];

        return $longDay[$day];
    }

    /**
     *	Convert number to Indonesian sort month.
     *
     *   @param  int  $month month number that being converted.
     *   @param  int  $length total length string.
     *   @return string ex: 1 => Jan
     **/
    public static function shortMonth(int $month, int $length = 3): string
    {
        $shortMonth = [
            1 => substr('Januari', 0, $length),
            2 => substr('Februari', 0, $length),
            3 => substr('Maret', 0, $length),
            4 => substr('April', 0, $length),
            5 => substr('Mei', 0, $length),
            6 => substr('Juni', 0, $length),
            7 => substr('Juli', 0, $length),
            8 => substr('Agustus', 0, $length),
            9 => substr('September', 0, $length),
            10 => substr('Oktober', 0, $length),
            11 => substr('November', 0, $length),
            12 => substr('Desember', 0, $length),
        ];

        return $shortMonth[$month];
    }

    /**
     *	Convert date to Indonesian Long Date Format with day.
     *
     *   @param  string  $date the date that being converted.
     *   @param  string  $sunday M for Minggu, and A for Ahad.
     *   @return string ex: 2020-01-22 => Kamis, 22 Januari 2020
     **/
    public static function toLongDateDay(string $date, string $sunday = 'M'): string
    {
        $splited = str_split('0' . date('wdmy', strtotime($date)), 2);
        $dayText = self::longDay(intval($splited[0]), $sunday);
        $day = $splited[1];
        $month = self::longMonth(intval($splited[2]));
        $year = date('Y', strtotime($date));

        return "$dayText, $day $month $year";
    }

    /**
     *	Convert date to Indonesian Long Date Format with day and time.
     *
     *   @param  string  $date the date that being converted.
     *   @param  string  $sunday M for Minggu, and A for Ahad.
     *   @param  string  $separator separator between date and time.
     *   @param  bool  $timeFirst if true, the time will be on the front.
     *   @return string ex: 2020-01-22 16:00:00 => Kamis, 22 Januari 2020 16:00:00
     **/
    public static function toLongDateDayTime(string $date, string $sunday = 'M', string $separator = ' ', bool $timeFirst = false): string
    {
        if ($timeFirst) {
            return date('H:i:s', strtotime($date)) . $separator . self::toLongDateDay($date, $sunday);
        }

        return self::toLongDateDay($date, $sunday) . $separator . date('H:i:s', strtotime($date));
    }

    /**
     *	Convert date to Indonesian Long Date Format.
     *
     *   @param  string  $date the date that being converted.
     *   @return string ex: 2020-01-22 => 22 Januari 2020
     **/
    public static function toLongDate(string $date): string
    {
        $splited = str_split(date('dmy', strtotime($date)), 2);

        $day = $splited[0];
        $month = self::longMonth(intval($splited[1]));
        $year = date('Y', strtotime($date));

        return "$day $month $year";
    }

    /**
     *	Convert date to Indonesian Long Date Format with time.
     *
     *   @param  string  $date the date that being converted.
     *   @param  string  $separator separator between date and time.
     *   @param  bool  $timeFirst if true, the time will be on the front.
     *   @return string ex: 2020-01-22 16:00:00 => 22 Januari 2020 16:00:00
     **/
    public static function toLongDateTime(string $date, string $separator = ' ', bool $timeFirst = false): string
    {
        if ($timeFirst) {
            return date('H:i:s', strtotime($date)) . $separator . self::toLongDate($date);
        }

        return self::toLongDate($date) . $separator . date('H:i:s', strtotime($date));
    }

    /**
     *	Convert date to Indonesian Short Date Format with day
     *
     *   @param  string  $date the date that being converted.
     *   @param  int  $dayLength total length string for day.
     *   @param  int  $monthLength total length string for month.
     *   @param  string  $sunday M for Minggu, and A for Ahad.
     *   @return string ex: 2020-01-22 => Kam, 22 Jan 2020
     **/
    public static function toShortDateDay(string $date, int $dayLength = 3, int $monthLength = 3, string $sunday = 'M'): string
    {
        $splited = str_split('0' . date('wdmy', strtotime($date)), 2);

        $dayText = self::shortDay(intval($splited[0]), $dayLength, $sunday);
        $day = $splited[1];
        $month = self::shortMonth(intval($splited[2]), $monthLength);
        $year = date('Y', strtotime($date));

        return "$dayText, $day $month $year";
    }

    /**
     *	Convert date to Indonesian Short Date Format with day and time.
     *
     *   @param  string  $date the date that being converted.
     *   @param  int  $dayLength total length string for day.
     *   @param  int  $monthLength total length string for month.
     *   @param  string  $sunday M for Minggu, and A for Ahad.
     *   @param  string  $separator separator between date and time.
     *   @param  bool  $timeFirst if true, the time will be on the front.
     *   @return string ex: 2020-01-22 16:00:00 => Kam, 22 Jan 2020 16:00
     **/
    public static function toShortDateDayTime(string $date, int $dayLength = 3, int $monthLength = 3, string $sunday = 'M', string $separator = ' ', bool $timeFirst = false): string
    {
        if ($timeFirst) {
            return date('H:i', strtotime($date)) . $separator . self::toShortDateDay($date, $dayLength, $monthLength, $sunday);
        }

        return self::toShortDateDay($date, $dayLength, $monthLength, $sunday) . $separator . date('H:i', strtotime($date));
    }

    /**
     *	Convert date to Indonesian Short Date Format.
     *
     *   @param  string  $date the date that being converted.
     *   @param  int  $monthLength total month length string.
     *   @return string ex: 2020-01-22 => 22 Jan 2020
     **/
    public static function toShortDate(string $date, int $monthLength = 3): string
    {
        $splited = str_split(date('dmy', strtotime($date)), 2);

        $day = $splited[0];
        $month = self::shortMonth(intval($splited[1]), $monthLength);
        $year = date('Y', strtotime($date));

        return "$day $month $year";
    }

    /**
     *	Convert date to Indonesian Short Date Format with time.
     *
     *   @param  string  $date the date that being converted.
     *   @param  int  $monthLength total month length string.
     *   @param  string  $separator separator between date and time.
     *   @param  bool  $timeFirst if true, the time will be on the front.
     *   @return string ex: 2020-01-22 16:00:00 => 22 Jan 2020 16:00
     **/
    public static function toShortDateTime(string $date, int $dayLength = 3, int $monthLength = 3, string $sunday = 'M', string $separator = ' ', bool $timeFirst = false): string
    {
        if ($timeFirst) {
            return date('H:i', strtotime($date)) . $separator . self::toShortDate($date, $monthLength);
        }

        return self::toShortDate($date, $monthLength) . $separator . date('H:i', strtotime($date));
    }

    public static function toLongMonthYear(string $date): string
    {
        $splited = str_split(date('my', strtotime($date)), 2);

        $month = self::longMonth(intval($splited[0]));
        $year = date('Y', strtotime($date));

        return "$month $year";
    }

    public static function toTime(string $datetime): string
    {
        return date('H:i', strtotime($datetime));
    }
    public static function dateDiff(string $dateToCompare, string $dateCompareWith = null): string
    {
        $now = $dateCompareWith ? strtotime($dateCompareWith) : strtotime(date('Y-m-d'));
        $dayCount = round(($now - strtotime($dateToCompare)) / (60 * 60 * 24));
        return $dayCount;
    }

    /**
     *	Convert number to Indonesian Format Number.
     *
     *   @param  float  $value the value that being converted.
     *   @param  int  $decimals decimal point after coma.
     *   @param  string  $thousands_separator separator for thousands.
     *   @param  string  $decimals_separator separator for decimals.
     *   @return string ex: 150000 => 150.000 | 150000,15 => 150.000,15
     **/
    public static function numberFormat(int|float $value, int $decimals = 0, string $thousands_separator = ',', string $decimals_separator = '.')
    {
        return number_format($value, $decimals, $decimals_separator, $thousands_separator);
    }
    /**
     *	Convert number to Currency.
     *
     *   @param  int  $value the value that being converted.
     *   @param  string  $currency that used after converted.
     *   @param  int  $decimals decimal point after coma.
     *   @param  string  $thousands_separator separator for thousands.
     *   @param  string  $decimals_separator separator for decimals.
     *   @return string ex: 150000 => Rp 150.000 | 150000,15 => Rp 150.000,15
     **/
    public static function toCurrency(int $value, string $currency = "Rp", int $decimals = 0, string $thousands_separator = '.', string $decimals_separator = ',')
    {
        return ($currency ? $currency . " " : "") . (self::numberFormat($value, $decimals, $thousands_separator, $decimals_separator));
    }
    /**
     *	Convert number to Wrap Format.
     *
     *   @param  string  $value the value that being converted.
     *   @param  int  $n_digits separator is added every $n_digits.
     *   @param  string  $separator that used to seperate converted value.
     *   @return string ex: 123456789 => 1234-5678-9
     **/
    public static function wordWrap(string $value, int $n_digits = 4, string $separator = '-'): string
    {
        return wordwrap($value, $n_digits, $separator, true);
    }

    /**
     * Stand alone encryption method to encrypt by stymiee/php-simple-encryption. This function is used to handle encryption that can be decrypt from other laravel
     * Make sure to install the package before use this function
     * ex: composer require stymiee/php-simple-encryption
     *
     * @param string $plantext
     * @return string
     **/
    public static function encrypt(string $planText): string
    {
        try {
            $encryption = \Encryption\Encryption::getEncryptionObject();
            return $encryption->encrypt($planText, config('app.encryption.key'), config('app.encryption.iv'));
        } catch (Throwable $th) {
            throw new Exception("Gagal mengamankan token", 422);
        }
    }
    /**
     * Stand alone encryption method to decrypt by stymiee/php-simple-encryption. This function is used to handle encryption that can be decrypt from other laravel
     * Make sure to install the package before use this function
     * ex: composer require stymiee/php-simple-encryption
     *
     * @param string $chiperText
     * @return string
     **/
    public static function decrypt(string $chiperText): string
    {
        try {
            $encryption = \Encryption\Encryption::getEncryptionObject();
            return $encryption->decrypt($chiperText, config('app.encryption.key'), config('app.encryption.iv'));
        } catch (Throwable $e) {
            throw new Exception("Gagal mendapatkan token", 422);
        }
    }
}
