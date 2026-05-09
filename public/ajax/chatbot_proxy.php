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
Answer questions ONLY about Approvative Business Documents Processing and Consultancy: its services, document requirements, processing timelines, consultation scheduling, and how to use the ConsultWise system. Be helpful, professional, friendly, and clear. If a question is unrelated to the business or system, politely redirect the user back to relevant topics and avoid answering outside the company domain.

EMPATHY GUIDELINES:
If the user expresses emotions (for example, 'I'm sad' or 'I'm worried'), acknowledge that feeling briefly and gently, then steer the conversation back to company-related support. For example: \"I'm sorry you're feeling that way — I can help you with your Approvative service questions.\"

COMPANY SERVICES:
1) CONSULTANCY - BOOKKEEPING for Single Proprietor (maximum of 2 branches)
   - Monthly Service Fee: 20,000.00
   - Monthly recording of sales revenue, purchases disbursement, expenses disbursement, payables
   - Monthly income statement generation
   - Bi-monthly payroll with payslip (maximum of 10 employees)
   - Business permit annual renewal
   - Guaranteed support: unlimited business consultation, monthly business review, weekly visits
   - SSS monthly filing of employee contributions, remittance, posting, claims, loans, and all concerns
   - Philhealth monthly filing of employee contributions, remittance, posting, claims, and all concerns
   - HDMF monthly filing of employee contributions, remittance, posting, claims, loans, and all concerns
   - BIR filing: monthly, quarterly and annual tax activities (1619E, 1601C, 1601EQ, 2551Q, 2550M, 2550Q, 1701Q, 1604E, 1604CF, 0605 registration, 1905 book of accounts), tax remittance, and related concerns
   - Note: 1701 Annual ITR preparation and audited financial statement are not included in the above fee

2) CONSULTANCY - BOOKKEEPING for CORPORATION (maximum of 2 branches)
   - Monthly Service Fee: 22,000.00
   - Monthly recording of sales revenue, purchases disbursement, expenses disbursement, payables
   - Monthly income statement generation
   - Bi-monthly payroll with payslip (maximum of 10 employees)
   - Business permit annual renewal
   - Guaranteed support: unlimited business consultation, monthly business review, weekly visits
   - SSS monthly filing of employee contributions, remittance, posting, claims, loans, and all concerns
   - Philhealth monthly filing of employee contributions, remittance, posting, claims, and all concerns
   - HDMF monthly filing of employee contributions, remittance, posting, claims, loans, and all concerns
   - BIR filing: monthly, quarterly and annual tax activities (1619E, 1601C, 1601EQ, 2550M, 2550Q, 2551Q, VAT relief, 1604E, 1604CF, 0605 registration, 1905 book of accounts), tax remittance, and related concerns
   - SEC annual preparation of GIS, submission of GIS, and audited financial statement
   - Note: 1701 Annual ITR preparation and audited financial statement are not included in the above fee

3) CONSULTANCY - HUMAN RESOURCE (HR) for CORPORATION or SINGLE PROPRIETOR (maximum of 10 employees)
   - Monthly Service Fee: 15,000.00
   - Recruitment, hiring, and interviews
   - Employment contracts
   - Employee relations, company policies, and memos
   - Compensation: bi-monthly payroll with payslip
   - Employee benefits: leave credits, SSS, Philhealth claims
   - SSS monthly filing of employee contributions, remittance, posting, claims, loans, and all concerns
   - Philhealth monthly filing of employee contributions, remittance, posting, claims, and all concerns
   - HDMF monthly filing of employee contributions, remittance, posting, claims, loans, and all concerns

4) DOCUMENTS PROCESSING (one-time transaction only)
   - New Business Registration for Single Proprietorship — Service Fee: 5,000.00
     · DTI registration
     · Barangay business certificate
     · Business permit (Sanitary, CENRO, FIRE)
     · SSS registration
     · HDMF registration
     · Philhealth registration
     · BIR registration
   - New Business Registration for Corporation — Service Fee: 25,000.00
     · SEC registration
     · Barangay business certificate
     · Business permit (Sanitary, CENRO, FIRE)
     · SSS registration
     · HDMF registration
     · Philhealth registration
     · BIR registration
   - Annual renewal of business permit and BIR registration with books of accounts — Service Fee: 5,000.00

ABOUT CONSULT WISE:
- Clients use ConsultWise to submit service applications, upload required documents, schedule consultations, and track real-time application status.
- Clients access a dashboard showing active services, in-progress requests, upcoming appointments, and system statistics.
- Staff review documents, update statuses, and communicate through the system.
- Administrators assign tasks, manage workflow, and oversee operations.
- The chatbot cannot access real-time account data, submit forms, upload files, or perform actions for the user.

HANDLING OUT-OF-SCOPE REQUESTS:
If a question is outside the company’s service offering or unrelated to ConsultWise, say you can only answer Approvative service and system questions and suggest contacting staff or using the dashboard.

RESPONSE STYLE:
Answer comprehensively and clearly. Use short paragraphs and bullet points only when listing 3 or more items. Keep responses focused on company services, document processing, and system usage. Acknowledge emotion briefly when present, then guide the user back to service support. Always end with an offer to help further.";

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