"""Koponix — WhatsApp & Social Media Promo Generator."""
import anthropic
import streamlit as st

from utils import (
    CATEGORY_ICONS,
    KOPONIX_SYSTEM_PROMPT,
    apply_koponix_style,
    load_sellers,
    sidebar_logo,
)

st.set_page_config(
    page_title="Promo Generator — Koponix",
    page_icon="📣",
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
    st.caption("Generate ready-to-share promotional content for WhatsApp, Facebook, and Instagram.")

# ── Page header ───────────────────────────────────────────────────────────────
st.markdown('<div class="section-head">📣 Promo Generator</div>', unsafe_allow_html=True)
st.caption("Generate ready-to-share promotional posts for your Koponix service listing.")

# ── Load sellers ──────────────────────────────────────────────────────────────
all_sellers   = load_sellers()
active_sellers = [s for s in all_sellers if s.get("status") == "active"]

# ── Session state ─────────────────────────────────────────────────────────────
if "promo_results" not in st.session_state:
    st.session_state.promo_results = {}   # {hash_key: {platform: text}}

# ── Layout ────────────────────────────────────────────────────────────────────
col_form, col_output = st.columns([1, 1], gap="large")

# ═══════════════════════════════════════════════════════════════════════════════
# LEFT: Input form
# ═══════════════════════════════════════════════════════════════════════════════
with col_form:
    st.markdown("#### 1️⃣  Choose your listing")

    source = st.radio(
        "Use listing from",
        ["Registered seller", "Enter details manually"],
        horizontal=True,
        label_visibility="collapsed",
    )

    seller_data = {}

    if source == "Registered seller":
        if not active_sellers:
            st.warning("No active sellers found. Register a service first.")
            st.stop()

        seller_options = {
            f"{CATEGORY_ICONS.get(s['category'],'⭐')} {s['name']} — {s['service_title']}": s
            for s in active_sellers
        }
        selected_label = st.selectbox("Select seller", list(seller_options.keys()))
        seller_data = seller_options[selected_label]

        # Preview card
        st.markdown(f"""
        <div style="background:#f0f4f8; border-radius:10px; padding:0.8rem 1rem;
                    font-size:0.82rem; color:#333; margin-top:0.4rem;">
            <strong>{seller_data['service_title']}</strong><br>
            📍 {seller_data['area']} &nbsp;·&nbsp; 💰 {seller_data['price_range']}<br>
            🕐 {seller_data['availability']}<br>
            <span style="color:#666;">{seller_data['description'][:100]}…</span>
        </div>""", unsafe_allow_html=True)
    else:
        c1, c2 = st.columns(2)
        with c1:
            m_name  = st.text_input("Your name", placeholder="Ahmad Rizal")
            m_title = st.text_input("Service title", placeholder="Professional Home Cleaning")
            m_area  = st.text_input("Area / location", placeholder="Kuala Lumpur")
        with c2:
            m_price = st.text_input("Price range", placeholder="RM 80–120 per session")
            m_avail = st.text_input("Availability", placeholder="Weekends, evenings")
            m_contact = st.text_input("Contact", placeholder="012-XXXXXXX")
        m_desc = st.text_area("Service description", height=80,
                               placeholder="What do you offer? What's included?")
        seller_data = {
            "name": m_name, "service_title": m_title, "area": m_area,
            "price_range": m_price, "availability": m_avail,
            "contact": m_contact, "description": m_desc, "category": "Other",
        }

    st.markdown("#### 2️⃣  Platforms & options")

    platforms = st.multiselect(
        "Generate for",
        ["WhatsApp Broadcast", "Facebook Post", "Instagram Caption"],
        default=["WhatsApp Broadcast"],
    )

    tone = st.select_slider(
        "Tone",
        options=["Formal", "Professional", "Friendly", "Casual & Fun"],
        value="Professional",
    )

    language = st.radio(
        "Language",
        ["English", "Bahasa Malaysia", "Bilingual (EN + BM)"],
        horizontal=True,
    )

    special_offer = st.text_input(
        "Special offer or promo (optional)",
        placeholder="e.g. 10% off for first booking, Free consultation, Ramadan special",
    )

    include_cta = st.checkbox("Include call-to-action (contact / WhatsApp link)", value=True)
    include_hashtags = st.checkbox("Include hashtags", value=True)

    st.markdown("<br>", unsafe_allow_html=True)
    generate_btn = st.button(
        "✨ Generate Promo Content",
        type="primary",
        use_container_width=True,
        disabled=not platforms,
    )

# ═══════════════════════════════════════════════════════════════════════════════
# RIGHT: Output
# ═══════════════════════════════════════════════════════════════════════════════
with col_output:
    st.markdown("#### 3️⃣  Generated content")

    if not generate_btn and not st.session_state.promo_results:
        st.markdown("""
        <div style="background:#f8f9fa; border:1px dashed #ccc; border-radius:12px;
                    padding:2.5rem; text-align:center; color:#888;">
            <div style="font-size:2.5rem">📣</div>
            <p>Fill in the form on the left and click<br><strong>Generate Promo Content</strong></p>
        </div>""", unsafe_allow_html=True)

    if generate_btn:
        if not platforms:
            st.warning("Select at least one platform.")
        elif source == "Enter details manually" and not seller_data.get("service_title","").strip():
            st.warning("Please enter a service title.")
        else:
            @st.cache_resource
            def get_client():
                return anthropic.Anthropic(api_key=st.secrets["ANTHROPIC_API_KEY"])

            client = get_client()

            PLATFORM_GUIDES = {
                "WhatsApp Broadcast": (
                    "WhatsApp broadcast message (max 200 words). "
                    "Start with a strong opening line. Use emoji sparingly but effectively. "
                    "Short paragraphs. End with clear CTA: how to contact/book. "
                    "Format for easy reading in WhatsApp (line breaks between sections)."
                ),
                "Facebook Post": (
                    "Facebook post (max 150 words). "
                    "Engaging opening hook (question or bold statement). "
                    "2–3 short paragraphs. Benefits-focused. "
                    "End with CTA. Add 5–8 relevant hashtags at the bottom."
                ),
                "Instagram Caption": (
                    "Instagram caption (max 120 words). "
                    "Punchy first line (shown before 'more'). "
                    "Emoji throughout. Value-driven. "
                    "End with CTA. Add 10–15 hashtags in a block below a divider (• • •)."
                ),
            }

            promo_context = (
                f"Name: {seller_data.get('name','')}\n"
                f"Service: {seller_data.get('service_title','')}\n"
                f"Category: {seller_data.get('category','')}\n"
                f"Area: {seller_data.get('area','')}\n"
                f"Price: {seller_data.get('price_range','')}\n"
                f"Availability: {seller_data.get('availability','')}\n"
                f"Contact: {seller_data.get('contact','')}\n"
                f"Description: {seller_data.get('description','')}\n"
                f"Special offer: {special_offer if special_offer.strip() else 'None'}\n"
                f"Tone: {tone}\n"
                f"Language: {language}\n"
                f"Include CTA: {include_cta}\n"
                f"Include hashtags: {include_hashtags}"
            )

            results = {}
            progress = st.progress(0, text="Generating…")

            for i, platform in enumerate(platforms):
                guide = PLATFORM_GUIDES.get(platform, "")
                prompt = (
                    f"Generate a {platform} promotional post for this Koponix service provider:\n\n"
                    f"{promo_context}\n\n"
                    f"Platform requirements: {guide}\n\n"
                    f"{'Include a clear call-to-action with the contact details provided.' if include_cta else 'Do not include specific contact details.'}\n"
                    f"{'Add relevant hashtags as specified.' if include_hashtags else 'Do not add hashtags.'}\n\n"
                    f"Write ONLY the post content — no preamble, no explanation, no quotation marks around it."
                )

                response = client.messages.create(
                    model="claude-opus-4-6",
                    max_tokens=600,
                    system=KOPONIX_SYSTEM_PROMPT,
                    messages=[{"role": "user", "content": prompt}],
                )
                results[platform] = response.content[0].text
                progress.progress((i + 1) / len(platforms), text=f"Generated {platform}…")

            progress.empty()
            st.session_state.promo_results = results
            st.rerun()

    # ── Display results ────────────────────────────────────────────────────────
    if st.session_state.promo_results:
        PLATFORM_ICONS = {
            "WhatsApp Broadcast": "💬",
            "Facebook Post":      "📘",
            "Instagram Caption":  "📸",
        }

        for platform, content in st.session_state.promo_results.items():
            icon = PLATFORM_ICONS.get(platform, "📣")
            st.markdown(f"**{icon} {platform}**")

            # Editable text area for copy-paste
            edited = st.text_area(
                label=platform,
                value=content,
                height=220,
                key=f"ta_{platform}",
                label_visibility="collapsed",
            )

            tip_col, regen_col = st.columns([3, 1])
            with tip_col:
                char_count = len(edited)
                st.caption(f"{char_count} characters · Select all text → Copy")
            with regen_col:
                if st.button("🔄 Regenerate", key=f"regen_{platform}", use_container_width=True):
                    st.session_state.promo_results.pop(platform, None)
                    st.rerun()

            st.markdown("<br>", unsafe_allow_html=True)

        st.divider()
        if st.button("🗑️ Clear all & start over", use_container_width=False):
            st.session_state.promo_results = {}
            st.rerun()
