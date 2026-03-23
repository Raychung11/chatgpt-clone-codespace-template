"""
STRate AI — Pricing Engine
Deterministic rule-based pricing logic. NO AI here — pure math.

Formula:
  final_price = base_price * (1 + demand_factor + event_boost + competitor_gap + occ_adj)
  clamped to [min_price, max_price]
"""
from dataclasses import dataclass
from typing import Optional


@dataclass
class PricingInput:
    base_price: float
    min_price: float
    max_price: float
    occupancy_rate: float        # 0.0 – 1.0
    competitor_avg_price: float
    event_flag: bool = False
    is_weekend: bool = False
    days_ahead: int = 0          # how far in future (urgency factor)


@dataclass
class PricingOutput:
    suggested_price: float
    confidence_score: float      # 0.0 – 1.0
    demand_factor: float
    event_boost: float
    competitor_gap: float
    occupancy_adj: float
    total_adjustment_pct: float
    breakdown: dict


# ─── Factor Calculators ───────────────────────────────────────────────────────

def _demand_factor(occupancy_rate: float, is_weekend: bool) -> float:
    """
    High occupancy = raise price, low = lower.
    Weekend adds a flat bonus.
    """
    if occupancy_rate >= 0.85:
        base = 0.18
    elif occupancy_rate >= 0.70:
        base = 0.10
    elif occupancy_rate >= 0.55:
        base = 0.0
    elif occupancy_rate >= 0.40:
        base = -0.08
    else:
        base = -0.15

    weekend_bonus = 0.05 if is_weekend else 0.0
    return round(base + weekend_bonus, 4)


def _event_boost(event_flag: bool) -> float:
    """Events drive demand spikes — add 20% premium."""
    return 0.20 if event_flag else 0.0


def _competitor_gap(base_price: float, competitor_avg: float) -> float:
    """
    If competitors are charging more than us → we can raise.
    If competitors are cheaper → we need to be careful.
    Capped at ±25% to prevent runaway pricing.
    """
    if competitor_avg <= 0 or base_price <= 0:
        return 0.0
    gap = (competitor_avg - base_price) / base_price
    # Dampen: only absorb 50% of the gap
    gap = gap * 0.5
    return round(max(-0.25, min(gap, 0.25)), 4)


def _occupancy_adjustment(occupancy_rate: float, days_ahead: int) -> float:
    """
    Last-minute pricing: if property likely to sit empty
    (low occupancy + date is near), drop price to fill.
    """
    if days_ahead <= 2 and occupancy_rate < 0.40:
        return -0.10
    if days_ahead <= 7 and occupancy_rate < 0.30:
        return -0.07
    return 0.0


def _confidence_score(
    demand_factor: float,
    event_boost: float,
    competitor_gap: float,
    occupancy_rate: float,
) -> float:
    """
    Confidence is higher when signals agree.
    Lower when factors conflict (e.g. high occupancy but low competitor prices).
    """
    signals = []

    # Occupancy signal strength
    if occupancy_rate >= 0.75 or occupancy_rate <= 0.35:
        signals.append(0.9)
    else:
        signals.append(0.6)

    # Event gives high confidence
    if event_boost > 0:
        signals.append(0.95)

    # Competitor data quality
    if abs(competitor_gap) > 0.05:
        signals.append(0.8)
    else:
        signals.append(0.7)

    # Conflict penalty: if demand is high but competitor is cheap
    if demand_factor > 0 and competitor_gap < -0.10:
        conflict_penalty = 0.15
    elif demand_factor < 0 and competitor_gap > 0.10:
        conflict_penalty = 0.10
    else:
        conflict_penalty = 0.0

    raw = sum(signals) / len(signals) - conflict_penalty
    return round(max(0.40, min(raw, 0.99)), 2)


# ─── Main Engine ─────────────────────────────────────────────────────────────

def calculate_price(inp: PricingInput) -> PricingOutput:
    """
    Core pricing function.
    All inputs → deterministic output → no randomness, no AI.
    """
    df = _demand_factor(inp.occupancy_rate, inp.is_weekend)
    eb = _event_boost(inp.event_flag)
    cg = _competitor_gap(inp.base_price, inp.competitor_avg_price)
    oa = _occupancy_adjustment(inp.occupancy_rate, inp.days_ahead)

    total_adj = df + eb + cg + oa
    raw_price = inp.base_price * (1 + total_adj)

    # Enforce hard min/max guardrails
    final_price = max(inp.min_price, min(raw_price, inp.max_price))
    final_price = round(final_price, 0)  # round to nearest RM

    confidence = _confidence_score(df, eb, cg, inp.occupancy_rate)

    return PricingOutput(
        suggested_price=final_price,
        confidence_score=confidence,
        demand_factor=df,
        event_boost=eb,
        competitor_gap=cg,
        occupancy_adj=oa,
        total_adjustment_pct=round(total_adj * 100, 1),
        breakdown={
            "base_price": inp.base_price,
            "demand_factor_pct": round(df * 100, 1),
            "event_boost_pct": round(eb * 100, 1),
            "competitor_gap_pct": round(cg * 100, 1),
            "occupancy_adj_pct": round(oa * 100, 1),
            "raw_price": round(raw_price, 2),
            "final_price": final_price,
            "clamped": raw_price != final_price,
            "occupancy_rate": inp.occupancy_rate,
            "is_weekend": inp.is_weekend,
            "event_flag": inp.event_flag,
            "competitor_avg": inp.competitor_avg_price,
        }
    )


def demand_label(occupancy_rate: float, event_flag: bool = False) -> str:
    """Human-readable demand level."""
    if event_flag and occupancy_rate >= 0.75:
        return "🔥 Very High"
    if occupancy_rate >= 0.80:
        return "🔴 High"
    if occupancy_rate >= 0.60:
        return "🟡 Medium"
    if occupancy_rate >= 0.40:
        return "🟢 Low"
    return "⚪ Very Low"
