<?php
declare(strict_types=1);

namespace Refugio\Support;

final class MarketingMetrics
{
    public static function divide(int|float|string|null $numerator,int|float|string|null $denominator):?float{$d=(float)$denominator;return$d==0.0?null:(float)$numerator/$d;}
    public static function cpl(mixed$spend,mixed$leads):?float{return self::divide($spend,$leads);}
    public static function costPerReservation(mixed$spend,mixed$reservations):?float{return self::divide($spend,$reservations);}
    public static function roas(mixed$revenue,mixed$spend):?float{return self::divide($revenue,$spend);}
    public static function conversionRate(mixed$reservations,mixed$leads):?float{$v=self::divide($reservations,$leads);return$v===null?null:$v*100;}
}
