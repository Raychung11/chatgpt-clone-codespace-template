<?php
/**
 * STRate AI — AI Explanation Engine
 * Calls OpenAI to convert pricing numbers → human explanation.
 * Role: EXPLAIN only. Never calculate.
 * Falls back to rule-based text if API unavailable.
 */
class AiExplainer
{
    private const SYSTEM_PROMPT = <<<PROMPT
You are a revenue manager for short-term rental (STR) properties in Malaysia.
Your job is to explain pricing decisions in simple, clear English to property owners.

Rules:
- Maximum 2 sentences
- Be specific: mention the price, demand level, and top reason
- Use Malaysian context (RM currency, local events)
- Don't use jargon. Speak like a helpful advisor.
- Never say "I recommend" — state it as fact.

Example outputs:
"Set RM320 tonight — strong weekend demand with 85% area occupancy makes this the optimal price."
"Drop to RM185 for tomorrow — low demand period with competitors averaging RM170 means pricing lower fills the calendar."
PROMPT;

    /**
     * Generate explanation via OpenAI API.
     * Falls back gracefully if API is down or key is missing.
     */
    public static function explain(array $pricingResult, array $marketData): string
    {
        if (!defined('OPENAI_API_KEY') || OPENAI_API_KEY === '') {
            return self::fallback($pricingResult, $marketData);
        }

        $bd          = $pricingResult['breakdown'];
        $suggested   = $pricingResult['suggested_price'];
        $base        = $bd['base_price'];
        $changePct   = round((($suggested - $base) / $base) * 100, 1);
        $occ         = $bd['occupancy_rate'];
        $compAvg     = $bd['competitor_avg'];
        $event       = $bd['event_flag']  ? 'Yes' : 'No';
        $weekend     = $bd['is_weekend']  ? 'Yes' : 'No';
        $confidence  = round($pricingResult['confidence_score'] * 100);
        $demandLevel = PricingEngine::demandLabel($occ, $bd['event_flag']);

        $userPrompt = <<<PROMPT
Generate a pricing explanation for this property:

- Property base price: RM{$base}
- Suggested price: RM{$suggested}
- Price change: {$changePct}%
- Occupancy rate in area: {$occ}
- Competitor average price: RM{$compAvg}
- Event nearby: {$event}
- Weekend: {$weekend}
- Demand level: {$demandLevel}
- Confidence score: {$confidence}%

Breakdown of price adjustments:
- Demand factor: {$bd['demand_factor_pct']}%
- Event boost: {$bd['event_boost_pct']}%
- Competitor gap: {$bd['competitor_gap_pct']}%
- Occupancy adjustment: {$bd['occupancy_adj_pct']}%

Write 1-2 sentences explaining this recommendation to the property owner.
PROMPT;

        try {
            $payload = json_encode([
                'model'       => OPENAI_MODEL,
                'messages'    => [
                    ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                    ['role' => 'user',   'content' => $userPrompt],
                ],
                'max_tokens'  => 120,
                'temperature' => 0.4,
            ]);

            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . OPENAI_API_KEY,
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                $content = $data['choices'][0]['message']['content'] ?? '';
                if ($content) return trim($content);
            }
        } catch (Throwable $e) {
            // Fall through to fallback — pricing must never fail due to AI
        }

        return self::fallback($pricingResult, $marketData);
    }

    /**
     * Rule-based fallback — always works, no API needed.
     */
    public static function fallback(array $result, array $market): string
    {
        $bd        = $result['breakdown'];
        $suggested = $result['suggested_price'];
        $base      = $bd['base_price'];
        $changePct = round((($suggested - $base) / $base) * 100, 1);
        $occ       = $bd['occupancy_rate'];
        $event     = $bd['event_flag'];
        $weekend   = $bd['is_weekend'];
        $compAvg   = $bd['competitor_avg'];
        $conf      = round($result['confidence_score'] * 100);
        $direction = $changePct >= 0 ? 'Increase' : 'Reduce';

        $reasons = [];
        if ($event)                          $reasons[] = 'nearby event driving demand';
        if ($weekend)                        $reasons[] = 'weekend demand surge';
        if ($occ >= 0.80)                    $reasons[] = sprintf('high area occupancy (%d%%)', $occ * 100);
        elseif ($occ <= 0.40)                $reasons[] = sprintf('low demand period (occupancy %d%%)', $occ * 100);
        if ($result['competitor_gap'] > 0.10)  $reasons[] = sprintf('competitors averaging RM%d', $compAvg);
        elseif ($result['competitor_gap'] < -0.10) $reasons[] = sprintf('competitors pricing lower at RM%d', $compAvg);

        $reasonStr = !empty($reasons) ? implode(', ', $reasons) : 'current market conditions';

        if (abs($changePct) < 1.0) {
            return sprintf(
                'Maintain RM%d — market conditions are stable with no significant demand shifts. Confidence: %d%%.',
                $suggested, $conf
            );
        }

        return sprintf(
            '%s to RM%d (%+.1f%%) due to %s. This maximizes revenue at %d%% confidence.',
            $direction, $suggested, $changePct, $reasonStr, $conf
        );
    }

    /**
     * Short WhatsApp-style reply for messaging integration.
     */
    public static function whatsappReply(string $propertyName, array $rec): string
    {
        $price     = (float) $rec['suggested_price'];
        $base      = (float) $rec['base_price'];
        $changePct = $base > 0 ? round((($price - $base) / $base) * 100, 1) : 0;
        $arrow     = $changePct >= 0 ? '▲' : '▼';
        $reason    = $rec['reason'] ?? '';

        // First sentence only for brevity
        $shortReason = strstr($reason, '.', true) ?: $reason;

        return sprintf(
            "*STRate AI — %s*\n\n💰 Set price: *RM%d* %s (%+.1f%%)\n\n📊 %s.\n\n_Confidence: %d%%_",
            $propertyName,
            $price,
            $arrow,
            $changePct,
            $shortReason,
            round((float)$rec['confidence_score'] * 100)
        );
    }
}
