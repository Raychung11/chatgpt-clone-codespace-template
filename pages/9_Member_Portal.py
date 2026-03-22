"""Koponix — Member Portal: login, register, personal dashboard."""
import anthropic
import streamlit as st

from utils import (
    CATEGORY_COLORS,
    CATEGORY_ICONS,
    KOPONIX_SYSTEM_PROMPT,
    apply_koponix_style,
    authenticate_member,
    get_member_by_kop_id,
    hash_password,
    load_requests,
    load_sellers,
    save_member,
    sidebar_logo,
    sidebar_member_status,
)

st.set_page_config(
    page_title="Member Portal — Koponix",
    page_icon="👤",
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
    st.page_link("pages/9_Member_Portal.py",        label="👤  Member Portal")
    sidebar_member_status()

# ── Shared client ─────────────────────────────────────────────────────────────
@st.cache_resource
def get_client():
    return anthropic.Anthropic(api_key=st.secrets["ANTHROPIC_API_KEY"])

# ══════════════════════════════════════════════════════════════════════════════
# NOT LOGGED IN — show login / register tabs
# ══════════════════════════════════════════════════════════════════════════════
if not st.session_state.get("member_logged_in"):
    st.markdown('<div class="section-head">👤 Member Portal</div>', unsafe_allow_html=True)
    st.caption("Sign in to access your personal dashboard, listings, and requests.")

    login_tab, register_tab = st.tabs(["🔑 Sign In", "📝 Create Account"])

    # ── Login ─────────────────────────────────────────────────────────────────
    with login_tab:
        st.markdown("<br>", unsafe_allow_html=True)
        with st.form("login_form"):
            li_kop = st.text_input("Koperasi Member ID", placeholder="e.g. KOP-2024-001")
            li_pw  = st.text_input("Password", type="password")
            if st.form_submit_button("Sign In", type="primary", use_container_width=True):
                if not li_kop.strip() or not li_pw.strip():
                    st.error("Please enter your Member ID and password.")
                else:
                    member = authenticate_member(li_kop.strip(), li_pw.strip())
                    if member:
                        st.session_state["member_logged_in"] = True
                        st.session_state["member"] = member
                        st.success(f"Welcome back, {member['name']}!")
                        st.rerun()
                    else:
                        st.error("Incorrect Member ID or password. Please try again.")

        st.caption("Don't have an account? Use the **Create Account** tab.")

    # ── Register ──────────────────────────────────────────────────────────────
    with register_tab:
        st.markdown("<br>", unsafe_allow_html=True)
        with st.form("register_form"):
            r1c, r2c = st.columns(2)
            with r1c:
                reg_name = st.text_input("Full Name *", placeholder="e.g. Ahmad Rizal bin Hassan")
                reg_kop  = st.text_input("Koperasi Member ID *", placeholder="e.g. KOP-2024-001")
            with r2c:
                reg_pw   = st.text_input("Password *", type="password", placeholder="Min 6 characters")
                reg_pw2  = st.text_input("Confirm Password *", type="password")
            reg_email = st.text_input("Email (optional)", placeholder="you@example.com")

            if st.form_submit_button("Create Account", type="primary", use_container_width=True):
                errors = []
                if not reg_name.strip():   errors.append("Full name is required.")
                if not reg_kop.strip():    errors.append("Koperasi Member ID is required.")
                if len(reg_pw) < 6:        errors.append("Password must be at least 6 characters.")
                if reg_pw != reg_pw2:      errors.append("Passwords do not match.")
                if get_member_by_kop_id(reg_kop.strip()):
                    errors.append("An account with this Member ID already exists.")

                if errors:
                    for e in errors:
                        st.error(e)
                else:
                    new_member = {
                        "name":          reg_name.strip(),
                        "koperasi_id":   reg_kop.strip(),
                        "email":         reg_email.strip(),
                        "password_hash": hash_password(reg_pw),
                        "role":          "member",
                    }
                    mid = save_member(new_member)
                    new_member["id"] = mid
                    st.session_state["member_logged_in"] = True
                    st.session_state["member"] = new_member
                    st.success(f"Account created! Welcome, {reg_name}.")
                    st.rerun()

    st.stop()

# ══════════════════════════════════════════════════════════════════════════════
# LOGGED IN — personal dashboard
# ══════════════════════════════════════════════════════════════════════════════
member = st.session_state["member"]
kop_id = member["koperasi_id"]

all_sellers  = load_sellers()
all_requests = load_requests()

my_listings = [s for s in all_sellers  if s.get("koperasi_id") == kop_id]
my_requests = [r for r in all_requests if r.get("member_kop_id") == kop_id]

active_listings  = [s for s in my_listings if s.get("status") == "active"]
open_requests    = [r for r in my_requests  if r.get("status") == "open"]

# ── Welcome banner ────────────────────────────────────────────────────────────
st.markdown(f"""
<div class="hero" style="padding:1.5rem 2rem;">
    <h2 style="margin:0 0 0.3rem;">Welcome back, {member['name']} 👋</h2>
    <p style="margin:0;opacity:0.9;">
        {kop_id} &nbsp;·&nbsp; Member since {member.get('joined_date','—')}
    </p>
</div>""", unsafe_allow_html=True)

# ── KPIs ──────────────────────────────────────────────────────────────────────
k1, k2, k3, k4 = st.columns(4)
def mini_kpi(num, label, color):
    return f'<div style="background:{color}18;border:1px solid {color}44;border-radius:10px;text-align:center;padding:0.8rem 0.4rem;"><div style="font-size:1.7rem;font-weight:800;color:{color};">{num}</div><div style="font-size:0.72rem;color:#555;">{label}</div></div>'

with k1: st.markdown(mini_kpi(len(my_listings),    "My Listings",       "#1a5276"), unsafe_allow_html=True)
with k2: st.markdown(mini_kpi(len(active_listings), "Active",           "#27ae60"), unsafe_allow_html=True)
with k3: st.markdown(mini_kpi(len(my_requests),     "My Requests",      "#8e44ad"), unsafe_allow_html=True)
with k4: st.markdown(mini_kpi(len(open_requests),   "Open",             "#e67e22"), unsafe_allow_html=True)

st.markdown("<br>", unsafe_allow_html=True)

# ── Tabs ──────────────────────────────────────────────────────────────────────
tab_listings, tab_requests, tab_ai_tip, tab_profile = st.tabs([
    "💼 My Listings",
    "📋 My Requests",
    "🤖 AI Coaching",
    "⚙️ Profile",
])

# ── My Listings ───────────────────────────────────────────────────────────────
with tab_listings:
    if not my_listings:
        st.info("You have no service listings yet.")
    else:
        for seller in my_listings:
            color  = CATEGORY_COLORS.get(seller["category"], "#607D8B")
            icon   = CATEGORY_ICONS.get(seller["category"], "⭐")
            status = seller.get("status", "active")
            pill   = '<span class="pill-active">● Active</span>' if status == "active" else '<span class="pill-pending">● Inactive</span>'
            st.markdown(f"""
            <div class="seller-card">
                <span class="cat-badge" style="background:{color}">{icon} {seller['category']}</span>
                &nbsp;{pill}
                <h4>{seller['service_title']}</h4>
                <div class="meta">📍 {seller['area']} &nbsp;|&nbsp; 💰 {seller['price_range']} &nbsp;|&nbsp; 🕐 {seller['availability']}</div>
                <div class="meta" style="color:#888;font-size:0.72rem;">ID: {seller['id'][:8]} · Registered: {seller.get('registered_date','—')}</div>
                <div class="desc">{seller['description']}</div>
            </div>""", unsafe_allow_html=True)

            ac1, ac2, ac3 = st.columns(3)
            with ac1:
                if st.button("📣 Generate Promo", key=f"promo_{seller['id'][:8]}", use_container_width=True):
                    st.switch_page("pages/7_Promo_Generator.py")
            with ac2:
                if st.button("💬 Ask AI for tips", key=f"aitip_{seller['id'][:8]}", use_container_width=True):
                    st.session_state["pending_prompt"] = (
                        f"I have a listing on Koponix: '{seller['service_title']}' in {seller['category']}, "
                        f"covering {seller['area']}, priced {seller['price_range']}. "
                        "How can I attract more buyers and improve my listing?"
                    )
                    st.switch_page("pages/1_AI_Assistant.py")
            with ac3:
                if st.button("🔍 View in listings", key=f"view_{seller['id'][:8]}", use_container_width=True):
                    st.switch_page("pages/2_Find_Services.py")

    st.divider()
    if st.button("➕ Add New Service Listing", type="primary"):
        st.switch_page("pages/3_Register_Service.py")

# ── My Requests ───────────────────────────────────────────────────────────────
with tab_requests:
    if not my_requests:
        st.info(
            "No requests linked to your account yet.\n\n"
            "When you submit a request while logged in, it will appear here."
        )
    else:
        status_colors = {"open":"#e74c3c","in progress":"#e67e22","matched":"#27ae60","closed":"#95a5a6"}
        for req in reversed(my_requests):
            color  = CATEGORY_COLORS.get(req.get("category",""), "#607D8B")
            icon   = CATEGORY_ICONS.get(req.get("category",""), "⭐")
            status = req.get("status","open").lower()
            sc     = status_colors.get(status, "#999")
            st.markdown(f"""
            <div class="seller-card">
                <span class="cat-badge" style="background:{color}">{icon} {req.get('category','—')}</span>
                <span style="font-size:0.72rem;color:{sc};font-weight:600;margin-left:6px;">● {status.title()}</span>
                <h4 style="margin:4px 0 2px;">{req.get('service_description','')[:90]}{'…' if len(req.get('service_description',''))>90 else ''}</h4>
                <div class="meta">📍 {req.get('location','—')} &nbsp;|&nbsp; 💰 {req.get('budget','—')} &nbsp;|&nbsp; 📅 {req.get('preferred_date','—')}</div>
                <div class="meta" style="color:#888;font-size:0.72rem;">ID: {req['id'][:8]} · Submitted: {req.get('submitted_date','—')}</div>
            </div>""", unsafe_allow_html=True)

    st.divider()
    if st.button("➕ Submit New Request", type="primary"):
        st.switch_page("pages/4_Request_Service.py")

# ── AI Coaching ───────────────────────────────────────────────────────────────
with tab_ai_tip:
    st.caption("Get personalised AI coaching based on your activity on Koponix.")

    if not my_listings and not my_requests:
        activity_context = "This member has no listings or requests yet — they are completely new."
    else:
        listings_summary = "; ".join(
            f"{s['service_title']} ({s['category']}, {s['area']}, {s['price_range']})"
            for s in my_listings
        ) or "none"
        requests_summary = "; ".join(
            f"{r['category']} in {r['location']}, budget {r['budget']}, status {r.get('status','open')}"
            for r in my_requests
        ) or "none"
        activity_context = (
            f"Member: {member['name']}, ID: {kop_id}\n"
            f"Active listings: {listings_summary}\n"
            f"Buyer requests: {requests_summary}"
        )

    coach_prompt = (
        f"You are a Koponix AI coach. Give this koperasi member 3 short, specific, "
        f"actionable tips to get more value from the Koponix platform.\n\n"
        f"Member activity:\n{activity_context}\n\n"
        "Format as numbered list. Each tip max 2 sentences. Be direct and practical. "
        "Do NOT use generic phrases like 'great job' or 'keep it up'."
    )

    if st.button("✨ Get My AI Coaching Tips", type="primary", use_container_width=False):
        with st.spinner("Generating personalised tips…"):
            resp = get_client().messages.create(
                model="claude-opus-4-6",
                max_tokens=350,
                system=KOPONIX_SYSTEM_PROMPT,
                messages=[{"role": "user", "content": coach_prompt}],
            )
        st.session_state["coaching_tips"] = resp.content[0].text.strip()

    if st.session_state.get("coaching_tips"):
        st.markdown(f"""
        <div style="background:#eaf4fb;border-left:4px solid #1a5276;border-radius:0 12px 12px 0;
                    padding:1rem 1.2rem;margin-top:0.8rem;font-size:0.88rem;line-height:1.7;">
            {st.session_state['coaching_tips'].replace(chr(10), '<br>')}
        </div>""", unsafe_allow_html=True)

        if st.button("🔄 Refresh tips"):
            st.session_state.pop("coaching_tips", None)
            st.rerun()

    st.divider()
    st.caption("Want to ask something specific? Use the AI Assistant.")
    if st.button("💬 Open AI Assistant"):
        st.switch_page("pages/1_AI_Assistant.py")

# ── Profile ───────────────────────────────────────────────────────────────────
with tab_profile:
    st.markdown(f"""
    | Field | Value |
    |---|---|
    | **Full Name** | {member['name']} |
    | **Koperasi Member ID** | {kop_id} |
    | **Email** | {member.get('email','—') or '—'} |
    | **Joined** | {member.get('joined_date','—')} |
    | **Account ID** | `{member.get('id','')[:8]}` |
    """)

    st.divider()
    st.markdown("**Change Password**")
    with st.form("change_pw"):
        cp_current = st.text_input("Current password", type="password")
        cp_new     = st.text_input("New password", type="password", placeholder="Min 6 characters")
        cp_confirm = st.text_input("Confirm new password", type="password")
        if st.form_submit_button("Update Password", type="primary"):
            import json, os
            from utils import MEMBERS_FILE, hash_password as hp, load_members
            errors = []
            if hp(cp_current) != member.get("password_hash"):
                errors.append("Current password is incorrect.")
            if len(cp_new) < 6:
                errors.append("New password must be at least 6 characters.")
            if cp_new != cp_confirm:
                errors.append("Passwords do not match.")
            if errors:
                for e in errors: st.error(e)
            else:
                members = load_members()
                for m in members:
                    if m["id"] == member["id"]:
                        m["password_hash"] = hp(cp_new)
                        break
                with open(MEMBERS_FILE, "w") as f:
                    json.dump(members, f, indent=2)
                st.session_state["member"]["password_hash"] = hp(cp_new)
                st.success("Password updated successfully.")

    st.divider()
    if st.button("🔒 Sign Out", type="secondary"):
        st.session_state.pop("member_logged_in", None)
        st.session_state.pop("member", None)
        st.session_state.pop("coaching_tips", None)
        st.rerun()
