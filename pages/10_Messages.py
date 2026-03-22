"""In-app messaging with AI bot support."""
import os
import anthropic
import streamlit as st

from utils import (
    apply_koponix_style,
    sidebar_logo,
    sidebar_member_status,
    load_sellers,
    get_member_conversations,
    create_conversation,
    add_message,
    load_conversations,
    KOPONIX_SYSTEM_PROMPT,
)

st.set_page_config(page_title="Messages – Koponix", page_icon="💬", layout="wide")
apply_koponix_style()

with st.sidebar:
    sidebar_logo()
    st.page_link("app.py", label="Home", icon="🏠")
    st.page_link("pages/2_Find_Services.py", label="Find Services", icon="🔍")
    st.page_link("pages/9_Member_Portal.py", label="Member Portal", icon="👤")
    sidebar_member_status()

st.title("💬 Messages")

# ── Auth guard ────────────────────────────────────────────────────────────────
if not st.session_state.get("member_logged_in"):
    st.info("Please log in to access your messages.")
    if st.button("Go to Member Login", type="primary"):
        st.switch_page("pages/9_Member_Portal.py")
    st.stop()

member = st.session_state["member"]
kop_id = member["koperasi_id"]
member_name = member["name"]

# ── Handle new conversation started from Find Services ────────────────────────
if "start_conv" in st.session_state:
    sc = st.session_state.pop("start_conv")
    conv_id = create_conversation(
        buyer_kop_id=kop_id,
        buyer_name=member_name,
        seller_kop_id=sc["seller_kop_id"],
        seller_name=sc["seller_name"],
        subject=sc["subject"],
        seller_id=sc.get("seller_id", ""),
    )
    st.session_state["active_conv_id"] = conv_id

# ── Load conversations ─────────────────────────────────────────────────────────
my_convs = get_member_conversations(kop_id)

if not my_convs and "active_conv_id" not in st.session_state:
    st.markdown("""
    <div style="text-align:center;padding:3rem 1rem;color:#888;">
        <div style="font-size:3rem;">💬</div>
        <div style="font-size:1.1rem;font-weight:600;margin-top:0.5rem;">No messages yet</div>
        <div style="font-size:0.9rem;margin-top:0.3rem;">
            Go to <b>Find Services</b> and click <b>Message Seller</b> to start a conversation.
        </div>
    </div>""", unsafe_allow_html=True)
    st.stop()

# ── Layout: sidebar list + main chat ──────────────────────────────────────────
col_list, col_chat = st.columns([1, 2], gap="medium")

with col_list:
    st.markdown("#### Conversations")
    active_id = st.session_state.get("active_conv_id")

    if not my_convs:
        st.caption("No conversations yet.")
    else:
        for cid, conv in sorted(my_convs.items(),
                                key=lambda x: x[1].get("created_date", ""), reverse=True):
            other_kop = next((p for p in conv["participants"] if p != kop_id), "?")
            other_name = conv["participant_names"].get(other_kop, other_kop)
            subject = conv.get("subject", "Conversation")
            msg_count = len(conv.get("messages", []))
            is_active = (cid == active_id)
            border = "#1a5276" if is_active else "#dde"
            bg = "#eaf4fb" if is_active else "#fff"
            label = f"**{other_name}**\n\n_{subject}_\n\n{msg_count} message{'s' if msg_count != 1 else ''}"
            if st.button(label, key=f"conv_{cid}", use_container_width=True):
                st.session_state["active_conv_id"] = cid
                st.rerun()

# ── Chat panel ────────────────────────────────────────────────────────────────
with col_chat:
    active_id = st.session_state.get("active_conv_id")

    if not active_id or active_id not in my_convs:
        st.markdown("""
        <div style="text-align:center;padding:4rem 1rem;color:#aaa;">
            <div style="font-size:2.5rem;">👈</div>
            <div style="margin-top:0.5rem;">Select a conversation to view messages</div>
        </div>""", unsafe_allow_html=True)
    else:
        conv = my_convs[active_id]
        other_kop = next((p for p in conv["participants"] if p != kop_id), "?")
        other_name = conv["participant_names"].get(other_kop, other_kop)
        subject = conv.get("subject", "Conversation")

        st.markdown(f"#### {subject}")
        st.caption(f"With **{other_name}** · Started {conv.get('created_date', '')}")
        st.divider()

        # Message history
        messages = conv.get("messages", [])
        chat_container = st.container(height=420)
        with chat_container:
            if not messages:
                st.caption("No messages yet. Say hello!")
            for msg in messages:
                is_mine = (msg["sender"] == kop_id)
                is_ai = msg.get("is_ai", False)

                if is_ai:
                    with st.chat_message("assistant", avatar="🤖"):
                        st.markdown(f"**Koponix AI**\n\n{msg['text']}")
                        st.caption(msg["timestamp"][:16].replace("T", " "))
                elif is_mine:
                    with st.chat_message("user"):
                        st.markdown(msg["text"])
                        st.caption(f"You · {msg['timestamp'][:16].replace('T', ' ')}")
                else:
                    with st.chat_message("assistant", avatar="👤"):
                        st.markdown(f"**{msg['sender_name']}**\n\n{msg['text']}")
                        st.caption(msg["timestamp"][:16].replace("T", " "))

        st.divider()

        # ── Send message ─────────────────────────────────────────────────────
        with st.form(key=f"msg_form_{active_id}", clear_on_submit=True):
            user_input = st.text_area("Your message", height=80, placeholder="Type a message…", label_visibility="collapsed")
            c1, c2 = st.columns([3, 1])
            with c1:
                send = st.form_submit_button("Send", type="primary", use_container_width=True)
            with c2:
                ask_ai = st.form_submit_button("🤖 Ask AI", use_container_width=True)

        if send and user_input.strip():
            add_message(active_id, kop_id, member_name, user_input.strip())
            st.rerun()

        if ask_ai and user_input.strip():
            # First post the user message
            add_message(active_id, kop_id, member_name, user_input.strip())

            # Build AI context from conversation history
            fresh_conv = load_conversations().get(active_id, conv)
            history = fresh_conv.get("messages", [])

            context_msgs = []
            for m in history[-10:]:  # last 10 messages for context
                role = "user" if m["sender"] == kop_id else "assistant"
                context_msgs.append({"role": role, "content": m["text"]})

            # Ensure last message is from user (required by Claude)
            if not context_msgs or context_msgs[-1]["role"] != "user":
                context_msgs.append({"role": "user", "content": user_input.strip()})

            system = (
                KOPONIX_SYSTEM_PROMPT
                + f"\n\nYou are assisting in a conversation between members about: '{subject}'. "
                "Give practical, friendly advice to help them connect and agree on the service. "
                "Keep replies concise (2-4 sentences)."
            )

            with st.spinner("AI is thinking…"):
                try:
                    client = anthropic.Anthropic(api_key=os.environ.get("ANTHROPIC_API_KEY"))
                    response = client.messages.create(
                        model="claude-sonnet-4-6",
                        max_tokens=300,
                        system=system,
                        messages=context_msgs,
                    )
                    ai_text = response.content[0].text
                except Exception as e:
                    ai_text = f"Sorry, I couldn't respond right now. ({e})"

            add_message(active_id, "AI", "Koponix AI", ai_text, is_ai=True)
            st.rerun()

        elif ask_ai and not user_input.strip():
            # Ask AI with just a prompt about the conversation
            fresh_conv = load_conversations().get(active_id, conv)
            history = fresh_conv.get("messages", [])

            context_summary = "\n".join(
                f"{m['sender_name']}: {m['text']}" for m in history[-6:]
            ) or "No messages yet."

            prompt = (
                f"This conversation is about: '{subject}'. "
                f"Recent messages:\n{context_summary}\n\n"
                "Give a helpful suggestion or tip for moving this service conversation forward."
            )

            system = KOPONIX_SYSTEM_PROMPT + "\nKeep your reply to 2-4 sentences."

            with st.spinner("AI is thinking…"):
                try:
                    client = anthropic.Anthropic(api_key=os.environ.get("ANTHROPIC_API_KEY"))
                    response = client.messages.create(
                        model="claude-sonnet-4-6",
                        max_tokens=300,
                        system=system,
                        messages=[{"role": "user", "content": prompt}],
                    )
                    ai_text = response.content[0].text
                except Exception as e:
                    ai_text = f"Sorry, I couldn't respond right now. ({e})"

            add_message(active_id, "AI", "Koponix AI", ai_text, is_ai=True)
            st.rerun()
