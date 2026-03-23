"""
STRate AI — Recommendations Page
View and generate price recommendations per property.
"""
import streamlit as st
import pandas as pd
import os
from datetime import datetime, timedelta

from strate_ai.database import (
    init_db, get_all_properties, get_recommendations,
    get_market_data, upsert_recommendation
)
from strate_ai.pricing_engine import PricingInput, calculate_price, demand_label
from strate_ai.ai_explainer import generate_explanation

init_db()

st.set_page_config(page_title="Recommendations — STRate AI", page_icon="💰", layout="wide")
st.title("💰 Price Recommendations")
st.caption("AI-assisted daily price recommendations per property.")
st.divider()

properties = get_all_properties()
if not properties:
    st.info("No properties found. Add one in the **Properties** page.")
    st.stop()

prop_names = {p["id"]: f"{p['name']} ({p['location']})" for p in properties}
selected_id = st.selectbox(
    "Select Property",
    options=[p["id"] for p in properties],
    format_func=lambda x: prop_names[x],
)

prop = next(p for p in properties if p["id"] == selected_id)

# ─── Generate button ─────────────────────────────────────────────────────────
col_gen, col_days = st.columns([2, 1])
with col_days:
    days_ahead = st.slider("Days to generate", 1, 14, 7)

with col_gen:
    if st.button("⚡ Generate Recommendations Now", use_container_width=True, type="primary"):
        api_key = st.secrets.get("OPENAI_API_KEY", os.environ.get("OPENAI_API_KEY", ""))
        count = 0
        errors = 0
        progress = st.progress(0, text="Generating...")
        total_dates = days_ahead + 1

        for i, delta in enumerate(range(total_dates)):
            d = (datetime.now() + timedelta(days=delta)).strftime("%Y-%m-%d")
            market = get_market_data(prop["location"], d)
            if not market:
                errors += 1
                progress.progress((i + 1) / total_dates, text=f"{d} — no market data")
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
            reason = generate_explanation(result, market, api_key=api_key)
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
                "reason":           reason,
            })
            count += 1
            progress.progress((i + 1) / total_dates, text=f"✓ {d} → RM{result.suggested_price:.0f}")

        progress.empty()
        st.success(f"Generated {count} recommendations! ({errors} skipped — no market data)")
        st.rerun()

st.divider()

# ─── Load existing recommendations ───────────────────────────────────────────
recs = get_recommendations(selected_id, days=days_ahead + 1)

if not recs:
    st.info("No recommendations yet. Click **Generate Recommendations Now** above.")
    st.stop()

df = pd.DataFrame(recs)
df["date"] = pd.to_datetime(df["date"])
df = df.sort_values("date")
df["change_pct"] = ((df["suggested_price"] - df["base_price"]) / df["base_price"] * 100).round(1)
df["confidence_pct"] = (df["confidence_score"] * 100).round(0)

# ─── Summary chart ────────────────────────────────────────────────────────────
st.subheader(f"📈 Price Forecast — {prop['name']}")

chart_df = df[["date", "suggested_price", "base_price"]].set_index("date")
st.line_chart(chart_df, color=["#2563EB", "#94A3B8"])
st.caption("Blue = Recommended · Grey = Base price")

st.divider()

# ─── Recommendation cards ─────────────────────────────────────────────────────
st.subheader("Detailed Breakdown")

for _, row in df.iterrows():
    date_label = row["date"].strftime("%A, %d %b %Y")
    is_today = row["date"].date() == datetime.now().date()
    header = f"{'📍 TODAY — ' if is_today else ''}{date_label}"

    with st.expander(header, expanded=is_today):
        c1, c2, c3, c4 = st.columns(4)
        c1.metric("Suggested", f"RM {row['suggested_price']:.0f}",
                  f"{row['change_pct']:+.1f}%")
        c2.metric("Base Price", f"RM {row['base_price']:.0f}")
        c3.metric("Confidence", f"{row['confidence_pct']:.0f}%")

        market = get_market_data(prop["location"], row["date"].strftime("%Y-%m-%d"))
        if market:
            c4.metric("Area Occupancy", f"{market['occupancy_rate']*100:.0f}%")

        # Breakdown bars
        st.markdown("**Price Factor Breakdown:**")
        factors = {
            "Demand Factor":      row["demand_factor"] * 100,
            "Event Boost":        row["event_boost"] * 100,
            "Competitor Gap":     row["competitor_gap"] * 100,
            "Occupancy Adj":      row["occupancy_adj"] * 100,
        }
        factor_df = pd.DataFrame(
            {"Factor": list(factors.keys()), "Impact (%)": list(factors.values())}
        ).set_index("Factor")
        st.bar_chart(factor_df, color="#6366F1", horizontal=True)

        if row.get("reason"):
            st.info(f"💬 {row['reason']}")
