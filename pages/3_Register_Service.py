"""Koponix — Register Service (Seller Onboarding) page."""
import anthropic
import streamlit as st
from utils import (
    apply_koponix_style, sidebar_logo, save_seller,
    CATEGORIES, LOCATIONS, KOPONIX_SYSTEM_PROMPT,
)

st.set_page_config(
    page_title="Register Your Service — Koponix",
    page_icon="💼",
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
    st.info(
        "**Need help?**\n\n"
        "Use the AI Assistant to get guidance on how to describe your service, "
        "set the right price, or write a strong listing."
    )
    if st.button("💬 Ask AI for help", use_container_width=True):
        st.switch_page("pages/1_AI_Assistant.py")

# ── Page header ───────────────────────────────────────────────────────────────
st.markdown('<div class="section-head">💼 Register Your Service</div>', unsafe_allow_html=True)
st.caption(
    "List your skills on Koponix and connect with buyers within the koperasi community. "
    "All fields marked * are required."
)

# ── AI description helper ──────────────────────────────────────────────────────
@st.cache_resource
def get_client():
    return anthropic.Anthropic(api_key=st.secrets["ANTHROPIC_API_KEY"])

# ── Registration form ─────────────────────────────────────────────────────────
with st.form("seller_registration", clear_on_submit=False):
    st.markdown("#### 👤 Personal & Member Details")
    c1, c2 = st.columns(2)
    with c1:
        name = st.text_input("Full Name *", placeholder="e.g. Ahmad Rizal bin Hassan")
    with c2:
        koperasi_id = st.text_input("Koperasi Member ID *", placeholder="e.g. KOP-2024-001")

    st.markdown("#### 🛠️ Service Details")
    c3, c4 = st.columns(2)
    with c3:
        category = st.selectbox("Service Category *", options=CATEGORIES)
    with c4:
        experience = st.selectbox(
            "Years of Experience *",
            options=["Less than 1 year", "1–2 years", "3–5 years", "5–10 years", "10+ years"]
        )

    service_title = st.text_input(
        "Service Title *",
        placeholder="e.g. Professional Home Cleaning, SPM Math Tutor, Logo Design"
    )

    description = st.text_area(
        "Service Description *",
        height=130,
        placeholder=(
            "Describe what you offer, what is included, and what is not included.\n"
            "Keep it clear and honest. Avoid exaggerated claims."
        )
    )

    st.markdown("#### 📍 Location & Availability")
    c5, c6 = st.columns(2)
    with c5:
        area = st.selectbox("Service Area *", options=LOCATIONS)
    with c6:
        availability = st.text_input(
            "Availability *",
            placeholder="e.g. Weekends, Mon–Fri evenings, Flexible"
        )

    price_range = st.text_input(
        "Price Range *",
        placeholder="e.g. RM 80 – RM 120 per session, RM 50/hour, RM 300–500/project"
    )

    contact = st.text_input(
        "Contact Method (optional)",
        placeholder="e.g. WhatsApp 012-XXXXXXX — leave blank to show 'available on request'"
    )

    st.divider()
    submitted = st.form_submit_button("✅ Submit Service Listing", type="primary", use_container_width=True)

# ── Handle submission ─────────────────────────────────────────────────────────
if submitted:
    required = {
        "Full Name": name,
        "Koperasi Member ID": koperasi_id,
        "Service Title": service_title,
        "Service Description": description,
        "Availability": availability,
        "Price Range": price_range,
    }
    missing = [k for k, v in required.items() if not v.strip()]

    if missing:
        st.error(f"Please fill in the following required fields: {', '.join(missing)}")
    else:
        seller_data = {
            "name": name.strip(),
            "koperasi_id": koperasi_id.strip(),
            "category": category,
            "service_title": service_title.strip(),
            "description": description.strip(),
            "area": area,
            "availability": availability.strip(),
            "price_range": price_range.strip(),
            "experience": experience,
            "contact": contact.strip() if contact.strip() else "WhatsApp available upon request",
        }
        new_id = save_seller(seller_data)

        st.success("🎉 Your service has been registered successfully!")
        st.markdown(f"""
        ---
        **Seller Profile Summary**

        | Field | Details |
        |---|---|
        | **Name** | {name} |
        | **Member ID** | {koperasi_id} |
        | **Category** | {category} |
        | **Service Title** | {service_title} |
        | **Area** | {area} |
        | **Price** | {price_range} |
        | **Availability** | {availability} |
        | **Reference ID** | `{new_id[:8]}` |
        """)

        st.info(
            "Your listing is now visible in **Find Services**. "
            "Buyers can discover and request your service through the platform."
        )
        c_a, c_b = st.columns(2)
        with c_a:
            if st.button("🔍 View All Listings"):
                st.switch_page("pages/2_Find_Services.py")
        with c_b:
            if st.button("💬 Ask AI for promotion tips"):
                st.session_state.pending_prompt = (
                    f"I just registered my service on Koponix: '{service_title}' in the "
                    f"{category} category, covering {area}, priced at {price_range}. "
                    "Can you help me write a short WhatsApp and Facebook post to promote it?"
                )
                st.switch_page("pages/1_AI_Assistant.py")

# ── AI Price Advisor ──────────────────────────────────────────────────────────
st.divider()
st.markdown("#### 💰 AI Price Advisor")
st.caption("Not sure what to charge? Let AI suggest a fair market rate based on your category, location, and experience.")

with st.expander("✨ Get AI pricing suggestion"):
    p_category = st.selectbox("Service category", CATEGORIES, key="price_cat")
    p_area     = st.selectbox("Service area", LOCATIONS, key="price_area")
    p_exp      = st.selectbox("Years of experience",
                               ["Less than 1 year","1–2 years","3–5 years","5–10 years","10+ years"],
                               key="price_exp")
    p_title    = st.text_input("Service title (optional)", key="price_title",
                                placeholder="e.g. Home Cleaning, Logo Design")

    if st.button("💰 Suggest Price Range", key="get_price", type="primary"):
        price_prompt = (
            f"I am a koperasi member in Malaysia offering a service on Koponix.\n"
            f"Category: {p_category}\n"
            f"Service: {p_title if p_title.strip() else 'not specified'}\n"
            f"Location: {p_area}\n"
            f"Experience: {p_exp}\n\n"
            "Suggest a realistic and competitive price range for this service in the Malaysian market. "
            "Consider typical freelancer/gig rates for koperasi members.\n\n"
            "Reply in this exact format:\n"
            "**Suggested Price Range:** [range with unit, e.g. RM 80–120 per session]\n"
            "**Rationale:** [1–2 sentences explaining why]\n"
            "**Tip:** [one practical pricing tip for this category]"
        )
        with st.spinner("Checking market rates…"):
            r = get_client().messages.create(
                model="claude-opus-4-6",
                max_tokens=250,
                system=KOPONIX_SYSTEM_PROMPT,
                messages=[{"role": "user", "content": price_prompt}],
            )
        st.success(r.content[0].text)
        st.caption("Use this as a guide — adjust based on your actual costs and target market.")

# ── AI description helper (below form) ────────────────────────────────────────
st.divider()
st.markdown("#### 💡 AI Description Helper")
st.caption(
    "Not sure how to describe your service? "
    "Fill in the fields below and let Koponix AI draft a description for you."
)

with st.expander("✍️ Generate a service description with AI"):
    h_category = st.selectbox("Your service category", CATEGORIES, key="helper_cat")
    h_title = st.text_input("Your service title", key="helper_title",
                             placeholder="e.g. Home Cleaning, Logo Design, SPM Tutor")
    h_details = st.text_area(
        "Brief notes (what you do, your experience, target customers)",
        height=80,
        key="helper_notes",
        placeholder="e.g. 3 years experience, bring own tools, serve KL area, RM80/session"
    )
    if st.button("✨ Generate Description", key="gen_desc"):
        if not h_title.strip():
            st.warning("Please enter your service title first.")
        else:
            client = get_client()
            prompt = (
                f"I am a koperasi member registering on Koponix. "
                f"Category: {h_category}. Service title: {h_title}. "
                f"Details: {h_details if h_details.strip() else 'not provided'}.\n\n"
                "Please write a short, honest, clear service description (max 80 words) "
                "that I can use on the Koponix platform. State what is included and what is not. "
                "Keep it professional and suitable for Malaysian buyers."
            )
            with st.spinner("Generating description…"):
                response = client.messages.create(
                    model="claude-opus-4-6",
                    max_tokens=256,
                    system=KOPONIX_SYSTEM_PROMPT,
                    messages=[{"role": "user", "content": prompt}],
                )
                generated = response.content[0].text
            st.success("Here is a suggested description:")
            st.markdown(f"> {generated}")
            st.caption("Copy this into the Service Description field above.")
