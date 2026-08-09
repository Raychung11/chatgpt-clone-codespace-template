/* ============================================================
   AI101 Platform — Floating AI Chat Widget
   Self-contained: injects HTML + CSS into the page
   ============================================================ */

(function () {
  'use strict';

  /* ── Knowledge base ── */
  const KB = [
    {
      keywords: ['marketplace', 'browse', 'agents', 'products', 'catalog', '101'],
      answer: 'We offer 101 AI agents across 8 categories — from customer service to finance and HR. Browse them all at <a href="/marketplace.php" class="cw-link">Marketplace</a>.'
    },
    {
      keywords: ['pricing', 'price', 'cost', 'plan', 'plans', 'subscription', 'pay', 'payment', 'fee'],
      answer: 'We have three plans: <strong>Starter $49/mo</strong>, <strong>Growth $149/mo</strong>, and <strong>Enterprise $399/mo</strong>. Save 20% with yearly billing! See full details on our <a href="/pricing.php" class="cw-link">Pricing page</a>.'
    },
    {
      keywords: ['trial', 'free', '14', 'day', 'days', 'try'],
      answer: 'Every plan includes a <strong>14-day free trial</strong> — no credit card required. <a href="/register.php" class="cw-link">Start your free trial now</a>!'
    },
    {
      keywords: ['about', 'company', 'who', 'team', 'founded', 'story', 'mission'],
      answer: 'AI101 was founded in 2022 to make AI automation accessible to small and medium businesses. Meet our team on the <a href="/about.php" class="cw-link">About page</a>.'
    },
    {
      keywords: ['contact', 'support', 'help', 'email', 'phone', 'talk', 'reach'],
      answer: 'Our support team is happy to help! Reach us at <strong>hello@ai101platform.com</strong> or visit our <a href="/contact.php" class="cw-link">Contact page</a> to send a message.'
    },
    {
      keywords: ['login', 'sign in', 'signin', 'account', 'log in'],
      answer: 'Already have an account? <a href="/login.php" class="cw-link">Login here</a>. Or <a href="/register.php" class="cw-link">register</a> to start your free 14-day trial.'
    },
    {
      keywords: ['register', 'signup', 'sign up', 'join', 'create account', 'get started'],
      answer: 'Getting started is easy! <a href="/register.php" class="cw-link">Create your account</a> and get 14 days free — no credit card needed.'
    },
    {
      keywords: ['cart', 'basket', 'checkout', 'buy', 'purchase', 'order'],
      answer: 'You can add AI agents to your cart and checkout securely. <a href="/cart.php" class="cw-link">View your cart</a> or <a href="/marketplace.php" class="cw-link">continue browsing</a>.'
    },
    {
      keywords: ['hr', 'human resources', 'employees', 'leave', 'payroll', 'recruitment', 'staff'],
      answer: 'Our HR AI agents handle employee management, leave tracking, payroll processing and recruitment. Check them out in the <a href="/marketplace.php?cat=hr-recruitment" class="cw-link">HR & Recruitment category</a>.'
    },
    {
      keywords: ['crm', 'sales', 'leads', 'deals', 'pipeline', 'customer relationship'],
      answer: 'Our CRM & Sales AI agents help you manage contacts, track deals, and automate follow-ups. Browse the <a href="/marketplace.php?cat=sales-marketing" class="cw-link">Sales & Marketing category</a>.'
    },
    {
      keywords: ['features', 'what can', 'what does', 'capabilities', 'integrations'],
      answer: 'AI101 agents can automate customer service, sales, HR, finance, marketing, and more. Each agent integrates with your existing tools. Explore all <a href="/marketplace.php" class="cw-link">101 agents</a>.'
    },
    {
      keywords: ['sme', 'small business', 'medium business', 'smb', 'startup'],
      answer: 'AI101 is purpose-built for SMEs — affordable, easy to deploy, and designed to tackle real business problems. <a href="/about.php" class="cw-link">Learn more about our mission</a>.'
    },
    {
      keywords: ['dashboard', 'account', 'my account', 'profile'],
      answer: 'Manage your subscriptions and AI agents from your personal <a href="/dashboard.php" class="cw-link">Dashboard</a>.'
    }
  ];

  const FALLBACK = "I can help you navigate! Try asking about <strong>pricing</strong>, our <strong>marketplace</strong>, <strong>free trial</strong>, or <strong>contact us</strong>.";
  const GREETING = "Hi! 👋 I'm your AI guide. Ask me about our platform, pricing, or any AI agent.";

  /* ── Style injection ── */
  const style = document.createElement('style');
  style.textContent = `
    /* Chat Widget Container */
    #cw-wrap {
      position: fixed;
      bottom: 24px;
      right: 24px;
      z-index: 99999;
      font-family: 'Inter', -apple-system, sans-serif;
    }

    /* Bubble button */
    #cw-bubble {
      width: 56px;
      height: 56px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--theme-primary, #6366f1), var(--theme-primary-dark, #4f46e5));
      border: none;
      color: #fff;
      font-size: 22px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      box-shadow: 0 4px 20px rgba(99,102,241,0.45);
      position: relative;
      transition: transform 0.2s, box-shadow 0.2s;
    }

    #cw-bubble:hover {
      transform: scale(1.08);
      box-shadow: 0 6px 28px rgba(99,102,241,0.60);
    }

    #cw-bubble::after {
      content: '';
      position: absolute;
      inset: -4px;
      border-radius: 50%;
      border: 2px solid var(--theme-primary, #6366f1);
      opacity: 0;
      animation: cw-pulse 2.4s ease-out infinite;
    }

    @keyframes cw-pulse {
      0%   { transform: scale(1);   opacity: 0.7; }
      70%  { transform: scale(1.4); opacity: 0; }
      100% { transform: scale(1.4); opacity: 0; }
    }

    /* Panel */
    #cw-panel {
      position: absolute;
      bottom: 68px;
      right: 0;
      width: 360px;
      height: 480px;
      background: rgba(17,17,24,0.97);
      border: 1px solid rgba(255,255,255,0.10);
      border-radius: 20px;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      box-shadow: 0 20px 60px rgba(0,0,0,0.5), 0 0 0 1px rgba(99,102,241,0.15);
      backdrop-filter: blur(20px);
      transition: opacity 0.25s, transform 0.25s;
      transform-origin: bottom right;
    }

    #cw-panel.cw-hidden {
      opacity: 0;
      transform: scale(0.92) translateY(10px);
      pointer-events: none;
    }

    /* Header */
    #cw-header {
      background: linear-gradient(135deg, var(--theme-primary, #6366f1), var(--theme-primary-dark, #4f46e5));
      padding: 14px 16px;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    #cw-avatar {
      width: 38px;
      height: 38px;
      background: rgba(255,255,255,0.2);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      flex-shrink: 0;
    }

    #cw-header-text { flex: 1; }
    #cw-header-text strong { display: block; color: #fff; font-size: 14px; font-weight: 600; }
    #cw-header-text span   { display: block; color: rgba(255,255,255,0.75); font-size: 11px; margin-top: 1px; }

    #cw-close {
      background: rgba(255,255,255,0.15);
      border: none;
      color: #fff;
      width: 28px;
      height: 28px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      font-size: 14px;
      transition: background 0.15s;
      flex-shrink: 0;
    }
    #cw-close:hover { background: rgba(255,255,255,0.28); }

    /* Messages */
    #cw-messages {
      flex: 1;
      overflow-y: auto;
      padding: 14px 14px 8px;
      display: flex;
      flex-direction: column;
      gap: 10px;
      scrollbar-width: thin;
      scrollbar-color: rgba(255,255,255,0.08) transparent;
    }
    #cw-messages::-webkit-scrollbar { width: 4px; }
    #cw-messages::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.10); border-radius: 2px; }

    .cw-msg {
      max-width: 84%;
      padding: 9px 13px;
      border-radius: 14px;
      font-size: 13px;
      line-height: 1.55;
      word-break: break-word;
      animation: cw-pop 0.22s ease;
    }

    @keyframes cw-pop {
      from { opacity: 0; transform: translateY(6px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .cw-msg.cw-ai {
      background: rgba(255,255,255,0.07);
      color: #dde2f0;
      border-radius: 4px 14px 14px 14px;
      align-self: flex-start;
    }

    .cw-msg.cw-user {
      background: var(--theme-primary, #6366f1);
      color: #fff;
      border-radius: 14px 14px 4px 14px;
      align-self: flex-end;
    }

    .cw-link {
      color: #a5b4fc;
      text-decoration: underline;
    }
    .cw-link:hover { color: #c7d2fe; }

    /* Thinking dots */
    .cw-thinking {
      display: flex;
      gap: 4px;
      padding: 10px 14px;
    }
    .cw-thinking span {
      width: 7px;
      height: 7px;
      background: rgba(255,255,255,0.35);
      border-radius: 50%;
      animation: cw-bounce 1.2s ease-in-out infinite;
    }
    .cw-thinking span:nth-child(2) { animation-delay: 0.18s; }
    .cw-thinking span:nth-child(3) { animation-delay: 0.36s; }

    @keyframes cw-bounce {
      0%, 60%, 100% { transform: translateY(0); }
      30%            { transform: translateY(-5px); }
    }

    /* Input area */
    #cw-footer {
      padding: 10px 12px;
      border-top: 1px solid rgba(255,255,255,0.07);
      display: flex;
      gap: 8px;
    }

    #cw-input {
      flex: 1;
      background: rgba(255,255,255,0.07);
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 10px;
      color: #e5e7eb;
      font-size: 13px;
      padding: 9px 12px;
      outline: none;
      font-family: inherit;
      transition: border-color 0.15s;
      resize: none;
    }
    #cw-input:focus { border-color: var(--theme-primary, #6366f1); }
    #cw-input::placeholder { color: rgba(255,255,255,0.25); }

    #cw-send {
      width: 38px;
      height: 38px;
      background: var(--theme-primary, #6366f1);
      border: none;
      border-radius: 10px;
      color: #fff;
      font-size: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: background 0.15s, transform 0.1s;
      flex-shrink: 0;
      align-self: flex-end;
    }
    #cw-send:hover { background: var(--theme-primary-dark, #4f46e5); }
    #cw-send:active { transform: scale(0.93); }

    @media (max-width: 480px) {
      #cw-panel { width: calc(100vw - 32px); right: 0; }
    }
  `;
  document.head.appendChild(style);

  /* ── HTML injection ── */
  const wrap = document.createElement('div');
  wrap.id = 'cw-wrap';
  wrap.innerHTML = `
    <div id="cw-panel" class="cw-hidden">
      <div id="cw-header">
        <div id="cw-avatar">🤖</div>
        <div id="cw-header-text">
          <strong>AI Assistant</strong>
          <span>Ask me anything about our platform</span>
        </div>
        <button id="cw-close" title="Close">✕</button>
      </div>
      <div id="cw-messages"></div>
      <div id="cw-footer">
        <input id="cw-input" type="text" placeholder="Ask a question…" autocomplete="off" maxlength="200">
        <button id="cw-send" title="Send">&#10148;</button>
      </div>
    </div>
    <button id="cw-bubble" title="Chat with AI">🤖</button>
  `;
  document.body.appendChild(wrap);

  /* ── DOM refs ── */
  const panel    = document.getElementById('cw-panel');
  const bubble   = document.getElementById('cw-bubble');
  const closeBtn = document.getElementById('cw-close');
  const messages = document.getElementById('cw-messages');
  const input    = document.getElementById('cw-input');
  const sendBtn  = document.getElementById('cw-send');

  let greeted = false;

  /* ── Helpers ── */
  function scrollBottom() {
    messages.scrollTop = messages.scrollHeight;
  }

  function addMsg(html, type) {
    const div = document.createElement('div');
    div.className = 'cw-msg cw-' + type;
    div.innerHTML = html;
    messages.appendChild(div);
    scrollBottom();
    return div;
  }

  function showThinking() {
    const el = document.createElement('div');
    el.className = 'cw-msg cw-ai cw-thinking';
    el.innerHTML = '<span></span><span></span><span></span>';
    messages.appendChild(el);
    scrollBottom();
    return el;
  }

  function findAnswer(query) {
    const q = query.toLowerCase();
    for (const entry of KB) {
      if (entry.keywords.some(kw => q.includes(kw))) {
        return entry.answer;
      }
    }
    return null;
  }

  function respond(userText) {
    addMsg(userText, 'user');
    const thinking = showThinking();

    setTimeout(function () {
      thinking.remove();
      const answer = findAnswer(userText) || FALLBACK;
      addMsg(answer, 'ai');
    }, 700 + Math.random() * 500);
  }

  /* ── Open / close ── */
  function openPanel() {
    panel.classList.remove('cw-hidden');
    sessionStorage.setItem('cw_open', '1');
    if (!greeted) {
      greeted = true;
      setTimeout(function () { addMsg(GREETING, 'ai'); }, 300);
    }
    setTimeout(function () { input.focus(); }, 400);
  }

  function closePanel() {
    panel.classList.add('cw-hidden');
    sessionStorage.setItem('cw_open', '0');
  }

  bubble.addEventListener('click', function () {
    if (panel.classList.contains('cw-hidden')) {
      openPanel();
    } else {
      closePanel();
    }
  });

  closeBtn.addEventListener('click', closePanel);

  /* ── Send message ── */
  function sendMessage() {
    const text = input.value.trim();
    if (!text) return;
    input.value = '';
    respond(text);
  }

  sendBtn.addEventListener('click', sendMessage);
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  });

  /* ── Restore session state ── */
  if (sessionStorage.getItem('cw_open') === '1') {
    openPanel();
  }

})();
