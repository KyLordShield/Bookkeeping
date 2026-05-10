<?php
/**
 * client_chatbot.php
 * Place in: /public/client_pages/client_chatbot.php
 */
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['client_id'])) {
    header("Location: ../../login_page.php");
    exit();
}

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../classes/Client.php';


/**
 * Chat backend:
 * - If CHAT_API_URL is set (e.g. https://your-api.onrender.com), the page POSTs to {CHAT_API_URL}/chat (Flask).
 * - Otherwise uses the PHP proxy (public/ajax/chatbot_proxy.php) — needs GROQ_API_KEY in project .env; no separate Python server.
 */
$chatApiUrl = getenv('CHAT_API_URL');
if (is_string($chatApiUrl) && $chatApiUrl !== '') {
    $chatEndpoint = rtrim($chatApiUrl, '/') . '/chat';
} else {
    $chatEndpoint = '../ajax/chatbot_proxy.php';
}


$clientId   = (int)$_SESSION['client_id'];
$client     = Client::findById($clientId);
$clientName = $client
    ? htmlspecialchars($client['first_name'] . ' ' . $client['last_name'])
    : 'Client';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approvative Assistant</title>

    <link rel="stylesheet" href="../assets/css_file/client_pages.css">
    <link rel="stylesheet" href="../assets/css_file/navigation_bar.css">
    <link rel="stylesheet" href="../assets/css_file/chatbot.css">
</head>
<body>
<div class="container">
    <?php include '../partials/navigation_bar.php'; ?>

    <div class="main-content">

        <div class="chatbot-page-wrapper">

            <!-- Header -->
            <div class="chat-header-card">
                <div class="chat-header-avatar">
                    <svg class="chat-header-avatar-svg" width="32" height="32" viewBox="0 0 24 24" fill="none"
                         stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        <circle cx="9"  cy="10" r="1" fill="#ffffff" stroke="none"/>
                        <circle cx="12" cy="10" r="1" fill="#ffffff" stroke="none"/>
                        <circle cx="15" cy="10" r="1" fill="#ffffff" stroke="none"/>
                    </svg>
                </div>
                <div class="chat-header-info">
                    <div class="chat-header-name">Approvative Assistant</div>
                    <div class="chat-header-status">
                        <span class="chat-status-dot"></span>
                        Online &mdash; Ask me anything about our services
                    </div>
                </div>
                <a href="client_dashboard.php" class="chat-back-link">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="19" y1="12" x2="5" y2="12"/>
                        <polyline points="12 19 5 12 12 5"/>
                    </svg>
                    Back to Dashboard
                </a>
            </div>

            <!-- Messages -->
            <div class="chat-messages-area" id="chatMessages">

                <!-- Welcome message -->
                <div class="chat-msg chat-msg--bot">
                    <div class="chat-msg-avatar-placeholder">AI</div>
                    <div class="chat-msg-bubble">
                        Hi <strong><?= $clientName ?></strong>! I'm the <strong>Approvative Assistant</strong>. 
                        I can help you with questions about our services, document requirements, 
                        processing times, and how to use ConsultWise. How can I help you today?
                    </div>
                    <div class="chat-msg-time" id="welcomeTime"></div>
                </div>

                <!-- Quick suggestion chips -->
                <div class="chat-suggestions" id="chatSuggestions">
                    <button class="chat-chip" onclick="chatSend('What services does Approvative offer?')">What services do you offer?</button>
                    <button class="chat-chip" onclick="chatSend('What documents do I need to submit?')">What documents do I need?</button>
                    <button class="chat-chip" onclick="chatSend('How long does document processing take?')">Processing times?</button>
                    <button class="chat-chip" onclick="chatSend('How do I track my application status?')">How to track my application?</button>
                    <button class="chat-chip" onclick="chatSend('How do I schedule a consultation?')">Schedule a consultation</button>
                </div>

                <!-- Typing indicator -->
                <div class="chat-typing" id="chatTyping">
                    <span></span><span></span><span></span>
                </div>

            </div>

            <!-- Input -->
            <div class="chat-input-area">
                <textarea
                    class="chat-input"
                    id="chatInput"
                    placeholder="Type your question here..."
                    rows="1"
                    onkeydown="chatHandleKey(event)"
                    oninput="chatAutoResize(this)"
                ></textarea>
                <button class="chat-send-btn" id="chatSendBtn" onclick="chatSend()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="22" y1="2" x2="11" y2="13"/>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                    </svg>
                </button>
            </div>

            <div class="chat-disclaimer">
                Approvative Assistant provides general guidance only. For official decisions, please contact our staff directly.
            </div>

        </div><!-- end chatbot-page-wrapper -->

    </div><!-- end main-content -->
</div><!-- end container -->

<script>
// ---- State ----
// Gemini uses 'user' and 'model' roles (not 'assistant')
let chatHistory = [];
let chatLoading = false;

// ---- Set welcome time ----
document.getElementById('welcomeTime').textContent = getTime();

// ---- Helpers ----
function getTime() {
    return new Date().toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });
}

function chatAutoResize(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 110) + 'px';
}

function chatHandleKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        chatSend();
    }
}

function scrollToBottom() {
    const area = document.getElementById('chatMessages');
    area.scrollTop = area.scrollHeight;
}

// ---- Append a message bubble ----
function appendMessage(text, role) {
    const area = document.getElementById('chatMessages');

    // Hide suggestion chips after first user message
    if (role === 'user') {
        const chips = document.getElementById('chatSuggestions');
        if (chips) chips.style.display = 'none';
    }

    // Insert before the typing indicator
    const typingEl = document.getElementById('chatTyping');

    const msgDiv = document.createElement('div');
    msgDiv.className = `chat-msg chat-msg--${role === 'user' ? 'user' : 'bot'}`;

    if (role !== 'user') {
        const avatar = document.createElement('div');
        avatar.className = 'chat-msg-avatar-placeholder';
        avatar.textContent = 'AI';
        msgDiv.appendChild(avatar);
    }

    const bubble = document.createElement('div');
    bubble.className = 'chat-msg-bubble';

    // Basic formatting: **bold**, newlines
    bubble.innerHTML = text
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/\n/g, '<br>');

    const timeEl = document.createElement('div');
    timeEl.className = 'chat-msg-time';
    timeEl.textContent = getTime();

    msgDiv.appendChild(bubble);
    msgDiv.appendChild(timeEl);

    area.insertBefore(msgDiv, typingEl);
    scrollToBottom();
}

// ---- Set loading state ----
function setLoading(loading) {
    chatLoading = loading;
    const typing  = document.getElementById('chatTyping');
    const sendBtn = document.getElementById('chatSendBtn');
    const input   = document.getElementById('chatInput');

    typing.style.display  = loading ? 'flex' : 'none';
    sendBtn.disabled      = loading;
    input.disabled        = loading;

    if (loading) scrollToBottom();
    else input.focus();
}

// ---- Send message ----
async function chatSend(prefill = null) {
    if (chatLoading) return;

    const input = document.getElementById('chatInput');
    const text  = (prefill || input.value).trim();
    if (!text) return;

    input.value = '';
    input.style.height = 'auto';

    appendMessage(text, 'user');

    // Add to history (Gemini format: role = 'user' or 'model')
    chatHistory.push({ role: 'user', content: text });

    setLoading(true);

    try {
        const res = await fetch('<?= htmlspecialchars($chatEndpoint, ENT_QUOTES, 'UTF-8') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ messages: chatHistory })
        });

        if (!res.ok) throw new Error('Server responded with ' + res.status);

        const data = await res.json();

        if (data.error) throw new Error(data.error);

        const reply = data.reply || "I'm sorry, I couldn't process that. Please try again.";
        appendMessage(reply, 'bot');

        // Add bot reply to history
        chatHistory.push({ role: 'model', content: reply });

        // Keep history manageable
        if (chatHistory.length > 20) chatHistory = chatHistory.slice(-20);

    } catch (err) {
        console.error('Chatbot error:', err);
        appendMessage(
            "Sorry, I'm having trouble connecting right now. Please try again in a moment, or contact our staff directly for assistance.",
            'bot'
        );
    }

    setLoading(false);
}
</script>

</body>
</html>