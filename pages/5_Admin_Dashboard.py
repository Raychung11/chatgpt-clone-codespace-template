"""Koponix — Admin Dashboard (password-protected)."""
import csv
import io
from collections import Counter
from datetime import date

import streamlit as st

from utils import (
    CATEGORIES,
    CATEGORY_COLORS,
    CATEGORY_ICONS,
    LOCATIONS,
    apply_koponix_style,
    delete_seller,
    load_requests,
    load_sellers,
    save_seller,
    sidebar_logo,
    update_request_status,
    update_seller_status,
)

st.set_page_config(
    page_title="Admin Dashboard — Koponix",
    page_icon="🛡️",
    layout="wide",
    initial_sidebar_state="expanded",
)
apply_koponix_style()

# ── Password gate ─────────────────────────────────────────────────────────────
ADMIN_PASSWORD = st.secrets.get("ADMIN_PASSWORD", "koponix-admin")

if "admin_authenticated" not in st.session_state:
    st.session_state.admin_authenticated = False

if not st.session_state.admin_authenticated:
    st.markdown("""
    <div style="max-width:380px; margin:6rem auto; padding:2rem;
                border:1px solid #dde; border-radius:16px;
                background:white; box-shadow:0 4px 20px rgba(0,0,0,0.08);">
        <div style="text-align:center; margin-bottom:1.5rem;">
            <div style="font-size:2.5rem">🛡️</div>
            <h2 style="color:#1a5276; margin:0.3rem 0 0.1rem;">Admin Dashboard</h2>
            <p style="color:#666; font-size:0.82rem; margin:0">Koponix Platform Management</p>
        </div>
    </div>
    """, unsafe_allow_html=True)

    with st.form("admin_login"):
        password = st.text_input("Admin Password", type="password", placeholder="Enter password")
        if st.form_submit_button("Sign In", type="primary", use_container_width=True):
            if password == ADMIN_PASSWORD:
                st.session_state.admin_authenticated = True
                st.rerun()
            else:
                st.error("Incorrect password. Please try again.")
    st.stop()

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
    st.divider()
    if st.button("🔒 Sign Out", use_container_width=True):
        st.session_state.admin_authenticated = False
        st.rerun()

# ── Load data ─────────────────────────────────────────────────────────────────
sellers  = load_sellers()
requests = load_requests()

active_sellers   = [s for s in sellers  if s.get("status") == "active"]
inactive_sellers = [s for s in sellers  if s.get("status") != "active"]
open_requests    = [r for r in requests if r.get("status") == "open"]
closed_requests  = [r for r in requests if r.get("status") != "open"]

# ── Page header ───────────────────────────────────────────────────────────────
st.markdown('<div class="section-head">🛡️ Admin Dashboard</div>', unsafe_allow_html=True)
st.caption(f"Koponix Platform Management · {date.today()}")

# ── KPI row ───────────────────────────────────────────────────────────────────
k1, k2, k3, k4, k5 = st.columns(5)
kpi_style = lambda num, lbl, color: f"""
<div style="background:{color}18; border:1px solid {color}44; border-radius:10px;
            text-align:center; padding:0.9rem 0.4rem;">
    <div style="font-size:1.9rem; font-weight:800; color:{color};">{num}</div>
    <div style="font-size:0.72rem; color:#555;">{lbl}</div>
</div>"""

with k1: st.markdown(kpi_style(len(sellers),         "Total Sellers",     "#1a5276"), unsafe_allow_html=True)
with k2: st.markdown(kpi_style(len(active_sellers),  "Active Listings",   "#27ae60"), unsafe_allow_html=True)
with k3: st.markdown(kpi_style(len(inactive_sellers),"Inactive Listings", "#e67e22"), unsafe_allow_html=True)
with k4: st.markdown(kpi_style(len(requests),        "Total Requests",    "#8e44ad"), unsafe_allow_html=True)
with k5: st.markdown(kpi_style(len(open_requests),   "Open Requests",     "#e74c3c"), unsafe_allow_html=True)

st.markdown("<br>", unsafe_allow_html=True)

# ── Tabs ──────────────────────────────────────────────────────────────────────
tab_overview, tab_sellers, tab_requests, tab_add, tab_export = st.tabs([
    "📊 Overview",
    "👤 Sellers",
    "📋 Requests",
    "➕ Add Seller",
    "📥 Export",
])

# ═══════════════════════════════════════════════════════════════════════════════
# OVERVIEW TAB
# ═══════════════════════════════════════════════════════════════════════════════
with tab_overview:
    col_left, col_right = st.columns(2)

    with col_left:
        st.markdown("**Listings by Category**")
        cat_counts = Counter(s["category"] for s in active_sellers)
        if cat_counts:
            cat_labels = list(cat_counts.keys())
            cat_vals   = list(cat_counts.values())
            # bar chart using st native
            chart_data = {cat: [cnt] for cat, cnt in sorted(cat_counts.items(), key=lambda x: -x[1])}
            for cat, cnt in sorted(cat_counts.items(), key=lambda x: -x[1]):
                color  = CATEGORY_COLORS.get(cat, "#607D8B")
                icon   = CATEGORY_ICONS.get(cat, "⭐")
                pct    = cnt / len(active_sellers) * 100 if active_sellers else 0
                st.markdown(f"""
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                    <span style="width:18px; text-align:center; font-size:0.9rem">{icon}</span>
                    <span style="width:160px; font-size:0.8rem; color:#333;">{cat}</span>
                    <div style="flex:1; background:#eee; border-radius:4px; height:14px; overflow:hidden;">
                        <div style="width:{pct:.0f}%; background:{color}; height:100%; border-radius:4px;"></div>
                    </div>
                    <span style="font-size:0.78rem; color:#555; min-width:24px; text-align:right">{cnt}</span>
                </div>""", unsafe_allow_html=True)
        else:
            st.info("No active sellers yet.")

    with col_right:
        st.markdown("**Requests by Category**")
        req_counts = Counter(r["category"] for r in requests)
        if req_counts:
            for cat, cnt in sorted(req_counts.items(), key=lambda x: -x[1]):
                color = CATEGORY_COLORS.get(cat, "#607D8B")
                icon  = CATEGORY_ICONS.get(cat, "⭐")
                pct   = cnt / len(requests) * 100 if requests else 0
                st.markdown(f"""
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                    <span style="width:18px; text-align:center; font-size:0.9rem">{icon}</span>
                    <span style="width:160px; font-size:0.8rem; color:#333;">{cat}</span>
                    <div style="flex:1; background:#eee; border-radius:4px; height:14px; overflow:hidden;">
                        <div style="width:{pct:.0f}%; background:{color}; height:100%; border-radius:4px;"></div>
                    </div>
                    <span style="font-size:0.78rem; color:#555; min-width:24px; text-align:right">{cnt}</span>
                </div>""", unsafe_allow_html=True)
        else:
            st.info("No requests submitted yet.")

    st.divider()
    st.markdown("**Top Locations (Active Sellers)**")
    loc_counts = Counter(s["area"] for s in active_sellers)
    lc1, lc2, lc3 = st.columns(3)
    for i, (loc, cnt) in enumerate(loc_counts.most_common(9)):
        with [lc1, lc2, lc3][i % 3]:
            st.markdown(
                f'<div style="background:#f0f4f8; border-radius:8px; padding:6px 10px; '
                f'margin-bottom:5px; font-size:0.82rem;">📍 <strong>{loc}</strong> — {cnt}</div>',
                unsafe_allow_html=True
            )

# ═══════════════════════════════════════════════════════════════════════════════
# SELLERS TAB
# ═══════════════════════════════════════════════════════════════════════════════
with tab_sellers:
    # Filter bar
    sf1, sf2, sf3 = st.columns([2, 2, 3])
    with sf1:
        s_filter_status = st.selectbox("Status", ["All", "Active", "Inactive"], key="sf_status")
    with sf2:
        s_filter_cat = st.selectbox("Category", ["All"] + CATEGORIES, key="sf_cat")
    with sf3:
        s_search = st.text_input("Search name / title", key="sf_search", placeholder="Type to filter…")

    display_sellers = sellers
    if s_filter_status == "Active":
        display_sellers = [s for s in display_sellers if s.get("status") == "active"]
    elif s_filter_status == "Inactive":
        display_sellers = [s for s in display_sellers if s.get("status") != "active"]
    if s_filter_cat != "All":
        display_sellers = [s for s in display_sellers if s["category"] == s_filter_cat]
    if s_search.strip():
        q = s_search.lower()
        display_sellers = [
            s for s in display_sellers
            if q in s["name"].lower() or q in s["service_title"].lower()
        ]

    st.caption(f"Showing {len(display_sellers)} of {len(sellers)} sellers")

    for seller in display_sellers:
        color  = CATEGORY_COLORS.get(seller["category"], "#607D8B")
        icon   = CATEGORY_ICONS.get(seller["category"], "⭐")
        status = seller.get("status", "active")
        status_html = (
            '<span class="pill-active">● Active</span>' if status == "active"
            else '<span class="pill-pending">● Inactive</span>'
        )

        with st.container():
            c_info, c_actions = st.columns([4, 1])
            with c_info:
                st.markdown(f"""
                <div class="seller-card" style="margin-bottom:0.4rem;">
                    <span class="cat-badge" style="background:{color}">{icon} {seller['category']}</span>
                    &nbsp;{status_html}
                    <h4 style="margin:4px 0 2px;">{seller['service_title']}</h4>
                    <div class="meta">👤 {seller['name']} &nbsp;|&nbsp; 🏅 {seller.get('koperasi_id','—')}</div>
                    <div class="meta">📍 {seller['area']} &nbsp;|&nbsp; 💰 {seller['price_range']} &nbsp;|&nbsp; 🕐 {seller['availability']}</div>
                    <div class="meta" style="color:#888; font-size:0.72rem;">ID: {seller['id'][:8]} · Registered: {seller.get('registered_date','—')}</div>
                </div>""", unsafe_allow_html=True)

            with c_actions:
                uid = seller["id"][:8]
                if status == "active":
                    if st.button("⏸ Deactivate", key=f"deact_{uid}", use_container_width=True):
                        update_seller_status(seller["id"], "inactive")
                        st.toast(f"Deactivated: {seller['name']}", icon="⏸")
                        st.rerun()
                else:
                    if st.button("▶ Activate", key=f"act_{uid}", use_container_width=True, type="primary"):
                        update_seller_status(seller["id"], "active")
                        st.toast(f"Activated: {seller['name']}", icon="▶")
                        st.rerun()

                with st.popover("🗑 Delete", use_container_width=True):
                    st.warning(f"Delete **{seller['name']}**?")
                    if st.button("Yes, delete", key=f"del_confirm_{uid}", type="primary"):
                        delete_seller(seller["id"])
                        st.toast(f"Deleted: {seller['name']}", icon="🗑")
                        st.rerun()

# ═══════════════════════════════════════════════════════════════════════════════
# REQUESTS TAB
# ═══════════════════════════════════════════════════════════════════════════════
with tab_requests:
    rf1, rf2 = st.columns([2, 3])
    with rf1:
        r_filter = st.selectbox("Status", ["All", "Open", "In Progress", "Matched", "Closed"], key="rf_status")
    with rf2:
        r_search = st.text_input("Search buyer / category", key="rf_search", placeholder="Type to filter…")

    display_requests = requests
    if r_filter != "All":
        display_requests = [r for r in display_requests if r.get("status", "open").lower() == r_filter.lower()]
    if r_search.strip():
        q = r_search.lower()
        display_requests = [
            r for r in display_requests
            if q in r.get("buyer_name", "").lower()
            or q in r.get("category", "").lower()
            or q in r.get("service_description", "").lower()
        ]

    st.caption(f"Showing {len(display_requests)} of {len(requests)} requests")

    if not display_requests:
        st.info("No requests match your filter.")
    else:
        for req in reversed(display_requests):  # newest first
            status = req.get("status", "open")
            status_colors = {
                "open":        ("#e74c3c", "🔴"),
                "in progress": ("#e67e22", "🟠"),
                "matched":     ("#27ae60", "🟢"),
                "closed":      ("#95a5a6", "⚫"),
            }
            sc, si = status_colors.get(status.lower(), ("#999", "⚪"))
            color  = CATEGORY_COLORS.get(req.get("category",""), "#607D8B")
            icon   = CATEGORY_ICONS.get(req.get("category",""), "⭐")
            rid    = req.get("id","")[:8]

            with st.container():
                c_req, c_act = st.columns([4, 1])
                with c_req:
                    st.markdown(f"""
                    <div class="seller-card" style="margin-bottom:0.4rem;">
                        <span class="cat-badge" style="background:{color}">{icon} {req.get('category','—')}</span>
                        &nbsp;<span style="font-size:0.72rem; color:{sc}; font-weight:600;">{si} {status.title()}</span>
                        <h4 style="margin:4px 0 2px;">{req.get('service_description','')[:90]}{'…' if len(req.get('service_description',''))>90 else ''}</h4>
                        <div class="meta">👤 {req.get('buyer_name','—')} &nbsp;|&nbsp; 📞 {req.get('buyer_contact','—')}</div>
                        <div class="meta">📍 {req.get('location','—')} &nbsp;|&nbsp; 💰 {req.get('budget','—')} &nbsp;|&nbsp; 📅 {req.get('preferred_date','—')}</div>
                        <div class="meta" style="color:#888; font-size:0.72rem;">ID: {rid} · Submitted: {req.get('submitted_date','—')}</div>
                    </div>""", unsafe_allow_html=True)
                with c_act:
                    new_status = st.selectbox(
                        "Set status",
                        ["open", "in progress", "matched", "closed"],
                        index=["open","in progress","matched","closed"].index(status.lower()) if status.lower() in ["open","in progress","matched","closed"] else 0,
                        key=f"rs_{rid}",
                    )
                    if st.button("Update", key=f"ru_{rid}", use_container_width=True, type="primary"):
                        update_request_status(req["id"], new_status)
                        st.toast(f"Request {rid} → {new_status}", icon="✅")
                        st.rerun()

# ═══════════════════════════════════════════════════════════════════════════════
# ADD SELLER TAB (admin direct add)
# ═══════════════════════════════════════════════════════════════════════════════
with tab_add:
    st.caption("Manually add a seller listing on behalf of a koperasi member.")

    with st.form("admin_add_seller"):
        ac1, ac2 = st.columns(2)
        with ac1:
            a_name = st.text_input("Full Name *")
            a_kop  = st.text_input("Koperasi Member ID *")
            a_cat  = st.selectbox("Category *", CATEGORIES)
            a_exp  = st.selectbox("Experience", ["Less than 1 year","1–2 years","3–5 years","5–10 years","10+ years"])
        with ac2:
            a_title = st.text_input("Service Title *")
            a_area  = st.selectbox("Service Area *", LOCATIONS)
            a_price = st.text_input("Price Range *")
            a_avail = st.text_input("Availability *")

        a_desc    = st.text_area("Description *", height=100)
        a_contact = st.text_input("Contact", placeholder="Leave blank for default")
        a_status  = st.selectbox("Initial Status", ["active","inactive"])

        if st.form_submit_button("➕ Add Seller", type="primary", use_container_width=True):
            required = {"Name": a_name,"Koperasi ID": a_kop,"Title": a_title,"Description": a_desc,"Price": a_price,"Availability": a_avail}
            missing  = [k for k,v in required.items() if not v.strip()]
            if missing:
                st.error(f"Please fill in: {', '.join(missing)}")
            else:
                save_seller({
                    "name": a_name.strip(), "koperasi_id": a_kop.strip(),
                    "category": a_cat, "service_title": a_title.strip(),
                    "description": a_desc.strip(), "area": a_area,
                    "price_range": a_price.strip(), "availability": a_avail.strip(),
                    "experience": a_exp,
                    "contact": a_contact.strip() or "WhatsApp available upon request",
                })
                if a_status == "inactive":
                    sellers_fresh = load_sellers()
                    update_seller_status(sellers_fresh[-1]["id"], "inactive")
                st.success(f"Seller '{a_title}' added successfully!")
                st.rerun()

# ═══════════════════════════════════════════════════════════════════════════════
# EXPORT TAB
# ═══════════════════════════════════════════════════════════════════════════════
with tab_export:
    st.markdown("Download platform data as CSV for offline reporting or backup.")

    def to_csv(data: list, fields: list) -> str:
        buf = io.StringIO()
        writer = csv.DictWriter(buf, fieldnames=fields, extrasaction="ignore")
        writer.writeheader()
        writer.writerows(data)
        return buf.getvalue()

    e1, e2 = st.columns(2)

    with e1:
        st.markdown("**Seller Listings**")
        st.metric("Total sellers", len(sellers))
        seller_fields = ["id","name","koperasi_id","category","service_title","area","price_range","availability","experience","status","registered_date","description"]
        st.download_button(
            label="📥 Download sellers.csv",
            data=to_csv(sellers, seller_fields),
            file_name="koponix_sellers.csv",
            mime="text/csv",
            use_container_width=True,
        )

    with e2:
        st.markdown("**Buyer Requests**")
        st.metric("Total requests", len(requests))
        request_fields = ["id","buyer_name","buyer_contact","category","location","service_description","preferred_date","urgency","budget","status","submitted_date","special_notes"]
        st.download_button(
            label="📥 Download requests.csv",
            data=to_csv(requests, request_fields),
            file_name="koponix_requests.csv",
            mime="text/csv",
            use_container_width=True,
        )

    st.divider()
    st.markdown("**Danger Zone**")
    with st.expander("⚠️ Reset seed data (replaces all sellers with demo data)"):
        st.warning(
            "This will delete all current seller records and restore the 8 demo sellers. "
            "Buyer requests are not affected."
        )
        confirm_reset = st.text_input("Type **RESET** to confirm", key="reset_confirm")
        if st.button("🔄 Reset Sellers", type="primary") and confirm_reset == "RESET":
            import json, os
            from utils import SELLERS_FILE, DEMO_SELLERS
            with open(SELLERS_FILE, "w") as f:
                json.dump(DEMO_SELLERS, f, indent=2)
            st.success("Seller data reset to demo state.")
            st.rerun()
