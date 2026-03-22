"""Koponix AI Assistant — Chat page."""
import anthropic
import streamlit as st
from utils import (
    KOPONIX_SYSTEM_PROMPT,
    apply_koponix_style,
    sidebar_logo,
    sidebar_member_status,
)

st.set_page_config(
    page_title="Koponix AI Assistant",
    page_icon="💬",
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

    st.markdown("**Quick Start**")
    quick_actions = {
        "🛒 I need a service": "I need to find a service. Please help me make a request.",
        "💼 I want to offer a service": "I want to register as a service provider on Koponix. Please guide me.",
        "📋 How does Koponix work?": "Can you explain how Koponix works and how I can benefit as a koperasi member?",
        "📣 Write a promotion": "I need help writing a promotion or marketing copy for my Koponix service.",
        "🏢 Admin support": "I am a koperasi admin. Please guide me on how to use the Koponix platform.",
        "❓ Common FAQs": "What are the most common questions about Koponix?",
    }
    for label, prompt_text in quick_actions.items():
        if st.button(label, key=f"qa_{label}", use_container_width=True):
            st.session_state.pending_prompt = prompt_text

    st.divider()
    if st.button("🗑️ Clear Chat", use_container_width=True):
        st.session_state.chat_messages = []
        st.rerun()
    sidebar_member_status()

# ── Page header ───────────────────────────────────────────────────────────────
st.markdown("""
<div style="border-bottom:2px solid #1a5276; padding-bottom:0.5rem; margin-bottom:1rem;">
    <h2 style="color:#1a5276; margin:0;">💬 Koponix AI Assistant</h2>
    <p style="color:#666; font-size:0.85rem; margin:0.2rem 0 0;">
        Ask about services, get seller guidance, or request buyer matching support.
    </p>
</div>
""", unsafe_allow_html=True)

# ── Session state ─────────────────────────────────────────────────────────────
if "chat_messages" not in st.session_state:
    st.session_state.chat_messages = []
if "pending_prompt" not in st.session_state:
    st.session_state.pending_prompt = None

# ── Welcome message ───────────────────────────────────────────────────────────
if not st.session_state.chat_messages:
    with st.chat_message("assistant", avatar="🤝"):
        st.markdown(
            "Selamat datang ke **Koponix AI**! 👋\n\n"
            "I am here to help koperasi members and administrators activate the "
            "member-to-member digital economy.\n\n"
            "**How can I help you today?**\n"
            "- 🛒 **Buyer** — Looking for a service from a koperasi member?\n"
            "- 💼 **Seller** — Want to list your skills and earn income?\n"
            "- 🏢 **Admin** — Need platform or member activation guidance?\n\n"
            "Use the quick-start buttons on the left, or just type below."
        )

# ── Render history ────────────────────────────────────────────────────────────
for msg in st.session_state.chat_messages:
    avatar = "🤝" if msg["role"] == "assistant" else "👤"
    with st.chat_message(msg["role"], avatar=avatar):
        st.markdown(msg["content"])

# ── Claude client ─────────────────────────────────────────────────────────────
@st.cache_resource
def get_client():
    return anthropic.Anthropic(api_key=st.secrets["ANTHROPIC_API_KEY"])

# ── Input handling ────────────────────────────────────────────────────────────
user_input = st.chat_input("Ask me anything about Koponix…")
if st.session_state.pending_prompt:
    user_input = st.session_state.pending_prompt
    st.session_state.pending_prompt = None

if user_input:
    st.session_state.chat_messages.append({"role": "user", "content": user_input})
    with st.chat_message("user", avatar="👤"):
        st.markdown(user_input)

    api_messages = [
        {"role": m["role"], "content": m["content"]}
        for m in st.session_state.chat_messages
    ]

    client = get_client()
    with st.chat_message("assistant", avatar="🤝"):
        placeholder = st.empty()
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
                placeholder.markdown(full_response + "▌")
        placeholder.markdown(full_response)

    st.session_state.chat_messages.append({"role": "assistant", "content": full_response})
