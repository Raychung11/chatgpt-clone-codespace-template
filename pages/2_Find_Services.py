"""Koponix — Find Services / Seller Listings page."""
import json

import anthropic
import streamlit as st

from utils import (
    CATEGORIES,
    CATEGORY_COLORS,
    CATEGORY_ICONS,
    KOPONIX_SYSTEM_PROMPT,
    LOCATIONS,
    apply_koponix_style,
    load_sellers,
    sidebar_logo,
    sidebar_member_status,
)

st.set_page_config(
    page_title="Find Services — Koponix",
    page_icon="🔍",
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

    st.markdown("**Manual Filters**")
    selected_categories = st.multiselect(
        "Category",
        options=CATEGORIES,
        default=st.session_state.get("ai_filter_categories", []),
        placeholder="All categories",
        key="cat_filter",
    )
    selected_locations = st.multiselect(
        "Location",
        options=LOCATIONS,
        default=st.session_state.get("ai_filter_locations", []),
        placeholder="All locations",
        key="loc_filter",
    )
    search_query = st.text_input("Search keyword", placeholder="e.g. cleaning, tutor…")
    if st.button("🔄 Clear all filters", use_container_width=True):
        for key in ["ai_filter_categories", "ai_filter_locations", "ai_search_used", "ai_search_label"]:
            st.session_state.pop(key, None)
        st.rerun()
    st.divider()
    if st.button("🛒 Submit a Service Request", use_container_width=True, type="primary"):
        st.switch_page("pages/4_Request_Service.py")
    if st.button("💼 List Your Service", use_container_width=True):
        st.switch_page("pages/3_Register_Service.py")
    sidebar_member_status()

# ── Page header ───────────────────────────────────────────────────────────────
st.markdown('<div class="section-head">🔍 Find Services</div>', unsafe_allow_html=True)
st.caption("Browse services offered by verified koperasi members.")

# ── AI Smart Search ───────────────────────────────────────────────────────────
@st.cache_resource
def get_client():
    return anthropic.Anthropic(api_key=st.secrets["ANTHROPIC_API_KEY"])

with st.container():
    st.markdown("""
    <div style="background:linear-gradient(135deg,#1a5276,#2e86c1); border-radius:12px;
                padding:1rem 1.2rem 0.8rem; margin-bottom:1rem;">
        <div style="color:white; font-weight:700; font-size:0.95rem; margin-bottom:4px;">
            ✨ AI Smart Search
        </div>
        <div style="color:rgba(255,255,255,0.85); font-size:0.78rem;">
            Describe what you need in plain language — AI will find the right services for you.
        </div>
    </div>""", unsafe_allow_html=True)

    ai_col, btn_col = st.columns([5, 1])
    with ai_col:
        ai_query = st.text_input(
            "ai_search_input",
            placeholder='e.g. "I need someone to teach my kid Maths in PJ" or "looking for logo design online"',
            label_visibility="collapsed",
            key="ai_search_input",
        )
    with btn_col:
        ai_search_btn = st.button("🔍 Search", use_container_width=True, type="primary", key="ai_btn")

    if ai_search_btn and ai_query.strip():
        cat_list = ", ".join(CATEGORIES)
        loc_list = ", ".join(LOCATIONS)
        extract_prompt = (
            f"A user is searching for a service on Koponix with this description:\n\"{ai_query}\"\n\n"
            f"Available categories: {cat_list}\n"
            f"Available locations: {loc_list}\n\n"
            "Extract the user's intent and return a JSON object with exactly these keys:\n"
            '{"categories": [...], "locations": [...], "keywords": "..."}\n'
            "- categories: list of matching category names from the available list (0–3 items)\n"
            "- locations: list of matching location names from the available list (0–2 items)\n"
            "- keywords: short keyword string for text search (max 4 words, empty string if none)\n"
            "Return ONLY the raw JSON object — no explanation, no markdown fences."
        )
        with st.spinner("AI is interpreting your search…"):
            resp = get_client().messages.create(
                model="claude-opus-4-6",
                max_tokens=200,
                system=KOPONIX_SYSTEM_PROMPT,
                messages=[{"role": "user", "content": extract_prompt}],
            )
            raw = resp.content[0].text.strip()
            try:
                extracted = json.loads(raw)
                st.session_state["ai_filter_categories"] = extracted.get("categories", [])
                st.session_state["ai_filter_locations"]  = extracted.get("locations", [])
                st.session_state["ai_search_used"]       = ai_query
                st.session_state["ai_search_label"]      = extracted.get("keywords", "")
            except Exception:
                st.warning("Could not parse AI response — try rephrasing or use manual filters.")
        st.rerun()

    # Show AI filter badge if active
    if st.session_state.get("ai_search_used"):
        applied_cats = st.session_state.get("ai_filter_categories", [])
        applied_locs = st.session_state.get("ai_filter_locations", [])
        parts = []
        if applied_cats: parts.append(f"Category: {', '.join(applied_cats)}")
        if applied_locs: parts.append(f"Location: {', '.join(applied_locs)}")
        if st.session_state.get("ai_search_label"): parts.append(f"Keywords: {st.session_state['ai_search_label']}")
        st.success(f"🎯 AI filter active — {' · '.join(parts) if parts else 'showing all'}")

# ── Merge AI filters with manual sidebar filters ───────────────────────────────
ai_cats = st.session_state.get("ai_filter_categories", [])
ai_locs = st.session_state.get("ai_filter_locations", [])
ai_kw   = st.session_state.get("ai_search_label", "")

effective_cats = list(set(selected_categories) | set(ai_cats))
effective_locs = list(set(selected_locations)  | set(ai_locs))
effective_kw   = " ".join(filter(None, [search_query.strip(), ai_kw]))

# ── Load and filter data ──────────────────────────────────────────────────────
sellers = load_sellers()
active  = [s for s in sellers if s.get("status") == "active"]

filtered = active
if effective_cats:
    filtered = [s for s in filtered if s["category"] in effective_cats]
if effective_locs:
    filtered = [s for s in filtered if s["area"] in effective_locs]
if effective_kw:
    q = effective_kw.lower()
    filtered = [
        s for s in filtered
        if q in s["service_title"].lower()
        or q in s["description"].lower()
        or q in s["category"].lower()
        or q in s["area"].lower()
    ]

# ── Result count ──────────────────────────────────────────────────────────────
total = len(filtered)
st.markdown(
    f"**{total} service{'s' if total != 1 else ''} found**"
    + (f" · filtered from {len(active)} total" if total < len(active) else ""),
)

if not filtered:
    st.info("No services match your search. Try rephrasing, adjusting filters, or clearing them.")
    st.stop()

st.markdown("<br>", unsafe_allow_html=True)

# ── Category tabs ─────────────────────────────────────────────────────────────
cats_in_results = sorted(set(s["category"] for s in filtered))
if len(cats_in_results) > 1:
    tab_labels = ["All"] + cats_in_results
    tabs = st.tabs(tab_labels)
    tab_map = {label: tab for label, tab in zip(tab_labels, tabs)}
else:
    tab_map = None


def render_seller_cards(seller_list):
    """Render sellers in a 2-column grid."""
    cols = st.columns(2)
    for i, seller in enumerate(seller_list):
        color = CATEGORY_COLORS.get(seller["category"], "#607D8B")
        icon  = CATEGORY_ICONS.get(seller["category"], "⭐")
        with cols[i % 2]:
            # AI insight badge (cached per seller)
            insight_key = f"insight_{seller['id']}"
            insight_html = ""
            if st.session_state.get(insight_key):
                insight_text = st.session_state[insight_key]
                insight_html = (
                    f'<div style="background:#eaf4fb;border-left:3px solid #2e86c1;border-radius:0 6px 6px 0;'
                    f'padding:4px 8px;margin-bottom:6px;font-size:0.75rem;color:#1a5276;">'
                    f'✨ <em>{insight_text}</em></div>'
                )

            st.markdown(f"""
            <div class="seller-card">
                <span class="cat-badge" style="background:{color}">
                    {icon} {seller['category']}
                </span>
                {insight_html}
                <h4>{seller['service_title']}</h4>
                <div class="meta">👤 <strong>{seller['name']}</strong>
                    &nbsp;|&nbsp; 🏅 {seller.get('experience','N/A')} experience</div>
                <div class="meta">📍 {seller['area']}
                    &nbsp;|&nbsp; 💰 {seller['price_range']}</div>
                <div class="meta">🕐 {seller['availability']}</div>
                <div class="desc">{seller['description']}</div>
            </div>""", unsafe_allow_html=True)

            exp_col, insight_col = st.columns([2, 1])
            with exp_col:
                with st.expander("📩 Contact / Request this service"):
                    st.markdown(
                        f"**{seller['name']}** — *{seller['service_title']}*\n\n"
                        f"📍 {seller['area']}  |  💰 {seller['price_range']}\n\n"
                        "To engage this provider, submit a formal service request. "
                        "Our team will facilitate the match."
                    )
                    if st.button("Submit Request →", key=f"req_{seller['id']}"):
                        st.switch_page("pages/4_Request_Service.py")
            with insight_col:
                if not st.session_state.get(insight_key):
                    if st.button("✨ AI insight", key=f"ai_{seller['id']}", use_container_width=True):
                        prompt = (
                            f"In one sentence (max 18 words), write a 'Best for:' tagline for this service provider:\n"
                            f"Service: {seller['service_title']}\n"
                            f"Category: {seller['category']}\n"
                            f"Area: {seller['area']}\n"
                            f"Price: {seller['price_range']}\n"
                            f"Description: {seller['description'][:200]}\n\n"
                            "Start with 'Best for' and be specific. No quotes."
                        )
                        with st.spinner(""):
                            r = get_client().messages.create(
                                model="claude-opus-4-6",
                                max_tokens=60,
                                system=KOPONIX_SYSTEM_PROMPT,
                                messages=[{"role": "user", "content": prompt}],
                            )
                        st.session_state[insight_key] = r.content[0].text.strip()
                        st.rerun()
                else:
                    if st.button("✕ clear", key=f"clr_{seller['id']}", use_container_width=True):
                        st.session_state.pop(insight_key, None)
                        st.rerun()


if tab_map:
    with tab_map["All"]:
        render_seller_cards(filtered)
    for cat in cats_in_results:
        with tab_map[cat]:
            render_seller_cards([s for s in filtered if s["category"] == cat])
else:
    render_seller_cards(filtered)

# ── CTA footer ────────────────────────────────────────────────────────────────
st.divider()
cta1, cta2 = st.columns(2)
with cta1:
    st.info("**Don't see what you need?**  Submit a buyer request and let Koponix AI match you.")
    if st.button("🛒 Submit a Service Request", key="cta_req"):
        st.switch_page("pages/4_Request_Service.py")
with cta2:
    st.info("**Are you a koperasi member with skills to offer?**  List your service and start earning.")
    if st.button("💼 Register Your Service", key="cta_reg"):
        st.switch_page("pages/3_Register_Service.py")
