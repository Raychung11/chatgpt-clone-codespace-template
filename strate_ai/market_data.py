"""
STRate AI — Market Data Module
Handles data ingestion from multiple sources:
  1. Simulated data (for demo/dev)
  2. CSV import
  3. API ingestion (Apify / BrightData stub)
"""
import random
import csv
import io
from datetime import datetime, timedelta
from typing import List, Dict, Optional

from .database import upsert_market_data, get_market_data

# Known events in Klang Valley (expandable)
MALAYSIA_EVENTS = {
    "2026-01-01": "New Year",
    "2026-01-28": "Chinese New Year",
    "2026-01-29": "Chinese New Year Holiday",
    "2026-02-01": "Federal Territory Day",
    "2026-03-02": "Israk dan Mikraj",
    "2026-03-28": "Good Friday (KL)",
    "2026-04-03": "Nuzul Al-Quran",
    "2026-04-10": "Hari Raya Aidilfitri",
    "2026-04-11": "Hari Raya Aidilfitri Holiday",
    "2026-05-01": "Labour Day",
    "2026-05-09": "Wesak Day",
    "2026-06-01": "Agong's Birthday",
    "2026-06-17": "Hari Raya Haji",
    "2026-07-07": "Awal Muharram",
    "2026-08-31": "National Day",
    "2026-09-16": "Malaysia Day",
    "2026-09-16": "Prophet's Birthday",
    "2026-10-31": "Deepavali",
    "2026-12-25": "Christmas",
}

LOCATION_BASE_PRICES = {
    "KLCC":          {"avg": 265, "min_mult": 0.72, "max_mult": 1.40, "listings": (55, 110)},
    "Bukit Bintang": {"avg": 195, "min_mult": 0.70, "max_mult": 1.35, "listings": (40, 90)},
    "Mont Kiara":    {"avg": 230, "min_mult": 0.75, "max_mult": 1.30, "listings": (30, 70)},
    "Sunway":        {"avg": 148, "min_mult": 0.68, "max_mult": 1.38, "listings": (35, 80)},
    "Cyberjaya":     {"avg": 112, "min_mult": 0.65, "max_mult": 1.25, "listings": (20, 55)},
    "Bangsar":       {"avg": 210, "min_mult": 0.72, "max_mult": 1.32, "listings": (25, 60)},
    "Cheras":        {"avg": 130, "min_mult": 0.65, "max_mult": 1.28, "listings": (20, 50)},
    "Petaling Jaya": {"avg": 155, "min_mult": 0.70, "max_mult": 1.30, "listings": (30, 65)},
}


def simulate_market_data(location: str, date_str: str) -> Dict:
    """
    Generate realistic simulated market data for a location/date.
    Used for demo and testing — not real scraping.
    """
    if location not in LOCATION_BASE_PRICES:
        raise ValueError(f"Unknown location: {location}. Known: {list(LOCATION_BASE_PRICES.keys())}")

    cfg = LOCATION_BASE_PRICES[location]
    dt = datetime.strptime(date_str, "%Y-%m-%d")
    is_weekend = dt.weekday() >= 4  # Fri/Sat/Sun
    is_holiday = date_str in MALAYSIA_EVENTS
    event_name = MALAYSIA_EVENTS.get(date_str)

    # Seed randomness on location+date for reproducibility
    seed = hash(f"{location}{date_str}") % (2**31)
    rng = random.Random(seed)

    # Base price with noise
    noise = rng.uniform(0.88, 1.12)
    base = cfg["avg"] * noise

    # Occupancy model
    occ = rng.uniform(0.50, 0.75)
    if is_weekend:
        occ += rng.uniform(0.05, 0.15)
    if is_holiday:
        occ += rng.uniform(0.08, 0.18)

    # Price responds to occupancy
    if occ > 0.80:
        base *= rng.uniform(1.10, 1.25)
    elif occ < 0.45:
        base *= rng.uniform(0.85, 0.95)

    occ = round(min(occ, 0.97), 3)

    return {
        "location": location,
        "date": date_str,
        "avg_price": round(base, 2),
        "min_price": round(base * cfg["min_mult"], 2),
        "max_price": round(base * cfg["max_mult"], 2),
        "listing_count": rng.randint(*cfg["listings"]),
        "occupancy_rate": occ,
        "event_flag": 1 if (is_holiday or event_name) else 0,
        "event_name": event_name,
        "source": "simulated",
    }


def fetch_and_store_location(location: str, days_ahead: int = 7, days_back: int = 30) -> int:
    """
    Generate + store market data for a location across a date range.
    Returns number of records stored.
    """
    today = datetime.now()
    count = 0
    for delta in range(-days_back, days_ahead + 1):
        d = (today + timedelta(days=delta)).strftime("%Y-%m-%d")
        data = simulate_market_data(location, d)
        upsert_market_data(data)
        count += 1
    return count


def fetch_all_locations(days_ahead: int = 7, days_back: int = 30) -> Dict[str, int]:
    """Refresh market data for all known locations."""
    results = {}
    for loc in LOCATION_BASE_PRICES:
        results[loc] = fetch_and_store_location(loc, days_ahead, days_back)
    return results


def ingest_from_csv(csv_content: str) -> List[Dict]:
    """
    Import market data from CSV string.
    Expected columns: location, date, avg_price, min_price, max_price,
                      listing_count, occupancy_rate, event_flag, event_name
    Returns list of ingested records.
    """
    reader = csv.DictReader(io.StringIO(csv_content))
    ingested = []
    errors = []

    for i, row in enumerate(reader, start=2):
        try:
            data = {
                "location":       row["location"].strip(),
                "date":           row["date"].strip(),
                "avg_price":      float(row["avg_price"]),
                "min_price":      float(row["min_price"]),
                "max_price":      float(row["max_price"]),
                "listing_count":  int(row["listing_count"]),
                "occupancy_rate": float(row["occupancy_rate"]),
                "event_flag":     int(row.get("event_flag", 0)),
                "event_name":     row.get("event_name", "").strip() or None,
                "source":         "csv_import",
            }
            # Validate date format
            datetime.strptime(data["date"], "%Y-%m-%d")
            upsert_market_data(data)
            ingested.append(data)
        except (KeyError, ValueError) as e:
            errors.append(f"Row {i}: {e}")

    return ingested, errors


def get_csv_template() -> str:
    """Return a CSV template string for market data import."""
    return (
        "location,date,avg_price,min_price,max_price,listing_count,"
        "occupancy_rate,event_flag,event_name\n"
        "KLCC,2026-04-10,320.00,230.00,450.00,85,0.92,1,Hari Raya Aidilfitri\n"
        "Sunway,2026-04-10,185.00,140.00,260.00,62,0.88,1,Hari Raya Aidilfitri\n"
    )


def get_available_locations() -> List[str]:
    return sorted(LOCATION_BASE_PRICES.keys())
