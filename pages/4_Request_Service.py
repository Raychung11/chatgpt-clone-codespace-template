"""Koponix — Request a Service (Buyer Request) page."""
import anthropic
import streamlit as st
from utils import (
    apply_koponix_style, sidebar_logo, save_request, load_sellers,
    CATEGORIES, LOCATIONS, KOPONIX_SYSTEM_PROMPT,
)

st.set_page_config(
    page_title="Request a Service — Koponix",
    page_icon="🛒",
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
    st.divider()
    st.info(
        "**Not sure what to request?**\n\n"
        "Use the AI Assistant to describe your need in plain language — it will help you "
        "clarify the scope and suggest the right category."
    )
    if st.button("💬 Get AI help", use_container_width=True):
        st.switch_page("pages/1_AI_Assistant.py")

# ── Page header ───────────────────────────────────────────────────────────────
st.markdown('<div class="section-head">🛒 Request a Service</div>', unsafe_allow_html=True)
st.caption(
    "Submit your service request and we will match you with the most suitable "
    "koperasi member-provider."
)

# ── Buyer request form ────────────────────────────────────────────────────────
with st.form("buyer_request", clear_on_submit=False):
    st.markdown("#### 📋 What do you need?")

    c1, c2 = st.columns(2)
    with c1:
        category = st.selectbox("Service Category *", options=CATEGORIES)
    with c2:
        location = st.selectbox("Location *", options=LOCATIONS)

    service_description = st.text_area(
        "Describe what you need *",
        height=100,
        placeholder=(
            "e.g. I need my 3-bedroom apartment cleaned — living room, 2 bathrooms, kitchen. "
            "About 1,000 sq ft. No heavy furniture moving needed."
        )
    )

    st.markdown("#### 📅 Timing & Budget")
    c3, c4 = st.columns(2)
    with c3:
        preferred_date = st.date_input("Preferred Date *")
    with c4:
        urgency = st.selectbox(
            "Urgency",
            ["Flexible — within 1 week", "This week", "Within 2–3 days", "As soon as possible"]
        )

    budget = st.text_input(
        "Budget Range *",
        placeholder="e.g. RM 80–120, RM 50/hour, Up to RM 500"
    )

    st.markdown("#### 👤 Your Contact Details")
    c5, c6 = st.columns(2)
    with c5:
        buyer_name = st.text_input("Your Name *", placeholder="Full name")
    with c6:
        buyer_contact = st.text_input("WhatsApp / Contact *", placeholder="e.g. 012-XXXXXXX")

    special_notes = st.text_area(
        "Special Requirements (optional)",
        height=70,
        placeholder="Any specific requirements, allergies, access instructions, etc."
    )

    st.divider()
    submitted = st.form_submit_button("📨 Submit Request", type="primary", use_container_width=True)

# ── Handle submission ─────────────────────────────────────────────────────────
if submitted:
    required = {
        "Service Description": service_description,
        "Budget": budget,
        "Your Name": buyer_name,
        "Contact": buyer_contact,
    }
    missing = [k for k, v in required.items() if not v.strip()]

    if missing:
        st.error(f"Please fill in: {', '.join(missing)}")
    else:
        request_data = {
            "buyer_name": buyer_name.strip(),
            "buyer_contact": buyer_contact.strip(),
            "category": category,
            "location": location,
            "service_description": service_description.strip(),
            "preferred_date": str(preferred_date),
            "urgency": urgency,
            "budget": budget.strip(),
            "special_notes": special_notes.strip(),
        }
        req_id = save_request(request_data)

        st.success("✅ Your service request has been submitted!")

        # ── Request Summary ────────────────────────────────────────────────────
        st.markdown("---")
        st.markdown("**Request Summary**")
        st.markdown(f"""
| Field | Details |
|---|---|
| **Service Needed** | {category} |
| **Location** | {location} |
| **Preferred Date** | {preferred_date} |
| **Urgency** | {urgency} |
| **Budget** | {budget} |
| **Scope** | {service_description} |
| **Notes** | {special_notes if special_notes.strip() else '—'} |
| **Reference ID** | `{req_id[:8]}` |
""")

        # ── AI Matching Suggestion ─────────────────────────────────────────────
        st.markdown("---")
        st.markdown("#### 🤖 AI Matching Suggestion")

        sellers = load_sellers()
        matching = [
            s for s in sellers
            if s.get("status") == "active" and s["category"] == category
        ]
        location_match = [s for s in matching if s["area"] == location or s["area"] == "Online / Remote"]

        @st.cache_resource
        def get_client():
            return anthropic.Anthropic(api_key=st.secrets["ANTHROPIC_API_KEY"])

        client = get_client()

        if matching:
            seller_context = "\n".join([
                f"- {s['name']} | {s['service_title']} | {s['area']} | {s['price_range']} | {s['availability']}"
                for s in matching[:6]
            ])
            match_prompt = (
                f"A buyer submitted this service request:\n"
                f"Category: {category}\n"
                f"Location: {location}\n"
                f"Need: {service_description}\n"
                f"Budget: {budget}\n"
                f"Date: {preferred_date} ({urgency})\n\n"
                f"Available providers in this category:\n{seller_context}\n\n"
                "Based on the request, briefly recommend the most suitable provider(s) "
                "and explain why in 2–3 sentences. Then state the suggested next step for the buyer."
            )
        else:
            match_prompt = (
                f"A buyer submitted a request for '{category}' services in {location}. "
                f"Need: {service_description}. Budget: {budget}.\n\n"
                "There are currently no registered providers in this exact category. "
                "Please suggest what the buyer should do next — "
                "e.g. try a related category, check online/remote options, or wait for new providers."
            )

        with st.spinner("Finding best match…"):
            ai_response = client.messages.create(
                model="claude-opus-4-6",
                max_tokens=400,
                system=KOPONIX_SYSTEM_PROMPT,
                messages=[{"role": "user", "content": match_prompt}],
            )
            match_text = ai_response.content[0].text

        st.markdown(match_text)

        # ── CTAs ───────────────────────────────────────────────────────────────
        st.divider()
        ca, cb = st.columns(2)
        with ca:
            if st.button("🔍 Browse providers directly"):
                st.switch_page("pages/2_Find_Services.py")
        with cb:
            if st.button("💬 Chat with Koponix AI"):
                st.session_state.pending_prompt = (
                    f"I just submitted a service request for {category} in {location}. "
                    f"My need: {service_description}. Budget: {budget}. "
                    "What should I do next to find the right provider?"
                )
                st.switch_page("pages/1_AI_Assistant.py")
