<?php
/**
 * STRate AI — Pricing Engine
 * Deterministic rule-based pricing. NO AI here — pure PHP math.
 *
 * Formula:
 *   final_price = base_price × (1 + demand_factor + event_boost + competitor_gap + occupancy_adj)
 *   clamped to [min_price, max_price]
 */
class PricingEngine
{
    // ─── Factor Calculators ───────────────────────────────────────────────

    /**
     * Demand factor based on occupancy rate + weekend bonus.
     * High occupancy = raise price. Low = lower.
     */
    private static function demandFactor(float $occupancyRate, bool $isWeekend): float
    {
        if ($occupancyRate >= 0.85)      $base = 0.18;
        elseif ($occupancyRate >= 0.70)  $base = 0.10;
        elseif ($occupancyRate >= 0.55)  $base = 0.00;
        elseif ($occupancyRate >= 0.40)  $base = -0.08;
        else                             $base = -0.15;

        return round($base + ($isWeekend ? 0.05 : 0.0), 4);
    }

    /**
     * Event boost: nearby events drive a 20% premium.
     */
    private static function eventBoost(bool $eventFlag): float
    {
        return $eventFlag ? 0.20 : 0.0;
    }

    /**
     * Competitor gap: if competitors charge more, we can raise.
     * Absorbs 50% of the gap, capped at ±25%.
     */
    private static function competitorGap(float $basePrice, float $competitorAvg): float
    {
        if ($competitorAvg <= 0 || $basePrice <= 0) return 0.0;
        $gap = (($competitorAvg - $basePrice) / $basePrice) * 0.5;
        return round(max(-0.25, min($gap, 0.25)), 4);
    }

    /**
     * Occupancy adjustment: last-minute fill strategy.
     * Drop price if date is near AND occupancy is very low.
     */
    private static function occupancyAdj(float $occupancyRate, int $daysAhead): float
    {
        if ($daysAhead <= 2 && $occupancyRate < 0.40) return -0.10;
        if ($daysAhead <= 7 && $occupancyRate < 0.30) return -0.07;
        return 0.0;
    }

    /**
     * Confidence score: higher when signals agree.
     * Penalised when factors conflict.
     */
    private static function confidenceScore(
        float $demandFactor,
        float $eventBoost,
        float $competitorGap,
        float $occupancyRate
    ): float {
        $signals = [];

        $signals[] = ($occupancyRate >= 0.75 || $occupancyRate <= 0.35) ? 0.90 : 0.60;
        if ($eventBoost > 0)             $signals[] = 0.95;
        $signals[] = abs($competitorGap) > 0.05 ? 0.80 : 0.70;

        // Conflict penalty
        $penalty = 0.0;
        if ($demandFactor > 0 && $competitorGap < -0.10) $penalty = 0.15;
        elseif ($demandFactor < 0 && $competitorGap > 0.10) $penalty = 0.10;

        $raw = array_sum($signals) / count($signals) - $penalty;
        return round(max(0.40, min($raw, 0.99)), 2);
    }

    // ─── Main Engine ──────────────────────────────────────────────────────

    /**
     * Calculate the recommended price for a property.
     *
     * @param array $property  {base_price, min_price, max_price}
     * @param array $market    {avg_price, occupancy_rate, event_flag}
     * @param bool  $isWeekend
     * @param int   $daysAhead days until target date (for urgency logic)
     * @return array           Full pricing output with breakdown
     */
    public static function calculate(
        array $property,
        array $market,
        bool $isWeekend = false,
        int $daysAhead = 0
    ): array {
        $basePrice      = (float) $property['base_price'];
        $minPrice       = (float) $property['min_price'];
        $maxPrice       = (float) $property['max_price'];
        $occupancyRate  = (float) $market['occupancy_rate'];
        $competitorAvg  = (float) $market['avg_price'];
        $eventFlag      = (bool)  $market['event_flag'];

        $df = self::demandFactor($occupancyRate, $isWeekend);
        $eb = self::eventBoost($eventFlag);
        $cg = self::competitorGap($basePrice, $competitorAvg);
        $oa = self::occupancyAdj($occupancyRate, $daysAhead);

        $totalAdj = $df + $eb + $cg + $oa;
        $rawPrice = $basePrice * (1 + $totalAdj);

        // Hard guardrails — always respect owner's min/max
        $finalPrice = round(max($minPrice, min($rawPrice, $maxPrice)));

        $confidence = self::confidenceScore($df, $eb, $cg, $occupancyRate);

        return [
            'suggested_price'     => $finalPrice,
            'confidence_score'    => $confidence,
            'demand_factor'       => $df,
            'event_boost'         => $eb,
            'competitor_gap'      => $cg,
            'occupancy_adj'       => $oa,
            'total_adjustment_pct'=> round($totalAdj * 100, 1),
            'breakdown' => [
                'base_price'          => $basePrice,
                'demand_factor_pct'   => round($df * 100, 1),
                'event_boost_pct'     => round($eb * 100, 1),
                'competitor_gap_pct'  => round($cg * 100, 1),
                'occupancy_adj_pct'   => round($oa * 100, 1),
                'raw_price'           => round($rawPrice, 2),
                'final_price'         => $finalPrice,
                'clamped'             => ($rawPrice != $finalPrice),
                'occupancy_rate'      => $occupancyRate,
                'is_weekend'          => $isWeekend,
                'event_flag'          => $eventFlag,
                'competitor_avg'      => $competitorAvg,
            ],
        ];
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    public static function demandLabel(float $occupancyRate, bool $eventFlag = false): string
    {
        if ($eventFlag && $occupancyRate >= 0.75) return 'Very High 🔥';
        if ($occupancyRate >= 0.80) return 'High 🔴';
        if ($occupancyRate >= 0.60) return 'Medium 🟡';
        if ($occupancyRate >= 0.40) return 'Low 🟢';
        return 'Very Low ⚪';
    }

    public static function changePct(float $suggested, float $base): float
    {
        return $base > 0 ? round((($suggested - $base) / $base) * 100, 1) : 0;
    }
}
