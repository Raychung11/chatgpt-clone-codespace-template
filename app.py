"""
STRate AI — Main Entry Point
Revenue Optimization Platform for STR Owners in Malaysia.
"""
import streamlit as st
from strate_ai.database import init_db, seed_demo_data

# ─── Page Config ─────────────────────────────────────────────────────────────
st.set_page_config(
    page_title="STRate AI",
    page_icon="🏠",
    layout="wide",
    initial_sidebar_state="expanded",
)

# ─── Bootstrap ───────────────────────────────────────────────────────────────
@st.cache_resource
def bootstrap():
    """Initialize DB and seed demo data once at startup."""
    init_db()
    seed_demo_data()

bootstrap()

# ─── Sidebar ─────────────────────────────────────────────────────────────────
with st.sidebar:
    st.image("https://img.icons8.com/fluency/96/home.png", width=60)
    st.title("STRate AI")
    st.caption("Revenue Decision Engine")
    st.divider()
    st.markdown("""
    **Navigation**
    - 🏠 Dashboard ← *you are here*
    - 📊 Market Data
    - 💰 Recommendations
    - 🏢 Properties
    - ⚙️ Settings & Cron
    """)
    st.divider()
    st.caption("v0.1 MVP · Klang Valley")

# ─── Home Page ───────────────────────────────────────────────────────────────
from datetime import datetime
from strate_ai.database import get_all_properties, get_latest_recommendation, get_market_data
from strate_ai.pricing_engine import demand_label

st.title("🏠 STRate AI Dashboard")
st.caption(f"Revenue Decision Engine — {datetime.now().strftime('%A, %d %B %Y')}")
st.divider()

properties = get_all_properties()
today = datetime.now().strftime("%Y-%m-%d")

if not properties:
    st.info("No properties yet. Go to **Properties** to add your first listing.")
    st.stop()

# ─── KPI Row ─────────────────────────────────────────────────────────────────
total_props = len(properties)
recs_today = [get_latest_recommendation(p["id"], today) for p in properties]
recs_today = [r for r in recs_today if r]

avg_suggested = (
    sum(r["suggested_price"] for r in recs_today) / len(recs_today)
    if recs_today else 0
)
avg_confidence = (
    sum(r["confidence_score"] for r in recs_today) / len(recs_today)
    if recs_today else 0
)
avg_base = (
    sum(r["base_price"] for r in recs_today) / len(recs_today)
    if recs_today else 0
)
revenue_signal = ((avg_suggested - avg_base) / avg_base * 100) if avg_base > 0 else 0

col1, col2, col3, col4 = st.columns(4)
col1.metric("Active Properties", total_props)
col2.metric("Avg Price Today", f"RM {avg_suggested:.0f}" if avg_suggested else "—",
            f"{revenue_signal:+.1f}%" if avg_suggested else None)
col3.metric("Recommendations Ready", len(recs_today), f"of {total_props}")
col4.metric("Avg Confidence", f"{avg_confidence*100:.0f}%" if avg_confidence else "—")

st.divider()

# ─── Property Cards ───────────────────────────────────────────────────────────
st.subheader("Today's Recommendations")

for i, prop in enumerate(properties):
    rec = get_latest_recommendation(prop["id"], today)
    market = get_market_data(prop["location"], today)

    with st.container(border=True):
        col_info, col_price, col_signal = st.columns([3, 2, 2])

        with col_info:
            st.markdown(f"### 🏠 {prop['name']}")
            st.caption(f"📍 {prop['location']} · {prop['room_type'].replace('_', ' ').title()} · {prop['bedrooms']} BR")
            if market:
                demand = demand_label(market["occupancy_rate"], bool(market["event_flag"]))
                st.markdown(f"**Demand:** {demand}")
                if market["event_flag"] and market["event_name"]:
                    st.markdown(f"🎉 **Event:** {market['event_name']}")

        with col_price:
            if rec:
                base = rec["base_price"]
                suggested = rec["suggested_price"]
                change_pct = ((suggested - base) / base * 100) if base > 0 else 0
                direction = "▲" if change_pct >= 0 else "▼"
                color = "green" if change_pct >= 0 else "red"
                st.metric(
                    "Suggested Price",
                    f"RM {suggested:.0f}",
                    f"{direction} {abs(change_pct):.1f}% from base RM{base:.0f}",
                    delta_color="normal" if change_pct >= 0 else "inverse"
                )
            else:
                st.info("No recommendation yet.\nRun the cron job or go to Recommendations.")

        with col_signal:
            if rec:
                conf = rec["confidence_score"]
                conf_color = "🟢" if conf >= 0.75 else "🟡" if conf >= 0.55 else "🔴"
                st.metric("Confidence", f"{conf*100:.0f}%", label_visibility="visible")
                st.markdown(f"{conf_color} {'High' if conf >= 0.75 else 'Medium' if conf >= 0.55 else 'Low'} confidence")
                if market:
                    st.caption(f"Area occupancy: {market['occupancy_rate']*100:.0f}%")

        if rec and rec.get("reason"):
            st.info(f"💬 **AI Insight:** {rec['reason']}")

st.divider()

# ─── Quick Actions ────────────────────────────────────────────────────────────
st.subheader("Quick Actions")
qcol1, qcol2, qcol3 = st.columns(3)

with qcol1:
    if st.button("🔄 Refresh Market Data", use_container_width=True):
        with st.spinner("Fetching market data..."):
            from strate_ai.market_data import fetch_all_locations
            from strate_ai.database import log_cron
            results = fetch_all_locations(days_ahead=7, days_back=3)
            total = sum(results.values())
            log_cron("fetch_market_data", "success", f"Manual: {total} records", 0)
            st.cache_resource.clear()
        st.success(f"Updated {total} market data records!")
        st.rerun()

with qcol2:
    if st.button("💰 Generate Recommendations", use_container_width=True):
        with st.spinner("Running pricing engine..."):
            from strate_ai.market_data import fetch_all_locations
            from strate_ai.pricing_engine import PricingInput, calculate_price
            from strate_ai.ai_explainer import generate_explanation
            from strate_ai.database import upsert_recommendation
            from datetime import timedelta
            import os

            api_key = st.secrets.get("OPENAI_API_KEY", os.environ.get("OPENAI_API_KEY", ""))
            count = 0
            for prop in properties:
                for delta in range(8):
                    d = (datetime.now() + timedelta(days=delta)).strftime("%Y-%m-%d")
                    market = get_market_data(prop["location"], d)
                    if not market:
                        continue
                    dt = datetime.strptime(d, "%Y-%m-%d")
                    inp = PricingInput(
                        base_price=prop["base_price"],
                        min_price=prop["min_price"],
                        max_price=prop["max_price"],
                        occupancy_rate=market["occupancy_rate"],
                        competitor_avg_price=market["avg_price"],
                        event_flag=bool(market["event_flag"]),
                        is_weekend=dt.weekday() >= 4,
                        days_ahead=delta,
                    )
                    result = calculate_price(inp)
                    explanation = generate_explanation(result, market, api_key=api_key)
                    upsert_recommendation({
                        "property_id":      prop["id"],
                        "date":             d,
                        "base_price":       prop["base_price"],
                        "suggested_price":  result.suggested_price,
                        "confidence_score": result.confidence_score,
                        "demand_factor":    result.demand_factor,
                        "event_boost":      result.event_boost,
                        "competitor_gap":   result.competitor_gap,
                        "occupancy_adj":    result.occupancy_adj,
                        "reason":           explanation,
                    })
                    count += 1
        st.success(f"Generated {count} recommendations!")
        st.rerun()

with qcol3:
    if st.button("📋 View System Logs", use_container_width=True):
        st.switch_page("pages/5_Cron_Logs.py")
