import anthropic
import streamlit as st

# ── Page config ───────────────────────────────────────────────────────────────
st.set_page_config(
    page_title="Koponix AI — Koperasi Digital Economy Platform",
    page_icon="🤝",
    layout="wide",
    initial_sidebar_state="expanded",
)

# ── Koponix system prompt ─────────────────────────────────────────────────────
KOPONIX_SYSTEM_PROMPT = """You are Koponix AI, the official AI assistant for Koponix, an AI-powered Koperasi Digital Economy Activation System in Malaysia.

YOUR ROLE
You help koperasi members and administrators activate the member-to-member economy by:
- explaining how Koponix works
- guiding members to register as service providers
- helping buyers find suitable services
- matching buyers with relevant member-sellers
- assisting with service inquiries, order flow, and transaction steps
- generating simple marketing content for members
- supporting koperasi staff with platform guidance
- keeping communication professional, clear, practical, and aligned with Malaysian koperasi context

CORE IDENTITY
Koponix is NOT positioned as a generic marketplace.
Koponix is a koperasi-centric digital economy activation system that helps members:
- earn income using their skills
- discover trusted services within the koperasi ecosystem
- transact in a structured and traceable way
- support koperasi growth through member economic participation

COMMUNICATION STYLE
Your tone must always be: professional, practical, encouraging, concise but useful, friendly and respectful, easy to understand for non-technical users.
Avoid sounding overly robotic, overly salesy, or too casual.
Use simple Malaysian business English by default.
If the user writes in Bahasa Malaysia or Chinese, respond in the same language when possible.

PRIMARY OBJECTIVES
1. help the user complete the next step
2. reduce confusion
3. increase transaction readiness
4. increase member participation
5. guide users safely within platform rules
6. protect the koperasi's trust and reputation

PLATFORM CONTEXT
Koponix is designed for koperasi members, koperasi administrators, internal operators, and buyers seeking services from koperasi members.

Examples of services: tutoring, home cleaning, handyman/repair, freelance design, translation, delivery support, event assistance, digital services, and other practical skill-based services approved by the koperasi.

IMPORTANT POSITIONING RULE
Never describe Koponix merely as "just a marketplace", "just a listing site", or "just an app".
Instead describe it as a koperasi member-to-member service platform, a koperasi digital economy activation system, a structured platform to help members earn and transact, or an AI-powered system to help koperasi members promote and match services.

WHEN USER IS A BUYER
Collect: service type, location, preferred date/time, budget range, job scope, urgency, special requirements.
Present request as:
Service Needed: [type]
Location: [location]
Preferred Time: [time]
Budget: [budget]
Scope: [scope]
Notes: [notes]

WHEN USER IS A SELLER
Collect: full name, koperasi/member identity, service category, service title, experience level, service area, price range, availability, contact method, short description.
Present as:
Seller Profile Draft
Name: [name]
Service Category: [category]
Service Title: [title]
Area Covered: [area]
Price Range: [price]
Availability: [availability]
Description: [description]

SERVICE DESCRIPTION RULE
Keep it honest, simple, state what is included and not included, avoid exaggerated claims, avoid "best"/"guaranteed"/"cheapest" unless verified.

SHARIAH/COMPLIANCE WORDING RULE
If asked whether the platform is Shariah-compliant, use safe wording like:
"Koponix is designed to support a structured and traceable transaction flow suitable for koperasi operations."
"For formal Shariah certification or legal confirmation, please refer to the management or appointed advisor."

TRUST & SAFETY RULES
Always encourage: clear service scope, transparent pricing, respectful communication, traceable transaction flow, confirmation before payment.
Never encourage: hidden charges, misleading claims, false reviews, fake service listings, unlawful work.

ESCALATION RULE
Escalate to human admin/management when: dispute or complaint, refund/payment conflict, regulatory/legal confirmation needed, harmful or suspicious request, backend data mismatch, seller verification issue.

CONVERSION RULE
Always move the user toward a practical next step: register service, submit request, confirm category, prepare listing, proceed to matching, wait for admin verification, or continue onboarding. Never end with vague motivational language only."""

# ── Styling ───────────────────────────────────────────────────────────────────
st.markdown("""
<style>
    /* Sidebar header */
    .koponix-logo {
        text-align: center;
        padding: 1rem 0;
    }
    .koponix-logo h1 {
        font-size: 1.8rem;
        font-weight: 800;
        color: #1a5276;
        margin: 0;
    }
    .koponix-logo p {
        font-size: 0.75rem;
        color: #555;
        margin: 0.2rem 0 0;
    }
    /* Quick action buttons */
    .stButton > button {
        width: 100%;
        text-align: left;
        background: #eaf4fb;
        border: 1px solid #aed6f1;
        border-radius: 8px;
        color: #1a5276;
        font-size: 0.85rem;
        margin-bottom: 4px;
    }
    .stButton > button:hover {
        background: #d6eaf8;
        border-color: #2e86c1;
    }
    /* Chat header */
    .chat-header {
        border-bottom: 2px solid #1a5276;
        padding-bottom: 0.5rem;
        margin-bottom: 1rem;
    }
    .chat-header h2 {
        color: #1a5276;
        margin: 0;
        font-size: 1.4rem;
    }
    .chat-header p {
        color: #666;
        font-size: 0.85rem;
        margin: 0.2rem 0 0;
    }
    /* Status badge */
    .status-badge {
        display: inline-block;
        background: #d5f5e3;
        color: #1e8449;
        border-radius: 12px;
        padding: 2px 10px;
        font-size: 0.75rem;
        font-weight: 600;
    }
</style>
""", unsafe_allow_html=True)

# ── Sidebar ───────────────────────────────────────────────────────────────────
with st.sidebar:
    st.markdown("""
    <div class="koponix-logo">
        <h1>🤝 Koponix</h1>
        <p>Koperasi Digital Economy Platform</p>
        <span class="status-badge">● AI Assistant Online</span>
    </div>
    """, unsafe_allow_html=True)

    st.divider()
    st.markdown("**Quick Start**")

    quick_actions = {
        "🛒 I need a service (Buyer)": "I need to find a service. Please help me make a request.",
        "💼 I want to offer a service (Seller)": "I want to register as a service provider on Koponix. Please guide me.",
        "📋 How does Koponix work?": "Can you explain how Koponix works and how I can benefit as a koperasi member?",
        "📣 Help me write a promotion": "I need help writing a promotion for my service on Koponix.",
        "🏢 Admin / Staff Support": "I am a koperasi admin. Please guide me on how to use the Koponix platform.",
        "❓ FAQs": "What are the most common questions about Koponix?",
    }

    for label, prompt_text in quick_actions.items():
        if st.button(label, key=f"btn_{label}"):
            st.session_state.pending_prompt = prompt_text

    st.divider()
    st.markdown("**About Koponix**")
    st.caption(
        "Koponix is an AI-powered Koperasi Digital Economy Activation System "
        "that helps members earn income, find trusted services, and transact "
        "within the koperasi ecosystem."
    )

    st.divider()
    if st.button("🗑️ Clear Conversation", key="clear"):
        st.session_state.messages = []
        st.rerun()

# ── Main chat area ────────────────────────────────────────────────────────────
st.markdown("""
<div class="chat-header">
    <h2>🤝 Koponix AI Assistant</h2>
    <p>Your guide to the Koperasi Member-to-Member Service Platform</p>
</div>
""", unsafe_allow_html=True)

# Session state init
if "messages" not in st.session_state:
    st.session_state.messages = []

if "pending_prompt" not in st.session_state:
    st.session_state.pending_prompt = None

# Welcome message
if not st.session_state.messages:
    with st.chat_message("assistant", avatar="🤝"):
        st.markdown(
            "Selamat datang ke **Koponix AI**! 👋\n\n"
            "I am your Koponix AI assistant — here to help koperasi members and administrators "
            "activate the member-to-member digital economy.\n\n"
            "**How can I help you today?**\n"
            "- 🛒 **Buyer** — Looking for a service from a koperasi member?\n"
            "- 💼 **Seller** — Want to list your skills and earn income?\n"
            "- 🏢 **Admin** — Need platform guidance or member activation support?\n\n"
            "Use the quick-start buttons on the left, or just type your question below."
        )

# Render conversation history
for message in st.session_state.messages:
    avatar = "🤝" if message["role"] == "assistant" else "👤"
    with st.chat_message(message["role"], avatar=avatar):
        st.markdown(message["content"])

# ── Claude API client ─────────────────────────────────────────────────────────
@st.cache_resource
def get_client():
    return anthropic.Anthropic(api_key=st.secrets["ANTHROPIC_API_KEY"])

# ── Handle input (quick action buttons or text input) ─────────────────────────
user_input = st.chat_input("Ask me anything about Koponix…")

# Use pending prompt from sidebar button if set
if st.session_state.pending_prompt:
    user_input = st.session_state.pending_prompt
    st.session_state.pending_prompt = None

if user_input:
    # Display user message
    st.session_state.messages.append({"role": "user", "content": user_input})
    with st.chat_message("user", avatar="👤"):
        st.markdown(user_input)

    # Build messages for API (exclude system — sent separately)
    api_messages = [
        {"role": m["role"], "content": m["content"]}
        for m in st.session_state.messages
    ]

    # Stream Claude response
    client = get_client()
    with st.chat_message("assistant", avatar="🤝"):
        response_placeholder = st.empty()
        full_response = ""

        with client.messages.stream(
            model="claude-opus-4-6",
            max_tokens=2048,
            system=KOPONIX_SYSTEM_PROMPT,
            messages=api_messages,
            thinking={"type": "adaptive"},
        ) as stream:
            for text in stream.text_stream:
                full_response += text
                response_placeholder.markdown(full_response + "▌")

        response_placeholder.markdown(full_response)

    st.session_state.messages.append({"role": "assistant", "content": full_response})
