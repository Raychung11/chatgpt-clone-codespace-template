#!/usr/bin/env python3
"""
STRate AI — Cron Job: Fetch Market Data
Run every 4 hours via cron: 0 */4 * * * python3 /path/cron/fetch_market_data.py

Fetches (simulates) market data for all locations and stores to DB.
"""
import sys
import time
import os

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from strate_ai.database import init_db, log_cron
from strate_ai.market_data import fetch_all_locations

JOB_NAME = "fetch_market_data"


def run():
    init_db()
    start = time.time()
    print(f"[{JOB_NAME}] Starting market data fetch...")

    try:
        results = fetch_all_locations(days_ahead=7, days_back=3)
        total = sum(results.values())
        msg = f"Fetched {total} records across {len(results)} locations: {results}"
        print(f"[{JOB_NAME}] {msg}")
        duration_ms = int((time.time() - start) * 1000)
        log_cron(JOB_NAME, "success", msg, duration_ms)
    except Exception as e:
        duration_ms = int((time.time() - start) * 1000)
        msg = f"Error: {e}"
        print(f"[{JOB_NAME}] FAILED — {msg}", file=sys.stderr)
        log_cron(JOB_NAME, "error", msg, duration_ms)
        sys.exit(1)


if __name__ == "__main__":
    run()
