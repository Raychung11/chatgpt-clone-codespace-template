"""
STRate AI — Market Data Page
View competitor prices, occupancy trends, and demand signals.
"""
import streamlit as st
import pandas as pd
from datetime import datetime, timedelta

from strate_ai.database import get_market_data_range, upsert_market_data, init_db
from strate_ai.market_data import (
    fetch_and_store_location, get_available_locations,
    get_csv_template, ingest_from_csv
)
from strate_ai.pricing_engine import demand_label

init_db()

st.set_page_config(page_title="Market Data — STRate AI", page_icon="📊", layout="wide")
st.title("📊 Market Data")
st.caption("Competitor pricing, occupancy trends, and demand signals by location.")
st.divider()

# ─── Location selector ───────────────────────────────────────────────────────
locations = get_available_locations()
col_loc, col_refresh = st.columns([3, 1])

with col_loc:
    selected_location = st.selectbox("Select Location", locations, index=0)

with col_refresh:
    st.write("")
    if st.button("🔄 Refresh Data", use_container_width=True):
        with st.spinner(f"Fetching data for {selected_location}..."):
            count = fetch_and_store_location(selected_location, days_ahead=7, days_back=30)
        st.success(f"Updated {count} records for {selected_location}")
        st.rerun()

# ─── Load data ───────────────────────────────────────────────────────────────
data = get_market_data_range(selected_location, days=37)

if not data:
    st.warning(f"No market data for {selected_location}. Click **Refresh Data** to fetch.")
    st.stop()

df = pd.DataFrame(data)
df["date"] = pd.to_datetime(df["date"])
df = df.sort_values("date")
df["demand"] = df.apply(
    lambda r: demand_label(r["occupancy_rate"], bool(r["event_flag"])), axis=1
)
df["occupancy_pct"] = (df["occupancy_rate"] * 100).round(1)

# ─── KPI Row ─────────────────────────────────────────────────────────────────
today_str = datetime.now().strftime("%Y-%m-%d")
today_row = df[df["date"].dt.strftime("%Y-%m-%d") == today_str]

c1, c2, c3, c4 = st.columns(4)
if not today_row.empty:
    r = today_row.iloc[0]
    c1.metric("Today Avg Price", f"RM {r['avg_price']:.0f}")
    c2.metric("Occupancy Rate", f"{r['occupancy_rate']*100:.0f}%")
    c3.metric("Active Listings", int(r["listing_count"]))
    c4.metric("Demand Level", r["demand"])
else:
    c1.metric("Today Avg Price", "—")
    c2.metric("Occupancy Rate", "—")
    c3.metric("Active Listings", "—")
    c4.metric("Demand Level", "—")

st.divider()

# ─── Charts ──────────────────────────────────────────────────────────────────
tab_price, tab_occ, tab_table = st.tabs(["💰 Price Trend", "📈 Occupancy Trend", "📋 Raw Data"])

with tab_price:
    chart_df = df[["date", "avg_price", "min_price", "max_price"]].set_index("date")
    st.line_chart(chart_df, y=["avg_price", "min_price", "max_price"],
                  color=["#2563EB", "#10B981", "#EF4444"])
    st.caption("Blue = Avg · Green = Min · Red = Max competitor prices")

with tab_occ:
    occ_df = df[["date", "occupancy_pct"]].set_index("date")
    st.area_chart(occ_df, y="occupancy_pct", color="#7C3AED")
    st.caption("Area occupancy % (simulated from market signals)")

with tab_table:
    display_cols = ["date", "avg_price", "min_price", "max_price",
                    "listing_count", "occupancy_pct", "demand", "event_name"]
    display_df = df[display_cols].copy()
    display_df["date"] = display_df["date"].dt.strftime("%Y-%m-%d")
    display_df.columns = ["Date", "Avg Price (RM)", "Min (RM)", "Max (RM)",
                          "Listings", "Occupancy %", "Demand", "Event"]
    st.dataframe(display_df, use_container_width=True, hide_index=True)

st.divider()

# ─── CSV Import ──────────────────────────────────────────────────────────────
with st.expander("📥 Import Market Data via CSV"):
    st.markdown("Upload your own market data to override simulated values.")

    template = get_csv_template()
    st.download_button(
        "⬇️ Download CSV Template",
        data=template,
        file_name="market_data_template.csv",
        mime="text/csv",
    )

    uploaded = st.file_uploader("Upload CSV", type=["csv"])
    if uploaded:
        content = uploaded.read().decode("utf-8")
        ingested, errors = ingest_from_csv(content)
        if ingested:
            st.success(f"Imported {len(ingested)} records.")
        if errors:
            st.warning(f"{len(errors)} errors:\n" + "\n".join(errors))
        if ingested:
            st.rerun()
