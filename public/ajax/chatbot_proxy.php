<?php
/**
 * chatbot_proxy.php
 * Place in: /public/ajax/chatbot_proxy.php
 *
 * Server-side proxy to Groq API.
 * Get your FREE key at: https://console.groq.com
 */

session_start();
// ---- Load .env (for local development) ----
$envPath = __DIR__ . '/../../.env';

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . '=' . trim($value));
    }
}

header('Content-Type: application/json');

// ---- Only logged-in clients can use this ----
if (!isset($_SESSION['user_id'])) {
    error_log("Chatbot: Unauthorized access attempt - no session user_id");
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ---- Only POST requests ----
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Chatbot: Invalid request method - " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ---- Parse request body ----
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['messages']) || !is_array($input['messages'])) {
    error_log("Chatbot: Invalid request body - " . print_r($input, true));
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$messages = array_slice($input['messages'], -20); // keep last 20 only

// ---- Validate message structure ----
foreach ($messages as $msg) {
    if (!in_array($msg['role'] ?? '', ['user', 'assistant', 'model'])) {
        error_log("Chatbot: Invalid message role - " . ($msg['role'] ?? 'null'));
        http_response_code(400);
        echo json_encode(['error' => 'Invalid message role']);
        exit;
    }
}

// ---- API Key ----
$apiKey = getenv('GROQ_API_KEY');
// LOCAL DEV ONLY - replace with your Groq key:


if (!$apiKey) {
    error_log("Chatbot: Groq API key is not configured");
    http_response_code(500);
    echo json_encode(['error' => 'API key not configured. Contact the administrator.']);
    exit;
}

error_log("Chatbot: Request received from user_id=" . $_SESSION['user_id'] . " with " . count($messages) . " message(s)");

// ---- System instruction (business context) ----
$systemInstruction = "You are the Approvative Assistant — the AI support chatbot for Approvative Business Documents Processing and Consultancy, embedded in ConsultWise, their web-based service request and document processing system.

YOUR ROLE:
Answer questions ONLY about Approvative Business Documents Processing and Consultancy: its services, document requirements, processing timelines, consultation scheduling, and how to use the ConsultWise system. Be helpful, professional, friendly, and concise. If a question is unrelated to the business or system, politely redirect the user back to relevant topics.

ABOUT THE BUSINESS:
Approvative Business Documents Processing and Consultancy is a consultancy firm that helps clients process business documents and related services. They replaced manual, fragmented processes with ConsultWise — a centralized web-based platform.

ABOUT CONSULT WISE SYSTEM:
- Clients can submit service applications, upload required documents, schedule consultations, and track real-time application status.
- Three user roles: clients, staff members, and administrators.
- Client dashboard shows: active services, in-progress work, upcoming approved service requests, and stats (total services, in-progress, completed).
- Clients submit requests by selecting a service type, uploading documents, and choosing a preferred date/time.
- Staff review documents, update statuses, and communicate through the system.
- Admins manage workflow, assign tasks to staff, and monitor all operations.

SERVICES:
The firm processes various business documents. Services include business registration documents, permits, licenses, and related consultancy. Each service type has specific document requirements. Clients can browse available services and submit requests through their dashboard.

DOCUMENT REQUIREMENTS:
- Requirements vary per service type.
- After a service request is submitted, staff or admin will specify the exact documents needed.
- Clients upload documents directly through the system.
- Common documents often include valid IDs, business permits, certificates, and financial records depending on the service.

PROCESSING TIMES:
- Processing times vary by service complexity.
- Clients can check real-time status updates from their dashboard at any time.
- Average processing times are tracked in the system's analytics.

CONSULTATION APPOINTMENTS:
- Clients schedule consultations through the system.
- Approved service requests with preferred dates appear as upcoming events on the dashboard.
- Office hours apply for scheduling.

HOW TO USE THE SYSTEM (step by step):
1. Log in to your account.
2. Go to your Client Dashboard for an overview.
3. Navigate to Services to browse and request a service.
4. Upload required documents when prompted.
5. Track your application status from the dashboard.
6. Check upcoming events for your scheduled appointments.

LIMITATIONS TO COMMUNICATE TO USERS:
- You cannot access real-time account data or specific application details — direct users to their dashboard.
- You cannot submit forms, upload files, or take actions on behalf of users.
- For complex legal or business advice, always recommend speaking with a human consultant.
- For urgent concerns, users should contact staff directly.

Keep responses concise (2-4 sentences unless listing steps). Use bullet points only for lists of 3 or more items. Always be warm and professional. End responses with an offer to help further if needed.";

// ---- Build Groq request payload ----
// Groq uses OpenAI-compatible format with 'user' and 'assistant' roles
$groqMessages = [
    ['role' => 'system', 'content' => $systemInstruction]
];

foreach ($messages as $msg) {
    // Convert Gemini 'model' role to Groq 'assistant' role
    $role = $msg['role'] === 'model' ? 'assistant' : $msg['role'];
    $groqMessages[] = [
        'role'    => $role,
        'content' => mb_substr($msg['content'] ?? '', 0, 2000)
    ];
}

$payload = json_encode([
    'model'       => 'llama-3.3-70b-versatile',
    'messages'    => $groqMessages,
    'max_tokens'  => 600,
    'temperature' => 0.7,
]);

// ---- Call Groq API ----
$url = "https://api.groq.com/openai/v1/chat/completions";

error_log("Chatbot: Sending request to Groq API - model: llama-3.3-70b-versatile");

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false, // Required for XAMPP localhost
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// ---- Handle curl errors ----
if ($curlError) {
    error_log("Chatbot: Curl error - " . $curlError);
    http_response_code(500);
    echo json_encode(['error' => 'Connection error. Please try again.']);
    exit;
}

error_log("Chatbot: Groq API HTTP Code - " . $httpCode);
error_log("Chatbot: Groq API Response - " . $response);

// ---- Handle API errors ----
if ($httpCode !== 200) {
    error_log("Chatbot: Groq API error $httpCode: $response");
    http_response_code(500);
    echo json_encode(['error' => 'AI service temporarily unavailable. Please try again.']);
    exit;
}

// ---- Parse and return Groq response ----
$data = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("Chatbot: Failed to parse Groq JSON response - " . json_last_error_msg());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to parse AI response. Please try again.']);
    exit;
}

$text = $data['choices'][0]['message']['content'] ?? null;

if (!$text) {
    error_log("Chatbot: Empty response from Groq - " . print_r($data, true));
    http_response_code(500);
    echo json_encode(['error' => 'Empty response from AI. Please try again.']);
    exit;
}

error_log("Chatbot: Successfully generated reply for user_id=" . $_SESSION['user_id']);

echo json_encode(['reply' => $text]);