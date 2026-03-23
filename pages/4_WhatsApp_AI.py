"""
STRate AI — WhatsApp AI Page
Simulate WhatsApp interactions and test the messaging format.
"""
import streamlit as st
from datetime import datetime, timedelta

from strate_ai.database import init_db, get_all_properties, get_latest_recommendation, get_market_data
from strate_ai.ai_explainer import generate_whatsapp_reply
from strate_ai.pricing_engine import demand_label

init_db()

st.set_page_config(page_title="WhatsApp AI — STRate AI", page_icon="💬", layout="wide")
st.title("💬 WhatsApp AI Integration")
st.caption("Preview how STRate AI communicates with property owners via WhatsApp.")
st.divider()

st.info("""
**Production Setup:** Connect via [AiServe](https://aisensy.com) or [Twilio WhatsApp API](https://www.twilio.com/whatsapp).
This page simulates what owners receive.
""")

properties = get_all_properties()
if not properties:
    st.warning("No properties found. Add one in the Properties page.")
    st.stop()

# ─── WhatsApp chat simulator ──────────────────────────────────────────────────
st.subheader("📱 Message Simulator")

col_l, col_r = st.columns([1, 1])

with col_l:
    st.markdown("**User sends:**")
    prop_names = {p["id"]: p["name"] for p in properties}
    selected_id = st.selectbox("Property", options=[p["id"] for p in properties],
                                format_func=lambda x: prop_names[x])
    prop = next(p for p in properties if p["id"] == selected_id)

    query_type = st.radio(
        "Query type",
        ["What price today?", "What price tomorrow?", "What price this week?", "Show demand report"]
    )
    send = st.button("📤 Send Message", type="primary", use_container_width=True)

with col_r:
    st.markdown("**STRate AI replies:**")

    if send:
        today = datetime.now().strftime("%Y-%m-%d")
        tomorrow = (datetime.now() + timedelta(days=1)).strftime("%Y-%m-%d")

        # Bubble style
        bubble_style = """
        <style>
        .chat-bubble {
            background: #DCF8C6;
            border-radius: 12px 12px 0 12px;
            padding: 12px 16px;
            margin: 8px 0;
            font-family: monospace;
            white-space: pre-wrap;
            font-size: 14px;
            max-width: 90%;
        }
        .timestamp { font-size: 11px; color: #888; text-align: right; }
        </style>
        """
        st.markdown(bubble_style, unsafe_allow_html=True)

        if query_type == "What price today?":
            rec = get_latest_recommendation(prop["id"], today)
            if rec:
                msg = generate_whatsapp_reply(prop["name"], rec)
            else:
                msg = f"*STRate AI* — {prop['name']}\n\nNo recommendation yet for today. Please run the pricing engine."

        elif query_type == "What price tomorrow?":
            rec = get_latest_recommendation(prop["id"], tomorrow)
            if rec:
                msg = generate_whatsapp_reply(prop["name"], rec)
            else:
                msg = f"*STRate AI* — {prop['name']}\n\nNo recommendation yet for tomorrow."

        elif query_type == "What price this week?":
            lines = [f"*STRate AI Weekly Forecast*\n*{prop['name']}*\n"]
            for delta in range(7):
                d = (datetime.now() + timedelta(days=delta)).strftime("%Y-%m-%d")
                rec = get_latest_recommendation(prop["id"], d)
                dt = datetime.strptime(d, "%Y-%m-%d")
                day_label = dt.strftime("%a %d/%m")
                if rec:
                    base = rec["base_price"]
                    sp = rec["suggested_price"]
                    chg = ((sp - base) / base * 100)
                    arrow = "▲" if chg >= 0 else "▼"
                    lines.append(f"{day_label}: RM{sp:.0f} {arrow}{abs(chg):.0f}%")
                else:
                    lines.append(f"{day_label}: —")
            msg = "\n".join(lines)

        else:  # demand report
            market = get_market_data(prop["location"], today)
            if market:
                demand = demand_label(market["occupancy_rate"], bool(market["event_flag"]))
                event_line = f"🎉 Event: {market['event_name']}" if market["event_flag"] and market["event_name"] else ""
                msg = (
                    f"*STRate AI — Demand Report*\n"
                    f"*{prop['name']} ({prop['location']})*\n\n"
                    f"📊 Demand Level: {demand}\n"
                    f"🏠 Area Occupancy: {market['occupancy_rate']*100:.0f}%\n"
                    f"💰 Competitor Avg: RM{market['avg_price']:.0f}\n"
                    f"🏘️ Active Listings: {market['listing_count']}"
                    + (f"\n{event_line}" if event_line else "")
                )
            else:
                msg = f"*STRate AI* — No demand data available for {prop['location']} today."

        st.markdown(
            f'<div class="chat-bubble">{msg}</div>'
            f'<div class="timestamp">{datetime.now().strftime("%H:%M")} ✓✓</div>',
            unsafe_allow_html=True
        )
    else:
        st.markdown("*Send a message to see the reply...*")

st.divider()

# ─── Integration guide ────────────────────────────────────────────────────────
with st.expander("🔌 Production Integration Guide"):
    st.markdown("""
    ### Connecting to AiServe / Twilio WhatsApp

    1. **Set up webhook** — point AiServe to your server endpoint
    2. **Parse incoming message** — extract property name + query intent
    3. **Call STRate AI API** — fetch recommendation from DB
    4. **Format reply** using `generate_whatsapp_reply()`
    5. **Send back** via AiServe API

    ```python
    # Example webhook handler (FastAPI)
    from fastapi import FastAPI, Request
    from strate_ai.database import get_latest_recommendation, get_all_properties
    from strate_ai.ai_explainer import generate_whatsapp_reply
    from datetime import datetime

    app = FastAPI()

    @app.post("/webhook/whatsapp")
    async def whatsapp_webhook(request: Request):
        body = await request.json()
        message = body.get("message", "").lower()
        phone = body.get("from")

        # Simple intent matching
        if "price" in message or "harga" in message:
            properties = get_all_properties()  # filter by owner phone
            today = datetime.now().strftime("%Y-%m-%d")
            replies = []
            for prop in properties:
                rec = get_latest_recommendation(prop["id"], today)
                if rec:
                    replies.append(generate_whatsapp_reply(prop["name"], rec))
            return {"reply": "\\n\\n".join(replies) or "No recommendations today."}

        return {"reply": "Hi! Ask me: 'What price today?' 💰"}
    ```
    """)
