"""Koponix — Landing / Home page."""
from collections import Counter

import anthropic
import streamlit as st

from utils import (
    CATEGORIES,
    CATEGORY_COLORS,
    CATEGORY_ICONS,
    KOPONIX_SYSTEM_PROMPT,
    apply_koponix_style,
    load_requests,
    load_sellers,
    sidebar_logo,
)

st.set_page_config(
    page_title="Koponix — Koperasi Digital Economy Platform",
    page_icon="🤝",
    layout="wide",
    initial_sidebar_state="expanded",
)
apply_koponix_style()

# ── Sidebar ───────────────────────────────────────────────────────────────────
with st.sidebar:
    sidebar_logo()
    st.page_link("app.py",                          label="🏠  Home",              )
    st.page_link("pages/1_AI_Assistant.py",         label="💬  AI Assistant",      )
    st.page_link("pages/2_Find_Services.py",        label="🔍  Find Services",     )
    st.page_link("pages/3_Register_Service.py",     label="💼  Register Service",  )
    st.page_link("pages/4_Request_Service.py",      label="🛒  Request a Service")
    st.page_link("pages/5_Admin_Dashboard.py",      label="🛡️  Admin Dashboard")
    st.page_link("pages/6_Match_Engine.py",         label="🎯  Match Engine")
    st.page_link("pages/7_Promo_Generator.py",      label="📣  Promo Generator")
    st.page_link("pages/8_Monthly_Report.py",       label="📊  Monthly Report")

# ── Hero ──────────────────────────────────────────────────────────────────────
st.markdown("""
<div class="hero">
    <h1>Activate Your Koperasi Economy 🤝</h1>
    <p>Koponix connects koperasi members as service providers and buyers — turning skills into income within a trusted, structured ecosystem.</p>
</div>
""", unsafe_allow_html=True)

col1, col2, col3 = st.columns(3)
with col1:
    if st.button("🔍  Browse Services", use_container_width=True, type="primary"):
        st.switch_page("pages/2_Find_Services.py")
with col2:
    if st.button("💼  Offer Your Service", use_container_width=True):
        st.switch_page("pages/3_Register_Service.py")
with col3:
    if st.button("💬  Ask Koponix AI", use_container_width=True):
        st.switch_page("pages/1_AI_Assistant.py")

st.markdown("<br>", unsafe_allow_html=True)

# ── Live stats ────────────────────────────────────────────────────────────────
sellers  = load_sellers()
requests = load_requests()
active_sellers = [s for s in sellers if s.get("status") == "active"]
categories_used = len(set(s["category"] for s in active_sellers))

s1, s2, s3, s4 = st.columns(4)
with s1:
    st.markdown(f'<div class="stat-box"><div class="num">{len(active_sellers)}</div><div class="lbl">Active Service Providers</div></div>', unsafe_allow_html=True)
with s2:
    st.markdown(f'<div class="stat-box"><div class="num">{categories_used}</div><div class="lbl">Service Categories</div></div>', unsafe_allow_html=True)
with s3:
    st.markdown('<div class="stat-box"><div class="num">1</div><div class="lbl">Koperasi Connected</div></div>', unsafe_allow_html=True)
with s4:
    st.markdown('<div class="stat-box"><div class="num">AI</div><div class="lbl">Powered Matching</div></div>', unsafe_allow_html=True)

st.markdown("<br>", unsafe_allow_html=True)

# ── How It Works ──────────────────────────────────────────────────────────────
st.markdown('<div class="section-head">How Koponix Works</div>', unsafe_allow_html=True)

c1, c2, c3 = st.columns(3)
with c1:
    st.markdown("""
    <div class="step-box">
        <h4>1️⃣  Register Your Service</h4>
        <p>Koperasi members list their skills and services with a clear scope, price, and availability.</p>
    </div>""", unsafe_allow_html=True)
with c2:
    st.markdown("""
    <div class="step-box">
        <h4>2️⃣  Get Matched with Buyers</h4>
        <p>Buyers submit service requests. Koponix AI helps match the right provider based on category, location, and needs.</p>
    </div>""", unsafe_allow_html=True)
with c3:
    st.markdown("""
    <div class="step-box">
        <h4>3️⃣  Transact & Earn</h4>
        <p>Confirm the booking, deliver the service, and receive payment — all within the koperasi's structured ecosystem.</p>
    </div>""", unsafe_allow_html=True)

st.markdown("<br>", unsafe_allow_html=True)

# ── Service Categories ────────────────────────────────────────────────────────
st.markdown('<div class="section-head">Service Categories</div>', unsafe_allow_html=True)

cat_cols = st.columns(4)
for i, cat in enumerate(CATEGORIES[:-1]):  # skip "Other" for display
    color = CATEGORY_COLORS[cat]
    icon = CATEGORY_ICONS[cat]
    count = len([s for s in active_sellers if s["category"] == cat])
    with cat_cols[i % 4]:
        st.markdown(f"""
        <div style="background:{color}18; border:1px solid {color}55; border-radius:10px;
                    padding:0.8rem 0.7rem; text-align:center; margin-bottom:0.6rem;">
            <div style="font-size:1.5rem">{icon}</div>
            <div style="font-size:0.82rem; font-weight:600; color:{color};">{cat}</div>
            <div style="font-size:0.72rem; color:#666;">{count} provider{"s" if count != 1 else ""}</div>
        </div>""", unsafe_allow_html=True)

st.markdown("<br>", unsafe_allow_html=True)

# ── Latest listings ───────────────────────────────────────────────────────────
st.markdown('<div class="section-head">Latest Service Listings</div>', unsafe_allow_html=True)

recent = sorted(active_sellers, key=lambda x: x.get("registered_date", ""), reverse=True)[:3]
r_cols = st.columns(3)
for i, seller in enumerate(recent):
    color = CATEGORY_COLORS.get(seller["category"], "#607D8B")
    icon = CATEGORY_ICONS.get(seller["category"], "⭐")
    with r_cols[i]:
        st.markdown(f"""
        <div class="seller-card">
            <span class="cat-badge" style="background:{color}">{icon} {seller['category']}</span>
            <h4>{seller['service_title']}</h4>
            <div class="meta">👤 {seller['name']}</div>
            <div class="meta">📍 {seller['area']} &nbsp;|&nbsp; 💰 {seller['price_range']}</div>
            <div class="desc">{seller['description'][:120]}…</div>
        </div>""", unsafe_allow_html=True)

st.markdown("<br>")
if st.button("View All Services →", use_container_width=False):
    st.switch_page("pages/2_Find_Services.py")

# ── AI Platform Pulse ──────────────────────────────────────────────────────────
st.markdown("<br>", unsafe_allow_html=True)
st.markdown('<div class="section-head">🤖 AI Platform Pulse</div>', unsafe_allow_html=True)

@st.cache_resource
def get_client():
    return anthropic.Anthropic(api_key=st.secrets["ANTHROPIC_API_KEY"])

pulse_col, pulse_btn_col = st.columns([5, 1])
with pulse_btn_col:
    run_pulse = st.button("✨ Generate Insight", use_container_width=True, type="primary")
    if st.button("🔄 Refresh", use_container_width=True):
        st.session_state.pop("pulse_text", None)
        st.rerun()

with pulse_col:
    if "pulse_text" not in st.session_state and not run_pulse:
        st.markdown("""
        <div style="background:#f8f9fa; border:1px dashed #ccc; border-radius:10px;
                    padding:1rem 1.2rem; color:#888; font-size:0.85rem;">
            Click <strong>Generate Insight</strong> for a live AI analysis of the platform's current state.
        </div>""", unsafe_allow_html=True)

    if run_pulse or "pulse_text" in st.session_state:
        if run_pulse or "pulse_text" not in st.session_state:
            open_reqs  = [r for r in requests if r.get("status","open") == "open"]
            matched    = [r for r in requests if r.get("status","") == "matched"]
            top_cat_s  = Counter(s["category"] for s in active_sellers).most_common(1)
            top_cat_r  = Counter(r["category"] for r in requests).most_common(1)
            match_rate = f"{len(matched)/len(requests)*100:.0f}%" if requests else "N/A"

            pulse_prompt = (
                "You are Koponix AI. Give a 3-sentence platform health snapshot for the koperasi management team.\n\n"
                f"Current data:\n"
                f"- Active service providers: {len(active_sellers)}\n"
                f"- Total buyer requests: {len(requests)}, open/unmatched: {len(open_reqs)}\n"
                f"- Match rate: {match_rate}\n"
                f"- Most popular seller category: {top_cat_s[0][0] if top_cat_s else 'N/A'}\n"
                f"- Most requested category: {top_cat_r[0][0] if top_cat_r else 'N/A'}\n\n"
                "Sentence 1: Overall platform health (positive framing). "
                "Sentence 2: One specific opportunity or gap (e.g. unmatched demand, underserved location). "
                "Sentence 3: One concrete action the admin can take today. "
                "Be direct and specific. No filler phrases."
            )
            with st.spinner("Analysing platform…"):
                resp = get_client().messages.create(
                    model="claude-opus-4-6",
                    max_tokens=200,
                    system=KOPONIX_SYSTEM_PROMPT,
                    messages=[{"role": "user", "content": pulse_prompt}],
                )
            st.session_state["pulse_text"] = resp.content[0].text.strip()

        pulse = st.session_state["pulse_text"]
        sentences = [s.strip() for s in pulse.replace("\n", " ").split(". ") if s.strip()]
        icons = ["📊", "💡", "✅"]
        pulse_html = "".join(
            f'<div style="display:flex;gap:10px;align-items:flex-start;margin-bottom:8px;">'
            f'<span style="font-size:1.1rem;flex-shrink:0;">{icons[i] if i < len(icons) else "•"}</span>'
            f'<span style="font-size:0.88rem;color:#2c3e50;line-height:1.5;">{s}{"." if not s.endswith(".") else ""}</span>'
            f'</div>'
            for i, s in enumerate(sentences)
        )
        st.markdown(f"""
        <div style="background:linear-gradient(135deg,#eaf4fb,#f8f9fa);
                    border:1px solid #2e86c155; border-radius:12px; padding:1rem 1.2rem;">
            {pulse_html}
        </div>""", unsafe_allow_html=True)

# ── Footer ─────────────────────────────────────────────────────────────────────
st.divider()
st.caption("Koponix — AI-Powered Koperasi Digital Economy Activation System · Malaysia")
