"""
STRate AI — AI Explanation Engine
Uses OpenAI to convert pricing numbers → human explanation.
Role: EXPLAIN only. Never calculate.
"""
import os
from typing import Optional

try:
    from openai import OpenAI
    _OPENAI_AVAILABLE = True
except ImportError:
    _OPENAI_AVAILABLE = False

from .pricing_engine import PricingOutput, demand_label


SYSTEM_PROMPT = """You are a revenue manager for short-term rental (STR) properties in Malaysia.
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
"""

USER_PROMPT_TEMPLATE = """Generate a pricing explanation for this property:

- Property base price: RM{base_price}
- Suggested price: RM{suggested_price}
- Price change: {change_pct:+.1f}%
- Occupancy rate in area: {occupancy_pct:.0f}%
- Competitor average price: RM{competitor_avg}
- Event nearby: {event_status}
- Weekend: {weekend_status}
- Demand level: {demand_level}
- Confidence score: {confidence:.0f}%

Breakdown of price adjustments:
- Demand factor: {demand_factor:+.1f}%
- Event boost: {event_boost:+.1f}%
- Competitor gap: {competitor_gap:+.1f}%
- Occupancy adjustment: {occupancy_adj:+.1f}%

Write 1-2 sentences explaining this recommendation to the property owner."""


def _fallback_explanation(output: PricingOutput, market_data: dict) -> str:
    """
    Rule-based fallback when OpenAI is unavailable.
    Covers the most common scenarios deterministically.
    """
    price = output.suggested_price
    base = output.breakdown["base_price"]
    change_pct = output.total_adjustment_pct
    occ = output.breakdown["occupancy_rate"]
    event = output.breakdown["event_flag"]
    is_weekend = output.breakdown["is_weekend"]
    direction = "Increase" if change_pct > 0 else "Reduce"
    action = "to" if change_pct > 0 else "to"

    reasons = []
    if event:
        reasons.append("nearby event driving demand")
    if is_weekend:
        reasons.append("weekend demand surge")
    if occ >= 0.80:
        reasons.append(f"high area occupancy ({occ*100:.0f}%)")
    elif occ <= 0.40:
        reasons.append(f"low demand period (occupancy {occ*100:.0f}%)")
    if abs(output.competitor_gap) >= 0.10:
        comp_avg = output.breakdown.get("competitor_avg", 0)
        if output.competitor_gap > 0:
            reasons.append(f"competitors averaging RM{comp_avg:.0f}")
        else:
            reasons.append(f"competitors pricing lower at RM{comp_avg:.0f}")

    reason_str = ", ".join(reasons) if reasons else "current market conditions"

    if abs(change_pct) < 1.0:
        return (
            f"Maintain RM{price:.0f} — market conditions are stable with no significant "
            f"demand shifts. Confidence: {output.confidence_score*100:.0f}%."
        )

    return (
        f"{direction} {action} RM{price:.0f} ({change_pct:+.1f}%) due to {reason_str}. "
        f"This maximizes revenue at {output.confidence_score*100:.0f}% confidence."
    )


def generate_explanation(
    output: PricingOutput,
    market_data: dict,
    api_key: Optional[str] = None,
) -> str:
    """
    Generate a human-readable explanation for the pricing recommendation.
    Falls back to rule-based text if OpenAI is unavailable.
    """
    if api_key is None:
        api_key = os.environ.get("OPENAI_API_KEY", "")

    if not _OPENAI_AVAILABLE or not api_key:
        return _fallback_explanation(output, market_data)

    base_price = output.breakdown["base_price"]
    suggested = output.suggested_price
    change_pct = ((suggested - base_price) / base_price) * 100

    prompt = USER_PROMPT_TEMPLATE.format(
        base_price=base_price,
        suggested_price=suggested,
        change_pct=change_pct,
        occupancy_pct=output.breakdown["occupancy_rate"] * 100,
        competitor_avg=output.breakdown.get("competitor_avg", "N/A"),
        event_status="Yes" if output.breakdown["event_flag"] else "No",
        weekend_status="Yes" if output.breakdown["is_weekend"] else "No",
        demand_level=demand_label(output.breakdown["occupancy_rate"], output.breakdown["event_flag"]),
        confidence=output.confidence_score * 100,
        demand_factor=output.demand_factor * 100,
        event_boost=output.event_boost * 100,
        competitor_gap=output.competitor_gap * 100,
        occupancy_adj=output.occupancy_adj * 100,
    )

    try:
        client = OpenAI(api_key=api_key)
        response = client.chat.completions.create(
            model="gpt-4o-mini",
            messages=[
                {"role": "system", "content": SYSTEM_PROMPT},
                {"role": "user", "content": prompt},
            ],
            max_tokens=120,
            temperature=0.4,
        )
        return response.choices[0].message.content.strip()
    except Exception as e:
        # Always fall back gracefully — pricing must never fail due to AI
        return _fallback_explanation(output, market_data)


def generate_whatsapp_reply(property_name: str, rec: dict) -> str:
    """
    Short WhatsApp-style reply format.
    Used by the messaging integration layer.
    """
    price = rec.get("suggested_price", 0)
    base = rec.get("base_price", price)
    change_pct = ((price - base) / base * 100) if base > 0 else 0
    reason = rec.get("reason", "")

    # Extract first sentence only for WhatsApp brevity
    short_reason = reason.split(".")[0] if reason else "current market conditions"

    direction = "▲" if change_pct >= 0 else "▼"
    return (
        f"*STRate AI — {property_name}*\n\n"
        f"💰 Set price: *RM{price:.0f}* {direction} ({change_pct:+.1f}%)\n\n"
        f"📊 {short_reason}.\n\n"
        f"_Confidence: {rec.get('confidence_score', 0)*100:.0f}%_"
    )
