#!/usr/bin/env python3
"""
STRate AI — Cron Job: Daily Summary
Run nightly at 9PM: 0 21 * * * python3 /path/cron/daily_summary.py

Builds a daily summary of recommendations and prints (or sends via WhatsApp).
"""
import sys
import os
from datetime import datetime, timedelta

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from strate_ai.database import init_db, get_all_properties, get_latest_recommendation, log_cron
from strate_ai.ai_explainer import generate_whatsapp_reply


def build_summary(properties: list, target_date: str) -> str:
    lines = [
        f"━━━━━━━━━━━━━━━━━━━━━━━━━━",
        f"  STRate AI — Daily Summary",
        f"  {target_date}",
        f"━━━━━━━━━━━━━━━━━━━━━━━━━━",
        "",
    ]

    for prop in properties:
        today_rec = get_latest_recommendation(prop["id"], target_date)

        if today_rec:
            base = today_rec["base_price"]
            suggested = today_rec["suggested_price"]
            change_pct = ((suggested - base) / base * 100) if base > 0 else 0
            direction = "▲" if change_pct >= 0 else "▼"
            lines.append(f"🏠 {prop['name']} ({prop['location']})")
            lines.append(f"   Price: RM{suggested:.0f} {direction} ({change_pct:+.1f}%)")
            lines.append(f"   Confidence: {today_rec['confidence_score']*100:.0f}%")
            lines.append(f"   {today_rec['reason']}")
        else:
            lines.append(f"🏠 {prop['name']} — No recommendation available")

        lines.append("")

    lines.append("━━━━━━━━━━━━━━━━━━━━━━━━━━")
    return "\n".join(lines)


def run():
    init_db()
    target_date = datetime.now().strftime("%Y-%m-%d")
    properties = get_all_properties()

    if not properties:
        log_cron("daily_summary", "success", "No properties.", 0)
        return

    summary = build_summary(properties, target_date)
    print(summary)

    # In production: send via WhatsApp AiServe API here
    # requests.post(AISERVER_URL, json={"message": summary, "to": owner_phone})

    log_cron("daily_summary", "success", f"Sent summary for {len(properties)} properties.", 0)


if __name__ == "__main__":
    run()
