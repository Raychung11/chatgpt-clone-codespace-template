"""Koponix — Find Services / Seller Listings page."""
import streamlit as st
from utils import (
    apply_koponix_style, sidebar_logo, load_sellers,
    CATEGORIES, CATEGORY_ICONS, CATEGORY_COLORS, LOCATIONS,
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
    st.divider()

    st.markdown("**Filter Services**")

    selected_categories = st.multiselect(
        "Category",
        options=CATEGORIES,
        default=[],
        placeholder="All categories",
    )

    selected_locations = st.multiselect(
        "Location",
        options=LOCATIONS,
        default=[],
        placeholder="All locations",
    )

    search_query = st.text_input("Search keyword", placeholder="e.g. cleaning, tutor…")

    st.divider()
    if st.button("🛒 Submit a Service Request", use_container_width=True, type="primary"):
        st.switch_page("pages/4_Request_Service.py")
    if st.button("💼 List Your Service", use_container_width=True):
        st.switch_page("pages/3_Register_Service.py")

# ── Page header ───────────────────────────────────────────────────────────────
st.markdown('<div class="section-head">🔍 Find Services</div>', unsafe_allow_html=True)
st.caption("Browse services offered by verified koperasi members.")

# ── Load and filter data ──────────────────────────────────────────────────────
sellers = load_sellers()
active = [s for s in sellers if s.get("status") == "active"]

filtered = active
if selected_categories:
    filtered = [s for s in filtered if s["category"] in selected_categories]
if selected_locations:
    filtered = [s for s in filtered if s["area"] in selected_locations]
if search_query:
    q = search_query.lower()
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
    unsafe_allow_html=False,
)

if not filtered:
    st.info("No services match your filter. Try adjusting the category or location, or clear the search.")
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
        icon = CATEGORY_ICONS.get(seller["category"], "⭐")
        with cols[i % 2]:
            st.markdown(f"""
            <div class="seller-card">
                <span class="cat-badge" style="background:{color}">
                    {icon} {seller['category']}
                </span>
                <h4>{seller['service_title']}</h4>
                <div class="meta">👤 <strong>{seller['name']}</strong>
                    &nbsp;|&nbsp; 🏅 {seller.get('experience','N/A')} experience</div>
                <div class="meta">📍 {seller['area']}
                    &nbsp;|&nbsp; 💰 {seller['price_range']}</div>
                <div class="meta">🕐 {seller['availability']}</div>
                <div class="desc">{seller['description']}</div>
            </div>""", unsafe_allow_html=True)

            with st.expander("📩 Contact / Request this service"):
                st.markdown(
                    f"**{seller['name']}** — *{seller['service_title']}*\n\n"
                    f"📍 {seller['area']}  |  💰 {seller['price_range']}\n\n"
                    f"To engage this provider, submit a formal service request below. "
                    f"Our team will facilitate the match."
                )
                if st.button("Submit Request →", key=f"req_{seller['id']}"):
                    st.switch_page("pages/4_Request_Service.py")

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
    st.info("**Don't see what you need?**  Submit a buyer request and let Koponix AI help match you with the right provider.")
    if st.button("🛒 Submit a Service Request", key="cta_req"):
        st.switch_page("pages/4_Request_Service.py")
with cta2:
    st.info("**Are you a koperasi member with skills to offer?**  List your service and start earning today.")
    if st.button("💼 Register Your Service", key="cta_reg"):
        st.switch_page("pages/3_Register_Service.py")
