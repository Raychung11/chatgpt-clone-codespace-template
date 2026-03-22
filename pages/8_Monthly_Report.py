"""Koponix — Koperasi Monthly Activity Report."""
import calendar
from collections import Counter
from datetime import date, datetime

import anthropic
import streamlit as st

from utils import (
    CATEGORY_COLORS,
    CATEGORY_ICONS,
    KOPONIX_SYSTEM_PROMPT,
    apply_koponix_style,
    load_requests,
    load_sellers,
    sidebar_logo,
)

st.set_page_config(
    page_title="Monthly Report — Koponix",
    page_icon="📊",
    layout="wide",
    initial_sidebar_state="expanded",
)
apply_koponix_style()

# ── Sidebar ───────────────────────────────────────────────────────────────────
with st.sidebar:
    sidebar_logo()
    st.page_link("app.py",                          label="🏠  Home")
    st.page_link("pages/1_AI_Assistant.py",         label="💬  AI Assistant")
    st.page_link("pages/2_Find_Services.py",        label="🔍  Find Services")
    st.page_link("pages/3_Register_Service.py",     label="💼  Register Service")
    st.page_link("pages/4_Request_Service.py",      label="🛒  Request a Service")
    st.page_link("pages/5_Admin_Dashboard.py",      label="🛡️  Admin Dashboard")
    st.page_link("pages/6_Match_Engine.py",         label="🎯  Match Engine")
    st.page_link("pages/7_Promo_Generator.py",      label="📣  Promo Generator")
    st.page_link("pages/8_Monthly_Report.py",       label="📊  Monthly Report")
    st.divider()

    st.markdown("**Report Period**")
    today = date.today()
    report_year  = st.selectbox("Year",  list(range(today.year, today.year - 3, -1)), index=0)
    report_month = st.selectbox(
        "Month",
        list(range(1, 13)),
        index=today.month - 1,
        format_func=lambda m: calendar.month_name[m],
    )
    st.divider()
    generate_btn = st.button("📊 Generate Report", type="primary", use_container_width=True)
    if "report_cache" in st.session_state:
        st.button("🗑️ Clear Report", on_click=lambda: st.session_state.pop("report_cache", None),
                  use_container_width=True)

# ── Page header ───────────────────────────────────────────────────────────────
st.markdown('<div class="section-head">📊 Monthly Activity Report</div>', unsafe_allow_html=True)
period_label = f"{calendar.month_name[report_month]} {report_year}"
st.caption(f"Koperasi Digital Economy Activation · Reporting Period: {period_label}")

# ── Helpers ────────────────────────────────────────────────────────────────────
def in_period(date_str: str, year: int, month: int) -> bool:
    try:
        d = datetime.strptime(date_str[:10], "%Y-%m-%d").date()
        return d.year == year and d.month == month
    except Exception:
        return False


def bar_html(label: str, value: int, max_val: int, color: str, icon: str = "") -> str:
    pct = (value / max_val * 100) if max_val else 0
    return f"""
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px;">
        <span style="width:18px;text-align:center;">{icon}</span>
        <span style="width:170px;font-size:0.8rem;color:#333;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{label}</span>
        <div style="flex:1;background:#eee;border-radius:4px;height:14px;overflow:hidden;">
            <div style="width:{pct:.0f}%;background:{color};height:100%;border-radius:4px;"></div>
        </div>
        <span style="font-size:0.78rem;color:#555;min-width:20px;text-align:right;">{value}</span>
    </div>"""


def kpi_box(num, label: str, color: str, delta: str = "") -> str:
    delta_html = f'<div style="font-size:0.68rem;color:{"#27ae60" if "+" in str(delta) else "#e74c3c"};margin-top:1px;">{delta}</div>' if delta else ""
    return f"""
    <div style="background:{color}18;border:1px solid {color}44;border-radius:10px;
                text-align:center;padding:0.9rem 0.4rem;">
        <div style="font-size:1.9rem;font-weight:800;color:{color};">{num}</div>
        <div style="font-size:0.72rem;color:#555;">{label}</div>
        {delta_html}
    </div>"""


@st.cache_resource
def get_client():
    return anthropic.Anthropic(api_key=st.secrets["ANTHROPIC_API_KEY"])


def build_html_report(data: dict, ai_summary: str, ai_recommendations: str) -> str:
    """Return a print-ready HTML report string."""
    cat_rows = "".join(
        f"<tr><td>{CATEGORY_ICONS.get(c,'⭐')} {c}</td><td>{data['cat_sellers'][c]}</td><td>{data['cat_requests'].get(c,0)}</td></tr>"
        for c in sorted(data["cat_sellers"], key=lambda x: -data["cat_sellers"][x])
    )
    new_seller_rows = "".join(
        f"<tr><td>{s['name']}</td><td>{s['service_title']}</td><td>{s['area']}</td><td>{s['category']}</td></tr>"
        for s in data["new_sellers"]
    )
    new_request_rows = "".join(
        f"<tr><td>{r['buyer_name']}</td><td>{r['category']}</td><td>{r['location']}</td><td>{r['budget']}</td><td>{r.get('status','open').title()}</td></tr>"
        for r in data["new_requests"]
    )
    return f"""<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Koponix Monthly Report — {data['period']}</title>
<style>
  body {{ font-family: 'Segoe UI', Arial, sans-serif; color: #2c3e50; margin: 0; padding: 32px; font-size: 13px; }}
  h1   {{ color: #1a5276; border-bottom: 3px solid #1a5276; padding-bottom: 8px; margin-bottom: 4px; }}
  h2   {{ color: #1a5276; margin-top: 28px; margin-bottom: 8px; font-size: 15px; border-left: 4px solid #1a5276; padding-left: 10px; }}
  h3   {{ color: #2e86c1; margin: 16px 0 6px; font-size: 13px; }}
  .subtitle {{ color: #666; font-size: 12px; margin-bottom: 24px; }}
  .kpi-grid {{ display: grid; grid-template-columns: repeat(5,1fr); gap: 12px; margin: 16px 0; }}
  .kpi {{ background: #eaf4fb; border-radius: 8px; text-align: center; padding: 12px 6px; }}
  .kpi .num {{ font-size: 1.8rem; font-weight: 800; color: #1a5276; }}
  .kpi .lbl {{ font-size: 0.7rem; color: #555; }}
  table {{ width: 100%; border-collapse: collapse; margin: 10px 0; }}
  th    {{ background: #1a5276; color: white; padding: 7px 10px; text-align: left; font-size: 12px; }}
  td    {{ padding: 6px 10px; border-bottom: 1px solid #eee; font-size: 12px; }}
  tr:nth-child(even) td {{ background: #f8f9fa; }}
  .narrative {{ background: #f0f4f8; border-left: 4px solid #2e86c1; padding: 12px 16px; border-radius: 0 8px 8px 0; margin: 10px 0; line-height: 1.6; }}
  .footer {{ margin-top: 40px; border-top: 1px solid #dde; padding-top: 10px; font-size: 11px; color: #999; text-align: center; }}
  @media print {{ body {{ padding: 16px; }} }}
</style>
</head>
<body>
<h1>🤝 Koponix Monthly Activity Report</h1>
<div class="subtitle">
  Koperasi Digital Economy Activation System &nbsp;·&nbsp; Period: <strong>{data['period']}</strong>
  &nbsp;·&nbsp; Generated: {date.today()}
</div>

<h2>1. Executive Summary</h2>
<div class="narrative">{ai_summary.replace(chr(10), '<br>')}</div>

<h2>2. Platform Overview</h2>
<div class="kpi-grid">
  <div class="kpi"><div class="num">{data['total_sellers']}</div><div class="lbl">Total Sellers</div></div>
  <div class="kpi"><div class="num">{data['active_sellers']}</div><div class="lbl">Active Listings</div></div>
  <div class="kpi"><div class="num">{data['new_seller_count']}</div><div class="lbl">New This Month</div></div>
  <div class="kpi"><div class="num">{data['total_requests']}</div><div class="lbl">Total Requests</div></div>
  <div class="kpi"><div class="num">{data['new_request_count']}</div><div class="lbl">New This Month</div></div>
</div>

<h2>3. Request Status Breakdown</h2>
<table>
  <tr><th>Status</th><th>Count</th><th>%</th></tr>
  {''.join(f"<tr><td>{s.title()}</td><td>{c}</td><td>{c/data['total_requests']*100:.0f}%</td></tr>" for s,c in data['req_status'].items()) if data['total_requests'] else '<tr><td colspan=3>No requests yet</td></tr>'}
</table>

<h2>4. Category Breakdown</h2>
<table>
  <tr><th>Category</th><th>Active Sellers</th><th>Requests</th></tr>
  {cat_rows if cat_rows else '<tr><td colspan=3>No data</td></tr>'}
</table>

<h2>5. New Seller Registrations This Month ({data['new_seller_count']})</h2>
<table>
  <tr><th>Name</th><th>Service</th><th>Area</th><th>Category</th></tr>
  {new_seller_rows if new_seller_rows else '<tr><td colspan=4>None this period</td></tr>'}
</table>

<h2>6. New Service Requests This Month ({data['new_request_count']})</h2>
<table>
  <tr><th>Buyer</th><th>Category</th><th>Location</th><th>Budget</th><th>Status</th></tr>
  {new_request_rows if new_request_rows else '<tr><td colspan=5>None this period</td></tr>'}
</table>

<h2>7. Recommendations</h2>
<div class="narrative">{ai_recommendations.replace(chr(10), '<br>')}</div>

<div class="footer">Koponix — AI-Powered Koperasi Digital Economy Activation System · Malaysia · {date.today()}</div>
</body>
</html>"""


# ── Main ──────────────────────────────────────────────────────────────────────
if not generate_btn and "report_cache" not in st.session_state:
    st.markdown("""
    <div style="background:#f8f9fa;border:1px dashed #ccc;border-radius:12px;
                padding:3rem;text-align:center;color:#888;margin-top:2rem;">
        <div style="font-size:2.5rem">📊</div>
        <p>Select a reporting period and click<br><strong>Generate Report</strong> in the sidebar.</p>
    </div>""", unsafe_allow_html=True)
    st.stop()

if generate_btn or "report_cache" in st.session_state:
    # ── Compute stats ──────────────────────────────────────────────────────────
    if generate_btn:
        with st.spinner("Analysing data and generating narrative…"):
            all_sellers  = load_sellers()
            all_requests = load_requests()

            active_s     = [s for s in all_sellers  if s.get("status") == "active"]
            new_sellers  = [s for s in all_sellers  if in_period(s.get("registered_date",""), report_year, report_month)]
            new_requests = [r for r in all_requests if in_period(r.get("submitted_date",""),  report_year, report_month)]

            # prev month for deltas
            prev_month = report_month - 1 if report_month > 1 else 12
            prev_year  = report_year if report_month > 1 else report_year - 1
            prev_sellers  = [s for s in all_sellers  if in_period(s.get("registered_date",""), prev_year, prev_month)]
            prev_requests = [r for r in all_requests if in_period(r.get("submitted_date",""),  prev_year, prev_month)]

            cat_sellers  = Counter(s["category"] for s in active_s)
            cat_requests = Counter(r["category"] for r in all_requests)
            req_status   = Counter(r.get("status","open") for r in all_requests)
            loc_sellers  = Counter(s["area"] for s in active_s)

            matched_count = req_status.get("matched", 0)
            match_rate    = f"{matched_count/len(all_requests)*100:.0f}%" if all_requests else "N/A"

            data = {
                "period":           period_label,
                "total_sellers":    len(all_sellers),
                "active_sellers":   len(active_s),
                "new_seller_count": len(new_sellers),
                "new_sellers":      new_sellers,
                "total_requests":   len(all_requests),
                "new_request_count":len(new_requests),
                "new_requests":     new_requests,
                "cat_sellers":      dict(cat_sellers),
                "cat_requests":     dict(cat_requests),
                "req_status":       dict(req_status),
                "loc_sellers":      dict(loc_sellers),
                "matched_count":    matched_count,
                "match_rate":       match_rate,
                "prev_sellers":     len(prev_sellers),
                "prev_requests":    len(prev_requests),
            }

            # ── AI narrative ───────────────────────────────────────────────────
            client = get_client()

            summary_prompt = (
                f"Write a 3–4 sentence executive summary for the Koponix Koperasi Monthly Report for {period_label}.\n\n"
                f"Platform data:\n"
                f"- Total active sellers: {len(active_s)}\n"
                f"- New seller registrations this month: {len(new_sellers)}\n"
                f"- Total buyer requests to date: {len(all_requests)}\n"
                f"- New requests this month: {len(new_requests)}\n"
                f"- Matched requests: {matched_count} ({match_rate})\n"
                f"- Top category by sellers: {cat_sellers.most_common(1)[0][0] if cat_sellers else 'N/A'}\n"
                f"- Top category by requests: {cat_requests.most_common(1)[0][0] if cat_requests else 'N/A'}\n\n"
                "Write in professional English. Mention key highlights and overall health of the platform. "
                "Be concise and factual. Suitable for a koperasi management report."
            )

            rec_prompt = (
                f"Based on this Koponix platform data for {period_label}, provide 3–4 actionable recommendations "
                f"for the koperasi management to improve member participation and transaction volume:\n\n"
                f"- Active sellers: {len(active_s)}, New this month: {len(new_sellers)}\n"
                f"- Total requests: {len(all_requests)}, Matched: {matched_count}\n"
                f"- Categories with most sellers: {', '.join(c for c,_ in cat_sellers.most_common(3))}\n"
                f"- Categories with most requests: {', '.join(c for c,_ in cat_requests.most_common(3))}\n"
                f"- Open (unmatched) requests: {req_status.get('open',0)}\n\n"
                "Format as a numbered list. Each recommendation should be 1–2 sentences. Practical and specific."
            )

            r1 = client.messages.create(model="claude-opus-4-6", max_tokens=300,
                                         system=KOPONIX_SYSTEM_PROMPT,
                                         messages=[{"role":"user","content":summary_prompt}])
            r2 = client.messages.create(model="claude-opus-4-6", max_tokens=400,
                                         system=KOPONIX_SYSTEM_PROMPT,
                                         messages=[{"role":"user","content":rec_prompt}])

            ai_summary = r1.content[0].text
            ai_recs    = r2.content[0].text

            html_report = build_html_report(data, ai_summary, ai_recs)

            st.session_state.report_cache = {
                "data": data, "ai_summary": ai_summary,
                "ai_recs": ai_recs, "html": html_report,
            }

    cache = st.session_state.report_cache
    data, ai_summary, ai_recs, html_report = (
        cache["data"], cache["ai_summary"], cache["ai_recs"], cache["html"]
    )

    # ── Render on-screen ──────────────────────────────────────────────────────
    # Download button at top
    dl_col, _ = st.columns([2, 5])
    with dl_col:
        st.download_button(
            label="📥 Download as HTML (Print to PDF)",
            data=html_report,
            file_name=f"koponix_report_{report_year}_{report_month:02d}.html",
            mime="text/html",
            use_container_width=True,
            type="primary",
        )

    st.markdown(f"## {period_label} — Activity Report")
    st.divider()

    # KPIs
    def delta_str(curr, prev):
        if prev == 0:
            return f"+{curr}" if curr else ""
        diff = curr - prev
        return f"+{diff}" if diff > 0 else (str(diff) if diff < 0 else "")

    k1,k2,k3,k4,k5 = st.columns(5)
    with k1: st.markdown(kpi_box(data["total_sellers"],    "Total Sellers",        "#1a5276"), unsafe_allow_html=True)
    with k2: st.markdown(kpi_box(data["active_sellers"],   "Active Listings",      "#27ae60"), unsafe_allow_html=True)
    with k3: st.markdown(kpi_box(data["new_seller_count"], "New Sellers (period)", "#2e86c1",
                                  delta_str(data["new_seller_count"], data["prev_sellers"])), unsafe_allow_html=True)
    with k4: st.markdown(kpi_box(data["total_requests"],   "Total Requests",       "#8e44ad"), unsafe_allow_html=True)
    with k5: st.markdown(kpi_box(data["new_request_count"],"New Requests (period)","#e67e22",
                                  delta_str(data["new_request_count"], data["prev_requests"])), unsafe_allow_html=True)

    st.markdown("<br>", unsafe_allow_html=True)

    # Executive summary
    st.markdown("### 📝 Executive Summary")
    st.info(ai_summary)

    st.divider()

    # Charts side by side
    ch1, ch2 = st.columns(2)
    with ch1:
        st.markdown("**Listings by Category**")
        max_s = max(data["cat_sellers"].values(), default=1)
        for cat, cnt in sorted(data["cat_sellers"].items(), key=lambda x: -x[1]):
            st.markdown(bar_html(cat, cnt, max_s, CATEGORY_COLORS.get(cat,"#607D8B"), CATEGORY_ICONS.get(cat,"⭐")),
                        unsafe_allow_html=True)

    with ch2:
        st.markdown("**Requests by Status**")
        total_r = data["total_requests"] or 1
        status_colors = {"open":"#e74c3c","in progress":"#e67e22","matched":"#27ae60","closed":"#95a5a6"}
        for s, cnt in sorted(data["req_status"].items(), key=lambda x: -x[1]):
            st.markdown(bar_html(s.title(), cnt, total_r, status_colors.get(s.lower(),"#607D8B")),
                        unsafe_allow_html=True)

    st.divider()

    # New registrations & requests this period
    r1c, r2c = st.columns(2)
    with r1c:
        st.markdown(f"**New Seller Registrations this period ({data['new_seller_count']})**")
        if data["new_sellers"]:
            for s in data["new_sellers"]:
                color = CATEGORY_COLORS.get(s["category"],"#607D8B")
                icon  = CATEGORY_ICONS.get(s["category"],"⭐")
                st.markdown(f"""
                <div style="background:#f8f9fa;border-left:3px solid {color};border-radius:0 8px 8px 0;
                            padding:0.5rem 0.8rem;margin-bottom:5px;font-size:0.82rem;">
                    <strong>{s['name']}</strong> — {s['service_title']}<br>
                    <span style="color:#666;">{icon} {s['category']} · 📍 {s['area']} · 💰 {s['price_range']}</span>
                </div>""", unsafe_allow_html=True)
        else:
            st.caption("No new registrations this period.")

    with r2c:
        st.markdown(f"**New Buyer Requests this period ({data['new_request_count']})**")
        if data["new_requests"]:
            for r in data["new_requests"]:
                color = CATEGORY_COLORS.get(r.get("category",""),"#607D8B")
                icon  = CATEGORY_ICONS.get(r.get("category",""),"⭐")
                status = r.get("status","open")
                pill_color = {"open":"#e74c3c","in progress":"#e67e22","matched":"#27ae60","closed":"#95a5a6"}.get(status.lower(),"#999")
                st.markdown(f"""
                <div style="background:#f8f9fa;border-left:3px solid {color};border-radius:0 8px 8px 0;
                            padding:0.5rem 0.8rem;margin-bottom:5px;font-size:0.82rem;">
                    <strong>{r.get('buyer_name','—')}</strong>
                    <span style="float:right;color:{pill_color};font-weight:600;font-size:0.72rem;">● {status.title()}</span><br>
                    <span style="color:#666;">{icon} {r.get('category','—')} · 📍 {r.get('location','—')} · 💰 {r.get('budget','—')}</span>
                </div>""", unsafe_allow_html=True)
        else:
            st.caption("No new requests this period.")

    st.divider()

    # Recommendations
    st.markdown("### 💡 Management Recommendations")
    st.success(ai_recs)

    st.divider()
    st.download_button(
        label="📥 Download Report as HTML (Print to PDF)",
        data=html_report,
        file_name=f"koponix_report_{report_year}_{report_month:02d}.html",
        mime="text/html",
        use_container_width=False,
    )
    st.caption("Open the downloaded file in your browser → File → Print → Save as PDF")
