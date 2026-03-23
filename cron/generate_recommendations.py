#!/usr/bin/env python3
"""
STRate AI — Cron Job: Generate Price Recommendations
Run daily at 6AM: 0 6 * * * python3 /path/cron/generate_recommendations.py

For each active property:
  1. Loads market data for target dates
  2. Runs pricing engine
  3. Calls AI for explanation
  4. Saves recommendation to DB
"""
import sys
import time
import os
from datetime import datetime, timedelta

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from strate_ai.database import (
    init_db, log_cron, get_all_properties,
    get_market_data, upsert_recommendation
)
from strate_ai.pricing_engine import PricingInput, calculate_price
from strate_ai.ai_explainer import generate_explanation

JOB_NAME = "generate_recommendations"
DAYS_AHEAD = 7  # Generate recommendations for next 7 days


def process_property(prop: dict, target_date: str, api_key: str) -> dict | None:
    """Generate one recommendation for property + date."""
    location = prop["location"]
    market = get_market_data(location, target_date)

    if not market:
        return None

    dt = datetime.strptime(target_date, "%Y-%m-%d")
    is_weekend = dt.weekday() >= 4
    today = datetime.now().date()
    days_ahead = (dt.date() - today).days

    pricing_input = PricingInput(
        base_price=prop["base_price"],
        min_price=prop["min_price"],
        max_price=prop["max_price"],
        occupancy_rate=market["occupancy_rate"],
        competitor_avg_price=market["avg_price"],
        event_flag=bool(market["event_flag"]),
        is_weekend=is_weekend,
        days_ahead=max(0, days_ahead),
    )

    result = calculate_price(pricing_input)

    explanation = generate_explanation(result, market, api_key=api_key)

    return {
        "property_id":      prop["id"],
        "date":             target_date,
        "base_price":       prop["base_price"],
        "suggested_price":  result.suggested_price,
        "confidence_score": result.confidence_score,
        "demand_factor":    result.demand_factor,
        "event_boost":      result.event_boost,
        "competitor_gap":   result.competitor_gap,
        "occupancy_adj":    result.occupancy_adj,
        "reason":           explanation,
    }


def run():
    init_db()
    start = time.time()
    api_key = os.environ.get("OPENAI_API_KEY", "")

    print(f"[{JOB_NAME}] Starting recommendation generation...")

    properties = get_all_properties()
    if not properties:
        msg = "No active properties found."
        print(f"[{JOB_NAME}] {msg}")
        log_cron(JOB_NAME, "success", msg, 0)
        return

    today = datetime.now()
    dates = [(today + timedelta(days=i)).strftime("%Y-%m-%d") for i in range(DAYS_AHEAD + 1)]

    total_ok = 0
    total_skip = 0
    total_err = 0

    for prop in properties:
        for date_str in dates:
            try:
                rec = process_property(prop, date_str, api_key)
                if rec:
                    upsert_recommendation(rec)
                    total_ok += 1
                    print(f"  ✓ {prop['name']} / {date_str} → RM{rec['suggested_price']:.0f}")
                else:
                    total_skip += 1
            except Exception as e:
                total_err += 1
                print(f"  ✗ {prop['name']} / {date_str}: {e}", file=sys.stderr)

    duration_ms = int((time.time() - start) * 1000)
    msg = (
        f"Generated {total_ok} recommendations | "
        f"Skipped {total_skip} (no market data) | "
        f"Errors {total_err}"
    )
    status = "error" if total_err > total_ok else "success"
    print(f"[{JOB_NAME}] Done — {msg}")
    log_cron(JOB_NAME, status, msg, duration_ms)


if __name__ == "__main__":
    run()
