"""
STRate AI — Database Layer
SQLite schema setup and query helpers.
"""
import sqlite3
import os
from datetime import datetime

DB_PATH = os.path.join(os.path.dirname(os.path.dirname(__file__)), "strate_ai.db")


def get_connection():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA journal_mode=WAL")
    conn.execute("PRAGMA foreign_keys=ON")
    return conn


def init_db():
    """Create all tables if they don't exist."""
    conn = get_connection()
    cur = conn.cursor()

    cur.executescript("""
        CREATE TABLE IF NOT EXISTS users (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            name        TEXT NOT NULL,
            email       TEXT UNIQUE NOT NULL,
            phone       TEXT,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS properties (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id     INTEGER NOT NULL,
            name        TEXT NOT NULL,
            location    TEXT NOT NULL,
            base_price  REAL NOT NULL,
            min_price   REAL NOT NULL,
            max_price   REAL NOT NULL,
            room_type   TEXT DEFAULT 'entire_unit',
            bedrooms    INTEGER DEFAULT 1,
            active      INTEGER DEFAULT 1,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS market_data (
            id               INTEGER PRIMARY KEY AUTOINCREMENT,
            location         TEXT NOT NULL,
            date             TEXT NOT NULL,
            avg_price        REAL,
            min_price        REAL,
            max_price        REAL,
            listing_count    INTEGER,
            occupancy_rate   REAL,
            event_flag       INTEGER DEFAULT 0,
            event_name       TEXT,
            source           TEXT DEFAULT 'simulated',
            created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(location, date)
        );

        CREATE TABLE IF NOT EXISTS price_recommendations (
            id               INTEGER PRIMARY KEY AUTOINCREMENT,
            property_id      INTEGER NOT NULL,
            date             TEXT NOT NULL,
            base_price       REAL NOT NULL,
            suggested_price  REAL NOT NULL,
            confidence_score REAL NOT NULL,
            demand_factor    REAL,
            event_boost      REAL,
            competitor_gap   REAL,
            occupancy_adj    REAL,
            reason           TEXT,
            status           TEXT DEFAULT 'pending',
            created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(property_id, date),
            FOREIGN KEY (property_id) REFERENCES properties(id)
        );

        CREATE TABLE IF NOT EXISTS cron_logs (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            job_name    TEXT NOT NULL,
            status      TEXT NOT NULL,
            message     TEXT,
            duration_ms INTEGER,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS job_queue (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            job_type    TEXT NOT NULL,
            payload     TEXT,
            status      TEXT DEFAULT 'pending',
            retry_count INTEGER DEFAULT 0,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    """)

    conn.commit()
    conn.close()


# ─── Properties ──────────────────────────────────────────────────────────────

def get_all_properties():
    conn = get_connection()
    rows = conn.execute(
        "SELECT p.*, u.name AS owner_name FROM properties p "
        "JOIN users u ON p.user_id = u.id WHERE p.active = 1"
    ).fetchall()
    conn.close()
    return [dict(r) for r in rows]


def get_property(property_id: int):
    conn = get_connection()
    row = conn.execute("SELECT * FROM properties WHERE id = ?", (property_id,)).fetchone()
    conn.close()
    return dict(row) if row else None


def upsert_property(data: dict) -> int:
    conn = get_connection()
    cur = conn.cursor()
    if data.get("id"):
        cur.execute(
            "UPDATE properties SET name=?, location=?, base_price=?, min_price=?, "
            "max_price=?, room_type=?, bedrooms=? WHERE id=?",
            (data["name"], data["location"], data["base_price"], data["min_price"],
             data["max_price"], data["room_type"], data["bedrooms"], data["id"])
        )
        pid = data["id"]
    else:
        cur.execute(
            "INSERT INTO properties (user_id, name, location, base_price, min_price, "
            "max_price, room_type, bedrooms) VALUES (?,?,?,?,?,?,?,?)",
            (data["user_id"], data["name"], data["location"], data["base_price"],
             data["min_price"], data["max_price"], data["room_type"], data["bedrooms"])
        )
        pid = cur.lastrowid
    conn.commit()
    conn.close()
    return pid


# ─── Market Data ─────────────────────────────────────────────────────────────

def upsert_market_data(data: dict):
    conn = get_connection()
    conn.execute("""
        INSERT INTO market_data
            (location, date, avg_price, min_price, max_price,
             listing_count, occupancy_rate, event_flag, event_name, source)
        VALUES (?,?,?,?,?,?,?,?,?,?)
        ON CONFLICT(location, date) DO UPDATE SET
            avg_price      = excluded.avg_price,
            min_price      = excluded.min_price,
            max_price      = excluded.max_price,
            listing_count  = excluded.listing_count,
            occupancy_rate = excluded.occupancy_rate,
            event_flag     = excluded.event_flag,
            event_name     = excluded.event_name,
            source         = excluded.source
    """, (
        data["location"], data["date"], data["avg_price"], data["min_price"],
        data["max_price"], data["listing_count"], data["occupancy_rate"],
        data.get("event_flag", 0), data.get("event_name"), data.get("source", "simulated")
    ))
    conn.commit()
    conn.close()


def get_market_data(location: str, date: str):
    conn = get_connection()
    row = conn.execute(
        "SELECT * FROM market_data WHERE location=? AND date=?", (location, date)
    ).fetchone()
    conn.close()
    return dict(row) if row else None


def get_market_data_range(location: str, days: int = 30):
    conn = get_connection()
    rows = conn.execute(
        "SELECT * FROM market_data WHERE location=? ORDER BY date DESC LIMIT ?",
        (location, days)
    ).fetchall()
    conn.close()
    return [dict(r) for r in rows]


# ─── Recommendations ─────────────────────────────────────────────────────────

def upsert_recommendation(data: dict):
    conn = get_connection()
    conn.execute("""
        INSERT INTO price_recommendations
            (property_id, date, base_price, suggested_price, confidence_score,
             demand_factor, event_boost, competitor_gap, occupancy_adj, reason)
        VALUES (?,?,?,?,?,?,?,?,?,?)
        ON CONFLICT(property_id, date) DO UPDATE SET
            suggested_price  = excluded.suggested_price,
            confidence_score = excluded.confidence_score,
            demand_factor    = excluded.demand_factor,
            event_boost      = excluded.event_boost,
            competitor_gap   = excluded.competitor_gap,
            occupancy_adj    = excluded.occupancy_adj,
            reason           = excluded.reason
    """, (
        data["property_id"], data["date"], data["base_price"],
        data["suggested_price"], data["confidence_score"],
        data.get("demand_factor", 0), data.get("event_boost", 0),
        data.get("competitor_gap", 0), data.get("occupancy_adj", 0),
        data.get("reason", "")
    ))
    conn.commit()
    conn.close()


def get_recommendations(property_id: int, days: int = 7):
    conn = get_connection()
    rows = conn.execute(
        "SELECT * FROM price_recommendations WHERE property_id=? "
        "ORDER BY date DESC LIMIT ?",
        (property_id, days)
    ).fetchall()
    conn.close()
    return [dict(r) for r in rows]


def get_latest_recommendation(property_id: int, date: str = None):
    if date is None:
        date = datetime.now().strftime("%Y-%m-%d")
    conn = get_connection()
    row = conn.execute(
        "SELECT * FROM price_recommendations WHERE property_id=? AND date=?",
        (property_id, date)
    ).fetchone()
    conn.close()
    return dict(row) if row else None


# ─── Cron Logs ───────────────────────────────────────────────────────────────

def log_cron(job_name: str, status: str, message: str = "", duration_ms: int = 0):
    conn = get_connection()
    conn.execute(
        "INSERT INTO cron_logs (job_name, status, message, duration_ms) VALUES (?,?,?,?)",
        (job_name, status, message, duration_ms)
    )
    conn.commit()
    conn.close()


def get_cron_logs(limit: int = 50):
    conn = get_connection()
    rows = conn.execute(
        "SELECT * FROM cron_logs ORDER BY created_at DESC LIMIT ?", (limit,)
    ).fetchall()
    conn.close()
    return [dict(r) for r in rows]


# ─── Users ───────────────────────────────────────────────────────────────────

def get_or_create_demo_user() -> int:
    conn = get_connection()
    row = conn.execute("SELECT id FROM users WHERE email='demo@strate.ai'").fetchone()
    if row:
        conn.close()
        return row["id"]
    cur = conn.cursor()
    cur.execute(
        "INSERT INTO users (name, email, phone) VALUES (?,?,?)",
        ("Demo Owner", "demo@strate.ai", "+60123456789")
    )
    uid = cur.lastrowid
    conn.commit()
    conn.close()
    return uid


def seed_demo_data():
    """Seed properties + market data for demo purposes."""
    init_db()
    uid = get_or_create_demo_user()

    locations = ["KLCC", "Sunway", "Bukit Bintang", "Mont Kiara", "Cyberjaya"]
    demo_properties = [
        {"name": "KLCC Sky Suite", "location": "KLCC", "base_price": 280, "min_price": 220, "max_price": 450, "room_type": "entire_unit", "bedrooms": 2},
        {"name": "Sunway Cozy Room", "location": "Sunway", "base_price": 150, "min_price": 120, "max_price": 250, "room_type": "private_room", "bedrooms": 1},
        {"name": "BB City Studio", "location": "Bukit Bintang", "base_price": 200, "min_price": 160, "max_price": 320, "room_type": "entire_unit", "bedrooms": 1},
    ]

    conn = get_connection()
    existing = conn.execute("SELECT COUNT(*) FROM properties").fetchone()[0]
    conn.close()

    if existing == 0:
        for p in demo_properties:
            p["user_id"] = uid
            upsert_property(p)

    # Seed 30 days of market data
    import random
    from datetime import timedelta
    base_date = datetime.now()
    for loc in locations:
        for i in range(-29, 8):
            d = (base_date + timedelta(days=i)).strftime("%Y-%m-%d")
            is_weekend = (base_date + timedelta(days=i)).weekday() >= 4
            event = random.random() < 0.1
            occ = round(random.uniform(0.55, 0.95) + (0.1 if is_weekend else 0) + (0.05 if event else 0), 2)
            occ = min(occ, 0.98)
            base = {"KLCC": 260, "Sunway": 145, "Bukit Bintang": 190, "Mont Kiara": 230, "Cyberjaya": 110}[loc]
            noise = random.uniform(0.85, 1.15)
            upsert_market_data({
                "location": loc,
                "date": d,
                "avg_price": round(base * noise, 2),
                "min_price": round(base * noise * 0.75, 2),
                "max_price": round(base * noise * 1.35, 2),
                "listing_count": random.randint(40, 120),
                "occupancy_rate": occ,
                "event_flag": 1 if event else 0,
                "event_name": "Local Event" if event else None,
                "source": "simulated",
            })
