"""Koponix — Match Engine: auto-match open buyer requests to active sellers."""
import anthropic
import streamlit as st

from utils import (
    CATEGORY_COLORS,
    CATEGORY_ICONS,
    KOPONIX_SYSTEM_PROMPT,
    apply_koponix_style,
    get_top_matches,
    load_requests,
    load_sellers,
    sidebar_logo,
    sidebar_member_status,
    update_request_status,
)

st.set_page_config(
    page_title="Match Engine — Koponix",
    page_icon="🎯",
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
    st.divider()

    st.markdown("**Request Filter**")
    show_status = st.multiselect(
        "Show status",
        ["open", "in progress", "matched"],
        default=["open", "in progress"],
        key="me_status_filter",
    )
    auto_run = st.toggle("Auto-run AI on selection", value=False)
    sidebar_member_status()

# ── Page header ───────────────────────────────────────────────────────────────
st.markdown('<div class="section-head">🎯 Match Engine</div>', unsafe_allow_html=True)
st.caption("Select a buyer request to find and rank the best-fit service providers.")

# ── Load data ─────────────────────────────────────────────────────────────────
all_requests = load_requests()
all_sellers  = load_sellers()

filtered_reqs = [
    r for r in reversed(all_requests)
    if r.get("status", "open").lower() in [s.lower() for s in show_status]
]

if not filtered_reqs:
    st.info("No requests match the selected status filter.")
    st.stop()

# ── Session state ─────────────────────────────────────────────────────────────
if "me_selected_id" not in st.session_state:
    st.session_state.me_selected_id = filtered_reqs[0]["id"] if filtered_reqs else None
if "me_ai_result" not in st.session_state:
    st.session_state.me_ai_result = {}   # {request_id: ai_text}

# ── Layout: two columns ───────────────────────────────────────────────────────
col_list, col_detail = st.columns([1, 2], gap="large")

# ═══════════════════════════════════════════════════════════════════════════════
# LEFT: Request list
# ═══════════════════════════════════════════════════════════════════════════════
with col_list:
    st.markdown(f"**{len(filtered_reqs)} Request{'s' if len(filtered_reqs)!=1 else ''}**")

    status_pill = {
        "open":        ("🔴", "#e74c3c"),
        "in progress": ("🟠", "#e67e22"),
        "matched":     ("🟢", "#27ae60"),
        "closed":      ("⚫", "#95a5a6"),
    }

    for req in filtered_reqs:
        rid     = req["id"]
        status  = req.get("status", "open").lower()
        si, sc  = status_pill.get(status, ("⚪", "#999"))
        color   = CATEGORY_COLORS.get(req.get("category",""), "#607D8B")
        icon    = CATEGORY_ICONS.get(req.get("category",""), "⭐")
        is_sel  = (rid == st.session_state.me_selected_id)

        border  = f"3px solid {color}" if is_sel else "1px solid #dde"
        bg      = f"{color}0d" if is_sel else "white"

        btn_label = (
            f"{icon} **{req.get('category','—')}**\n\n"
            f"{req.get('service_description','')[:55]}…\n\n"
            f"📍 {req.get('location','—')}  ·  💰 {req.get('budget','—')}"
        )

        st.markdown(f"""
        <div style="background:{bg}; border:{border}; border-radius:10px;
                    padding:0.7rem 0.9rem; margin-bottom:6px; cursor:pointer;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span class="cat-badge" style="background:{color}">{icon} {req.get('category','—')}</span>
                <span style="font-size:0.72rem; color:{sc}; font-weight:600;">{si} {status.title()}</span>
            </div>
            <div style="font-size:0.82rem; color:#222; margin:4px 0 2px; font-weight:{'600' if is_sel else '400'}">
                {req.get('service_description','')[:65]}{'…' if len(req.get('service_description',''))>65 else ''}
            </div>
            <div style="font-size:0.72rem; color:#666;">
                📍 {req.get('location','—')} · 💰 {req.get('budget','—')} · 👤 {req.get('buyer_name','—')}
            </div>
        </div>""", unsafe_allow_html=True)

        if st.button("Select →", key=f"sel_{rid[:8]}", use_container_width=True):
            st.session_state.me_selected_id = rid
            # Clear AI result so fresh analysis runs if auto_run is on
            st.session_state.me_ai_result.pop(rid, None)
            st.rerun()

# ═══════════════════════════════════════════════════════════════════════════════
# RIGHT: Match detail
# ═══════════════════════════════════════════════════════════════════════════════
with col_detail:
    sel_id  = st.session_state.me_selected_id
    sel_req = next((r for r in all_requests if r["id"] == sel_id), None)

    if not sel_req:
        st.info("Select a request on the left to see matches.")
        st.stop()

    color  = CATEGORY_COLORS.get(sel_req.get("category",""), "#607D8B")
    icon   = CATEGORY_ICONS.get(sel_req.get("category",""), "⭐")
    status = sel_req.get("status","open").lower()

    # ── Request card ──────────────────────────────────────────────────────────
    st.markdown(f"""
    <div style="background:{color}0d; border:2px solid {color}55; border-radius:12px;
                padding:1rem 1.2rem; margin-bottom:1rem;">
        <span class="cat-badge" style="background:{color}">{icon} {sel_req.get('category','—')}</span>
        <h3 style="color:#1a5276; margin:0.3rem 0 0.2rem;">
            {sel_req.get('service_description','')[:100]}{'…' if len(sel_req.get('service_description',''))>100 else ''}
        </h3>
        <div style="font-size:0.82rem; color:#555; margin-bottom:0.3rem;">
            👤 {sel_req.get('buyer_name','—')} &nbsp;·&nbsp;
            📞 {sel_req.get('buyer_contact','—')} &nbsp;·&nbsp;
            📍 {sel_req.get('location','—')}
        </div>
        <div style="font-size:0.82rem; color:#555;">
            💰 {sel_req.get('budget','—')} &nbsp;·&nbsp;
            📅 {sel_req.get('preferred_date','—')} &nbsp;·&nbsp;
            ⚡ {sel_req.get('urgency','—')}
        </div>
        {'<div style="font-size:0.8rem;color:#444;margin-top:0.4rem;border-top:1px solid #ccc;padding-top:0.4rem;">📝 ' + sel_req.get('special_notes','') + '</div>' if sel_req.get('special_notes') else ''}
    </div>""", unsafe_allow_html=True)

    # ── Status update ─────────────────────────────────────────────────────────
    sa, sb = st.columns([3, 1])
    with sa:
        new_status = st.selectbox(
            "Request status",
            ["open", "in progress", "matched", "closed"],
            index=["open","in progress","matched","closed"].index(status) if status in ["open","in progress","matched","closed"] else 0,
            key="me_status_sel",
            label_visibility="collapsed",
        )
    with sb:
        if st.button("Update Status", use_container_width=True):
            update_request_status(sel_id, new_status)
            st.toast(f"Status updated → {new_status}", icon="✅")
            st.rerun()

    st.divider()

    # ── Scored matches ────────────────────────────────────────────────────────
    matches = get_top_matches(sel_req, all_sellers, top_n=5)

    if not matches:
        st.warning(
            f"No active sellers found in the **{sel_req.get('category','—')}** category. "
            "Consider adding providers via the Admin Dashboard or Register Service page."
        )
    else:
        st.markdown(f"**Top {len(matches)} Match{'es' if len(matches)!=1 else ''} — {sel_req.get('category','—')}**")

        for rank, (score, seller) in enumerate(matches, start=1):
            sel_color = CATEGORY_COLORS.get(seller["category"], "#607D8B")
            loc_match = seller["area"] == sel_req.get("location","")
            loc_badge = (
                "✅ Exact location" if loc_match
                else ("🌐 Online/Remote" if seller["area"] == "Online / Remote"
                      else f"📍 {seller['area']}")
            )

            # Score bar colour
            bar_color = "#27ae60" if score >= 70 else ("#e67e22" if score >= 40 else "#e74c3c")
            bar_pct   = score

            medal = {1: "🥇", 2: "🥈", 3: "🥉"}.get(rank, f"#{rank}")

            with st.container():
                st.markdown(f"""
                <div class="seller-card">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                        <div>
                            <span style="font-size:1.1rem">{medal}</span>
                            <strong style="color:#1a5276; font-size:0.95rem;"> {seller['service_title']}</strong>
                        </div>
                        <div style="text-align:right; min-width:80px;">
                            <div style="font-size:1.3rem; font-weight:800; color:{bar_color};">{score}</div>
                            <div style="font-size:0.65rem; color:#888;">match score</div>
                        </div>
                    </div>
                    <div style="background:#eee; border-radius:4px; height:6px; margin:6px 0;">
                        <div style="width:{bar_pct}%; background:{bar_color}; height:100%; border-radius:4px;"></div>
                    </div>
                    <div class="meta">👤 {seller['name']} &nbsp;|&nbsp; 🏅 {seller.get('experience','—')}</div>
                    <div class="meta">{loc_badge} &nbsp;|&nbsp; 💰 {seller['price_range']} &nbsp;|&nbsp; 🕐 {seller['availability']}</div>
                    <div class="desc">{seller['description'][:130]}{'…' if len(seller['description'])>130 else ''}</div>
                </div>""", unsafe_allow_html=True)

        st.divider()

        # ── AI Analysis ───────────────────────────────────────────────────────
        st.markdown("**🤖 AI Match Analysis**")

        cached_ai = st.session_state.me_ai_result.get(sel_id)
        run_ai    = auto_run and not cached_ai

        if not cached_ai:
            if st.button("✨ Run AI Analysis", type="primary", key="run_ai") or run_ai:
                @st.cache_resource
                def get_client():
                    return anthropic.Anthropic(api_key=st.secrets["ANTHROPIC_API_KEY"])

                client = get_client()

                seller_context = "\n".join([
                    f"{i+1}. {s['name']} — {s['service_title']} | {s['area']} | "
                    f"{s['price_range']} | {s['availability']} | {s.get('experience','—')} | "
                    f"Score: {sc}"
                    for i, (sc, s) in enumerate(matches)
                ])

                prompt = (
                    f"A buyer submitted this request on Koponix:\n"
                    f"Category: {sel_req.get('category')}\n"
                    f"Need: {sel_req.get('service_description')}\n"
                    f"Location: {sel_req.get('location')}\n"
                    f"Budget: {sel_req.get('budget')}\n"
                    f"Date: {sel_req.get('preferred_date')} ({sel_req.get('urgency')})\n"
                    f"Special notes: {sel_req.get('special_notes') or 'None'}\n\n"
                    f"Top matched providers (by algorithm score):\n{seller_context}\n\n"
                    "As Koponix AI, give a concise analysis:\n"
                    "1. Recommend the **best 1–2 providers** for this request and explain why.\n"
                    "2. Note any concerns (budget mismatch, location, availability).\n"
                    "3. Suggest the next step for the koperasi admin to facilitate this match.\n\n"
                    "Keep it practical, under 200 words."
                )

                with st.spinner("Analysing matches…"):
                    response = client.messages.create(
                        model="claude-opus-4-6",
                        max_tokens=512,
                        system=KOPONIX_SYSTEM_PROMPT,
                        messages=[{"role": "user", "content": prompt}],
                    )
                    ai_text = response.content[0].text

                st.session_state.me_ai_result[sel_id] = ai_text
                st.rerun()
        else:
            st.markdown(cached_ai)
            cc1, cc2 = st.columns(2)
            with cc1:
                if st.button("🔄 Re-run Analysis", key="rerun_ai"):
                    st.session_state.me_ai_result.pop(sel_id, None)
                    st.rerun()
            with cc2:
                if st.button("✅ Mark as Matched", key="mark_matched", type="primary"):
                    update_request_status(sel_id, "matched")
                    st.toast("Request marked as Matched!", icon="✅")
                    st.session_state.me_ai_result.pop(sel_id, None)
                    st.rerun()
