"""Shared utilities, data layer, and styling for Koponix platform."""
import json
import os
import uuid
from datetime import date, datetime

# ── Paths ─────────────────────────────────────────────────────────────────────
BASE_DIR = os.path.dirname(__file__)
DATA_DIR = os.path.join(BASE_DIR, "data")
SELLERS_FILE = os.path.join(DATA_DIR, "sellers.json")
REQUESTS_FILE = os.path.join(DATA_DIR, "requests.json")

# ── Constants ─────────────────────────────────────────────────────────────────
CATEGORIES = [
    "Home Services",
    "Education & Tutoring",
    "Creative & Design",
    "Language & Translation",
    "Technical & Repair",
    "Events & Assistance",
    "Delivery & Logistics",
    "Digital Services",
    "Other",
]

CATEGORY_ICONS = {
    "Home Services": "🏠",
    "Education & Tutoring": "📚",
    "Creative & Design": "🎨",
    "Language & Translation": "🌐",
    "Technical & Repair": "🔧",
    "Events & Assistance": "🎉",
    "Delivery & Logistics": "🚚",
    "Digital Services": "💻",
    "Other": "⭐",
}

CATEGORY_COLORS = {
    "Home Services": "#2196F3",
    "Education & Tutoring": "#9C27B0",
    "Creative & Design": "#FF5722",
    "Language & Translation": "#009688",
    "Technical & Repair": "#607D8B",
    "Events & Assistance": "#E91E63",
    "Delivery & Logistics": "#FF9800",
    "Digital Services": "#3F51B5",
    "Other": "#795548",
}

LOCATIONS = [
    "Kuala Lumpur",
    "Petaling Jaya",
    "Shah Alam",
    "Subang Jaya",
    "Klang",
    "Ampang",
    "Cheras",
    "Puchong",
    "Seremban",
    "Johor Bahru",
    "Penang",
    "Ipoh",
    "Kota Kinabalu",
    "Kuching",
    "Online / Remote",
]

# ── Koponix system prompt (shared across pages) ───────────────────────────────
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
Koponix is a koperasi-centric digital economy activation system that helps members:
- earn income using their skills
- discover trusted services within the koperasi ecosystem
- transact in a structured and traceable way
- support koperasi growth through member economic participation

COMMUNICATION STYLE
Professional, practical, encouraging, concise, friendly, easy to understand.
Use simple Malaysian business English by default.
If the user writes in Bahasa Malaysia or Chinese, respond in the same language.

PRIMARY OBJECTIVES
1. Help the user complete the next step
2. Reduce confusion
3. Increase transaction readiness
4. Increase member participation
5. Guide users safely within platform rules

WHEN USER IS A BUYER
Collect: service type, location, preferred date/time, budget range, job scope, urgency, special requirements.
Present request summary as:
Service Needed: [type]
Location: [location]
Preferred Time: [time]
Budget: [budget]
Scope: [scope]
Notes: [notes]

WHEN USER IS A SELLER
Collect: name, koperasi/member identity, service category, service title, experience, service area, price range, availability, description.
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
Keep it honest, simple, state what is included/not included, avoid exaggerated claims.

SHARIAH/COMPLIANCE WORDING RULE
If asked about Shariah compliance: "Koponix is designed to support a structured and traceable transaction flow suitable for koperasi operations. For formal certification, please refer to management or the appointed advisor."

TRUST & SAFETY RULES
Always encourage: clear service scope, transparent pricing, traceable transaction flow.
Never encourage: hidden charges, misleading claims, unlawful work.

ESCALATION RULE
Escalate to human admin for: disputes, refunds, legal/regulatory questions, suspicious requests.

CONVERSION RULE
Always move user toward a practical next step. Never end with vague motivational language only."""

# ── Demo seed data ────────────────────────────────────────────────────────────
DEMO_SELLERS = [
    {
        "id": "demo-001",
        "name": "Ahmad Rizal bin Hassan",
        "koperasi_id": "KOP-2024-001",
        "category": "Home Services",
        "service_title": "Professional Home Cleaning",
        "area": "Kuala Lumpur",
        "price_range": "RM 80 – RM 120 per session",
        "availability": "Weekends, Weekday evenings",
        "description": "Reliable home cleaning service covering living areas, bedrooms, kitchen, and bathrooms. Bring own equipment. Min 2-hour booking. Does not include deep cleaning of drains or heavy furniture moving.",
        "contact": "WhatsApp available upon request",
        "experience": "3 years",
        "registered_date": "2026-01-10",
        "status": "active",
    },
    {
        "id": "demo-002",
        "name": "Siti Norzahra binti Azmi",
        "koperasi_id": "KOP-2024-002",
        "category": "Education & Tutoring",
        "service_title": "Math & Science Tutor (Form 1–5)",
        "area": "Petaling Jaya",
        "price_range": "RM 50 – RM 80 per hour",
        "availability": "Mon–Fri evenings, Sat mornings",
        "description": "SPM-focused tutoring for Mathematics and Science subjects. Home visit or online via Google Meet. Small group sessions available at reduced rate. 5 years teaching experience.",
        "contact": "WhatsApp available upon request",
        "experience": "5 years",
        "registered_date": "2026-01-15",
        "status": "active",
    },
    {
        "id": "demo-003",
        "name": "David Lim Wei Keat",
        "koperasi_id": "KOP-2024-003",
        "category": "Creative & Design",
        "service_title": "Logo & Branding Design",
        "area": "Online / Remote",
        "price_range": "RM 200 – RM 500 per project",
        "availability": "Mon–Fri, 9am–6pm",
        "description": "Professional logo design and basic brand identity packages. Includes up to 3 concept drafts and 2 revision rounds. Delivers editable files (AI, PNG, PDF). Social media kit available as add-on.",
        "contact": "WhatsApp available upon request",
        "experience": "4 years",
        "registered_date": "2026-01-20",
        "status": "active",
    },
    {
        "id": "demo-004",
        "name": "Nurul Izzati Mohamad",
        "koperasi_id": "KOP-2024-004",
        "category": "Language & Translation",
        "service_title": "English ↔ Bahasa Malaysia Translation",
        "area": "Online / Remote",
        "price_range": "RM 0.12 – RM 0.18 per word",
        "availability": "Flexible, 24–48 hour turnaround",
        "description": "Accurate translation for documents, reports, marketing materials, and websites. Specialises in business and legal texts. Certified translator. Does not cover technical engineering or medical documents.",
        "contact": "WhatsApp available upon request",
        "experience": "6 years",
        "registered_date": "2026-01-22",
        "status": "active",
    },
    {
        "id": "demo-005",
        "name": "Hafiz Rahman",
        "koperasi_id": "KOP-2024-005",
        "category": "Technical & Repair",
        "service_title": "Electrical & Plumbing Repair",
        "area": "Shah Alam",
        "price_range": "RM 80 – RM 250 per job",
        "availability": "Mon–Sat, 8am–6pm",
        "description": "Minor electrical wiring, switch/socket replacement, water pipe leaks, tap installation and basic plumbing. Covers Klang Valley area. Parts cost not included. Emergency call-out at additional charge.",
        "contact": "WhatsApp available upon request",
        "experience": "8 years",
        "registered_date": "2026-02-01",
        "status": "active",
    },
    {
        "id": "demo-006",
        "name": "Aileen Tan Mei Ling",
        "koperasi_id": "KOP-2024-006",
        "category": "Events & Assistance",
        "service_title": "Event Helper & Emcee (Bilingual)",
        "area": "Klang",
        "price_range": "RM 150 – RM 350 per day",
        "availability": "Weekends, Public Holidays",
        "description": "Event coordination assistant and bilingual emcee (English/Mandarin) for weddings, corporate dinners, and community events. Experienced in crowd management and MC script preparation.",
        "contact": "WhatsApp available upon request",
        "experience": "3 years",
        "registered_date": "2026-02-05",
        "status": "active",
    },
    {
        "id": "demo-007",
        "name": "Mohd Faizal Nordin",
        "koperasi_id": "KOP-2024-007",
        "category": "Delivery & Logistics",
        "service_title": "Same-Day Item Delivery (Klang Valley)",
        "area": "Subang Jaya",
        "price_range": "RM 25 – RM 60 per trip",
        "availability": "Daily, 8am–9pm",
        "description": "Point-to-point delivery within Klang Valley using MPV. Suitable for documents, food items, small parcels, and market goods. Max load 30kg. Price varies by distance. Not for fragile or hazardous items.",
        "contact": "WhatsApp available upon request",
        "experience": "2 years",
        "registered_date": "2026-02-10",
        "status": "active",
    },
    {
        "id": "demo-008",
        "name": "Rashidah Omar",
        "koperasi_id": "KOP-2024-008",
        "category": "Digital Services",
        "service_title": "Social Media Management",
        "area": "Online / Remote",
        "price_range": "RM 300 – RM 800 per month",
        "availability": "Mon–Fri",
        "description": "Monthly social media management for Facebook and Instagram. Includes content planning, 12 posts/month, basic graphic design, and monthly performance report. Ad budget not included.",
        "contact": "WhatsApp available upon request",
        "experience": "4 years",
        "registered_date": "2026-02-12",
        "status": "active",
    },
]

# ── Data layer ─────────────────────────────────────────────────────────────────

def _ensure_data_dir():
    os.makedirs(DATA_DIR, exist_ok=True)


def load_sellers() -> list:
    _ensure_data_dir()
    if not os.path.exists(SELLERS_FILE):
        # Seed with demo data on first run
        with open(SELLERS_FILE, "w") as f:
            json.dump(DEMO_SELLERS, f, indent=2)
        return list(DEMO_SELLERS)
    with open(SELLERS_FILE, "r") as f:
        return json.load(f)


def save_seller(seller_data: dict) -> str:
    sellers = load_sellers()
    seller_data["id"] = str(uuid.uuid4())
    seller_data["registered_date"] = str(date.today())
    seller_data["status"] = "active"
    sellers.append(seller_data)
    _ensure_data_dir()
    with open(SELLERS_FILE, "w") as f:
        json.dump(sellers, f, indent=2)
    return seller_data["id"]


def load_requests() -> list:
    _ensure_data_dir()
    if not os.path.exists(REQUESTS_FILE):
        return []
    with open(REQUESTS_FILE, "r") as f:
        return json.load(f)


def save_request(request_data: dict) -> str:
    requests = load_requests()
    request_data["id"] = str(uuid.uuid4())
    request_data["submitted_date"] = str(date.today())
    request_data["status"] = "open"
    requests.append(request_data)
    _ensure_data_dir()
    with open(REQUESTS_FILE, "w") as f:
        json.dump(requests, f, indent=2)
    return request_data["id"]


# ── Matching engine ────────────────────────────────────────────────────────────

EXPERIENCE_SCORE = {
    "Less than 1 year": 5,
    "1–2 years": 8,
    "3–5 years": 12,
    "5–10 years": 16,
    "10+ years": 20,
}


def score_match(request: dict, seller: dict) -> int:
    """Return 0–100 match score for a (request, seller) pair."""
    score = 0

    # Category match: 40 pts
    if seller.get("category") == request.get("category"):
        score += 40

    # Location match
    req_area = request.get("location", "")
    sel_area = seller.get("area", "")
    if sel_area == req_area:
        score += 30
    elif sel_area == "Online / Remote":
        score += 15
    elif req_area == "Online / Remote":
        score += 15

    # Experience: up to 20 pts
    score += EXPERIENCE_SCORE.get(seller.get("experience", ""), 5)

    # Status bonus: active sellers only
    if seller.get("status") == "active":
        score += 10

    return min(score, 100)


def get_top_matches(request: dict, sellers: list, top_n: int = 5) -> list:
    """Return sellers sorted by match score (descending), filtered to same category."""
    scored = [
        (score_match(request, s), s)
        for s in sellers
        if s.get("status") == "active" and s.get("category") == request.get("category")
    ]
    scored.sort(key=lambda x: -x[0])
    return scored[:top_n]


# ── Shared CSS ─────────────────────────────────────────────────────────────────

def update_seller_status(seller_id: str, new_status: str) -> bool:
    sellers = load_sellers()
    for s in sellers:
        if s["id"] == seller_id:
            s["status"] = new_status
            break
    else:
        return False
    _ensure_data_dir()
    with open(SELLERS_FILE, "w") as f:
        json.dump(sellers, f, indent=2)
    return True


def delete_seller(seller_id: str) -> bool:
    sellers = load_sellers()
    new_list = [s for s in sellers if s["id"] != seller_id]
    if len(new_list) == len(sellers):
        return False
    _ensure_data_dir()
    with open(SELLERS_FILE, "w") as f:
        json.dump(new_list, f, indent=2)
    return True


def update_request_status(request_id: str, new_status: str) -> bool:
    requests = load_requests()
    for r in requests:
        if r["id"] == request_id:
            r["status"] = new_status
            break
    else:
        return False
    _ensure_data_dir()
    with open(REQUESTS_FILE, "w") as f:
        json.dump(requests, f, indent=2)
    return True


# ── Member auth ────────────────────────────────────────────────────────────────

import hashlib

MEMBERS_FILE = os.path.join(DATA_DIR, "members.json")


def hash_password(password: str) -> str:
    return hashlib.sha256(password.encode()).hexdigest()


def load_members() -> list:
    _ensure_data_dir()
    if not os.path.exists(MEMBERS_FILE):
        return []
    with open(MEMBERS_FILE, "r") as f:
        return json.load(f)


def save_member(member_data: dict) -> str:
    members = load_members()
    member_data["id"] = str(uuid.uuid4())
    member_data["joined_date"] = str(date.today())
    members.append(member_data)
    _ensure_data_dir()
    with open(MEMBERS_FILE, "w") as f:
        json.dump(members, f, indent=2)
    return member_data["id"]


def authenticate_member(koperasi_id: str, password: str) -> dict | None:
    members = load_members()
    ph = hash_password(password)
    for m in members:
        if m.get("koperasi_id") == koperasi_id and m.get("password_hash") == ph:
            return m
    return None


def get_member_by_kop_id(koperasi_id: str) -> dict | None:
    for m in load_members():
        if m.get("koperasi_id") == koperasi_id:
            return m
    return None


# ── Messaging ──────────────────────────────────────────────────────────────────

MESSAGES_FILE = os.path.join(DATA_DIR, "messages.json")


def load_conversations() -> dict:
    _ensure_data_dir()
    if not os.path.exists(MESSAGES_FILE):
        return {}
    with open(MESSAGES_FILE, "r") as f:
        return json.load(f)


def _save_conversations(convs: dict) -> None:
    _ensure_data_dir()
    with open(MESSAGES_FILE, "w") as f:
        json.dump(convs, f, indent=2)


def create_conversation(buyer_kop_id: str, buyer_name: str,
                        seller_kop_id: str, seller_name: str,
                        subject: str, seller_id: str = "") -> str:
    """Create a new conversation and return its ID."""
    convs = load_conversations()
    # Return existing conv between same pair about same seller
    for cid, c in convs.items():
        if (set(c["participants"]) == {buyer_kop_id, seller_kop_id}
                and c.get("seller_id") == seller_id):
            return cid
    conv_id = str(uuid.uuid4())[:8]
    convs[conv_id] = {
        "id": conv_id,
        "participants": [buyer_kop_id, seller_kop_id],
        "participant_names": {buyer_kop_id: buyer_name, seller_kop_id: seller_name},
        "subject": subject,
        "seller_id": seller_id,
        "created_date": str(date.today()),
        "messages": [],
    }
    _save_conversations(convs)
    return conv_id


def add_message(conv_id: str, sender_kop_id: str, sender_name: str,
                text: str, is_ai: bool = False) -> bool:
    convs = load_conversations()
    if conv_id not in convs:
        return False
    convs[conv_id]["messages"].append({
        "id": str(uuid.uuid4())[:8],
        "sender": sender_kop_id,
        "sender_name": sender_name,
        "text": text,
        "timestamp": datetime.now().isoformat(),
        "is_ai": is_ai,
    })
    _save_conversations(convs)
    return True


def get_member_conversations(kop_id: str) -> dict:
    return {cid: c for cid, c in load_conversations().items()
            if kop_id in c.get("participants", [])}


def sidebar_member_status():
    """Render logged-in member status block in the sidebar."""
    import streamlit as st
    st.divider()
    if st.session_state.get("member_logged_in"):
        member = st.session_state["member"]
        st.markdown(f"""
        <div style="background:#eaf4fb;border-radius:8px;padding:0.6rem 0.8rem;font-size:0.82rem;">
            <div style="font-weight:700;color:#1a5276;">👤 {member['name']}</div>
            <div style="color:#555;font-size:0.72rem;">{member['koperasi_id']}</div>
        </div>""", unsafe_allow_html=True)
        mc1, mc2 = st.columns(2)
        with mc1:
            if st.button("My Profile", key="_nav_profile", use_container_width=True):
                st.switch_page("pages/9_Member_Portal.py")
        with mc2:
            if st.button("Logout", key="_nav_logout", use_container_width=True):
                st.session_state.pop("member_logged_in", None)
                st.session_state.pop("member", None)
                st.rerun()
    else:
        if st.button("🔑 Member Login", use_container_width=True, key="_nav_login"):
            st.switch_page("pages/9_Member_Portal.py")


def apply_koponix_style():
    import streamlit as st
    st.markdown("""
    <style>
        /* Hide default Streamlit menu/footer */
        #MainMenu {visibility: hidden;}
        footer {visibility: hidden;}

        /* Global font */
        html, body, [class*="css"] {
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }

        /* Sidebar logo */
        .koponix-logo { text-align: center; padding: 0.5rem 0 1rem; }
        .koponix-logo h1 { font-size: 1.6rem; font-weight: 800; color: #1a5276; margin: 0; }
        .koponix-logo p  { font-size: 0.72rem; color: #666; margin: 0.15rem 0 0; }

        /* Category badge */
        .cat-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.72rem;
            font-weight: 600;
            color: white;
            margin-bottom: 6px;
        }

        /* Seller card */
        .seller-card {
            background: white;
            border: 1px solid #dde;
            border-radius: 12px;
            padding: 1rem 1.1rem;
            margin-bottom: 0.8rem;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
            transition: box-shadow 0.2s;
        }
        .seller-card:hover { box-shadow: 0 3px 12px rgba(0,0,0,0.12); }
        .seller-card h4 { margin: 0.2rem 0 0.1rem; color: #1a5276; font-size: 1rem; }
        .seller-card .meta { font-size: 0.78rem; color: #555; margin: 0.2rem 0; }
        .seller-card .desc { font-size: 0.82rem; color: #333; margin-top: 0.4rem;
                             border-top: 1px solid #eee; padding-top: 0.4rem; }

        /* Stat box */
        .stat-box {
            background: #eaf4fb;
            border-radius: 10px;
            text-align: center;
            padding: 1rem 0.5rem;
        }
        .stat-box .num { font-size: 2rem; font-weight: 800; color: #1a5276; }
        .stat-box .lbl { font-size: 0.78rem; color: #555; }

        /* How-it-works step */
        .step-box {
            background: #f8f9fa;
            border-left: 4px solid #1a5276;
            border-radius: 8px;
            padding: 0.9rem 1rem;
            margin-bottom: 0.6rem;
        }
        .step-box h4 { margin: 0 0 0.2rem; color: #1a5276; font-size: 0.95rem; }
        .step-box p  { margin: 0; font-size: 0.82rem; color: #555; }

        /* Hero */
        .hero {
            background: linear-gradient(135deg, #1a5276 0%, #2e86c1 100%);
            color: white;
            border-radius: 16px;
            padding: 2.5rem 2rem;
            margin-bottom: 1.5rem;
        }
        .hero h1 { margin: 0 0 0.5rem; font-size: 2rem; font-weight: 800; }
        .hero p  { margin: 0; font-size: 1rem; opacity: 0.9; }

        /* Status pill */
        .pill-active   { background:#d5f5e3; color:#1e8449; border-radius:12px;
                         padding:2px 10px; font-size:0.72rem; font-weight:600; }
        .pill-pending  { background:#fef9e7; color:#b7950b; border-radius:12px;
                         padding:2px 10px; font-size:0.72rem; font-weight:600; }

        /* Section header */
        .section-head {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1a5276;
            border-bottom: 2px solid #1a5276;
            padding-bottom: 0.3rem;
            margin-bottom: 1rem;
        }
    </style>
    """, unsafe_allow_html=True)


def sidebar_logo():
    import streamlit as st
    st.markdown("""
    <div class="koponix-logo">
        <h1>🤝 Koponix</h1>
        <p>Koperasi Digital Economy Platform</p>
    </div>
    """, unsafe_allow_html=True)
    st.divider()


def category_badge(category: str) -> str:
    color = CATEGORY_COLORS.get(category, "#607D8B")
    icon = CATEGORY_ICONS.get(category, "⭐")
    return f'<span class="cat-badge" style="background:{color}">{icon} {category}</span>'
