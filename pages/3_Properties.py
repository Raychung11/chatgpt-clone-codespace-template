"""
STRate AI — Properties Page
Add, edit, and manage rental properties.
"""
import streamlit as st
from strate_ai.database import init_db, get_all_properties, upsert_property, get_or_create_demo_user
from strate_ai.market_data import get_available_locations

init_db()

st.set_page_config(page_title="Properties — STRate AI", page_icon="🏢", layout="wide")
st.title("🏢 My Properties")
st.caption("Manage your short-term rental properties.")
st.divider()

properties = get_all_properties()

# ─── Existing properties ──────────────────────────────────────────────────────
if properties:
    for prop in properties:
        with st.container(border=True):
            c1, c2, c3 = st.columns([3, 2, 1])
            with c1:
                st.markdown(f"### 🏠 {prop['name']}")
                st.caption(f"📍 {prop['location']} · {prop['room_type'].replace('_', ' ').title()} · {prop['bedrooms']} BR")
            with c2:
                st.metric("Base Price", f"RM {prop['base_price']:.0f}")
                st.caption(f"Range: RM {prop['min_price']:.0f} – RM {prop['max_price']:.0f}")
            with c3:
                if st.button("✏️ Edit", key=f"edit_{prop['id']}", use_container_width=True):
                    st.session_state["editing_property"] = prop
                    st.rerun()
    st.divider()
else:
    st.info("No properties yet. Add your first property below.")

# ─── Add / Edit form ─────────────────────────────────────────────────────────
editing = st.session_state.get("editing_property", None)
form_title = f"✏️ Edit: {editing['name']}" if editing else "➕ Add New Property"

with st.expander(form_title, expanded=(editing is not None or not properties)):
    with st.form("property_form", clear_on_submit=True):
        col1, col2 = st.columns(2)

        with col1:
            name = st.text_input("Property Name", value=editing["name"] if editing else "")
            location = st.selectbox(
                "Location",
                get_available_locations(),
                index=get_available_locations().index(editing["location"])
                if editing and editing["location"] in get_available_locations() else 0
            )
            room_type = st.selectbox(
                "Room Type",
                ["entire_unit", "private_room", "shared_room"],
                index=["entire_unit", "private_room", "shared_room"].index(
                    editing["room_type"]) if editing else 0,
                format_func=lambda x: x.replace("_", " ").title()
            )
            bedrooms = st.number_input("Bedrooms", min_value=1, max_value=10,
                                       value=editing["bedrooms"] if editing else 1)

        with col2:
            base_price = st.number_input(
                "Base Price (RM)", min_value=50.0, max_value=2000.0, step=10.0,
                value=float(editing["base_price"]) if editing else 200.0
            )
            min_price = st.number_input(
                "Min Price (RM)", min_value=30.0, max_value=2000.0, step=10.0,
                value=float(editing["min_price"]) if editing else 150.0
            )
            max_price = st.number_input(
                "Max Price (RM)", min_value=50.0, max_value=5000.0, step=10.0,
                value=float(editing["max_price"]) if editing else 350.0
            )
            st.caption("Min/Max are hard guardrails — pricing engine will never exceed these.")

        submit_label = "💾 Update Property" if editing else "✅ Add Property"
        submitted = st.form_submit_button(submit_label, use_container_width=True, type="primary")

        if submitted:
            if not name.strip():
                st.error("Property name is required.")
            elif min_price >= max_price:
                st.error("Min price must be less than max price.")
            elif base_price < min_price or base_price > max_price:
                st.error("Base price must be between min and max price.")
            else:
                uid = get_or_create_demo_user()
                data = {
                    "user_id":    uid,
                    "name":       name.strip(),
                    "location":   location,
                    "base_price": base_price,
                    "min_price":  min_price,
                    "max_price":  max_price,
                    "room_type":  room_type,
                    "bedrooms":   bedrooms,
                }
                if editing:
                    data["id"] = editing["id"]

                pid = upsert_property(data)
                action = "updated" if editing else "added"
                st.success(f"Property {action}! (ID: {pid})")
                st.session_state.pop("editing_property", None)
                st.rerun()

    if editing:
        if st.button("❌ Cancel Edit", use_container_width=True):
            st.session_state.pop("editing_property", None)
            st.rerun()

# ─── Pricing logic explainer ─────────────────────────────────────────────────
with st.expander("❓ How does the pricing engine use these values?"):
    st.markdown("""
    | Field | Role |
    |-------|------|
    | **Base Price** | Starting point for all calculations |
    | **Min Price** | Hard floor — never go below this |
    | **Max Price** | Hard ceiling — never exceed this |
    | **Location** | Used to fetch competitor & demand data |

    **Formula:**
    ```
    suggested = base_price × (1 + demand_factor + event_boost + competitor_gap + occupancy_adj)
    suggested = clamp(suggested, min_price, max_price)
    ```

    The AI explains the result — it does **not** calculate it.
    """)
