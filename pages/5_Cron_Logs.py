"""
STRate AI — Cron Logs & System Status Page
"""
import streamlit as st
import pandas as pd
import subprocess
import sys
import os
from datetime import datetime

from strate_ai.database import init_db, get_cron_logs

init_db()

st.set_page_config(page_title="Cron Logs — STRate AI", page_icon="⚙️", layout="wide")
st.title("⚙️ System & Cron Jobs")
st.caption("Monitor automated jobs and system health.")
st.divider()

CRON_DIR = os.path.join(os.path.dirname(os.path.dirname(__file__)), "cron")

# ─── Manual job triggers ──────────────────────────────────────────────────────
st.subheader("▶️ Run Jobs Manually")

c1, c2, c3 = st.columns(3)

def run_script(script_name: str) -> tuple[str, bool]:
    path = os.path.join(CRON_DIR, script_name)
    result = subprocess.run(
        [sys.executable, path],
        capture_output=True, text=True, timeout=120
    )
    output = result.stdout + result.stderr
    return output, result.returncode == 0

with c1:
    st.markdown("**🔄 Fetch Market Data**")
    st.caption("Updates competitor prices and occupancy for all locations.")
    if st.button("Run fetch_market_data.py", use_container_width=True):
        with st.spinner("Running..."):
            output, ok = run_script("fetch_market_data.py")
        if ok:
            st.success("Completed!")
        else:
            st.error("Failed!")
        st.code(output)

with c2:
    st.markdown("**💰 Generate Recommendations**")
    st.caption("Runs pricing engine for all properties, next 7 days.")
    if st.button("Run generate_recommendations.py", use_container_width=True):
        with st.spinner("Running... (this may take a moment)"):
            output, ok = run_script("generate_recommendations.py")
        if ok:
            st.success("Completed!")
        else:
            st.error("Failed!")
        st.code(output)

with c3:
    st.markdown("**📋 Daily Summary**")
    st.caption("Generates the nightly summary report.")
    if st.button("Run daily_summary.py", use_container_width=True):
        with st.spinner("Running..."):
            output, ok = run_script("daily_summary.py")
        if ok:
            st.success("Completed!")
        else:
            st.error("Failed!")
        st.code(output)

st.divider()

# ─── Cron schedule reference ─────────────────────────────────────────────────
with st.expander("📅 Cron Schedule (Linux / Hostinger)"):
    st.code("""# STRate AI Cron Jobs
# Edit with: crontab -e

# Fetch market data every 4 hours
0 */4 * * * /usr/bin/python3 /var/www/strate_ai/cron/fetch_market_data.py >> /var/log/strate_fetch.log 2>&1

# Generate recommendations daily at 6AM
0 6 * * * /usr/bin/python3 /var/www/strate_ai/cron/generate_recommendations.py >> /var/log/strate_recs.log 2>&1

# Daily summary at 9PM
0 21 * * * /usr/bin/python3 /var/www/strate_ai/cron/daily_summary.py >> /var/log/strate_summary.log 2>&1
""", language="bash")

st.divider()

# ─── Log viewer ───────────────────────────────────────────────────────────────
st.subheader("📋 Job Logs")

col_refresh, col_filter = st.columns([1, 2])
with col_refresh:
    if st.button("🔄 Refresh Logs"):
        st.rerun()

with col_filter:
    filter_status = st.selectbox("Filter by status", ["all", "success", "error"])

logs = get_cron_logs(limit=100)

if not logs:
    st.info("No logs yet. Run a job above to see logs.")
else:
    df = pd.DataFrame(logs)
    if filter_status != "all":
        df = df[df["status"] == filter_status]

    df["created_at"] = pd.to_datetime(df["created_at"])
    df = df.sort_values("created_at", ascending=False)

    # Status styling
    def style_status(val):
        if val == "success":
            return "color: green; font-weight: bold"
        elif val == "error":
            return "color: red; font-weight: bold"
        return ""

    display_df = df[["created_at", "job_name", "status", "message", "duration_ms"]].copy()
    display_df.columns = ["Timestamp", "Job", "Status", "Message", "Duration (ms)"]
    display_df["Timestamp"] = display_df["Timestamp"].dt.strftime("%Y-%m-%d %H:%M:%S")

    st.dataframe(
        display_df.style.applymap(style_status, subset=["Status"]),
        use_container_width=True,
        hide_index=True,
    )

st.divider()

# ─── System info ─────────────────────────────────────────────────────────────
with st.expander("ℹ️ System Info"):
    from strate_ai.database import DB_PATH
    db_size = os.path.getsize(DB_PATH) / 1024 if os.path.exists(DB_PATH) else 0
    st.markdown(f"""
    | Key | Value |
    |-----|-------|
    | Python | `{sys.version.split()[0]}` |
    | DB Path | `{DB_PATH}` |
    | DB Size | `{db_size:.1f} KB` |
    | Cron Dir | `{CRON_DIR}` |
    | Timestamp | `{datetime.now().isoformat()}` |
    """)
