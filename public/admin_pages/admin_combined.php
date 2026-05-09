<?php
// admin_combined.php
// Combines: admin_client_manage.php + admin_task.php + admin_approval.php

session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login_page.php");
    exit();
}

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../classes/Client.php';
require_once __DIR__ . '/../../classes/Service.php';
require_once __DIR__ . '/../../classes/ServiceRequest.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Staff.php';
require_once __DIR__ . '/../../classes/Notification.php';

$current_staff_id = $_SESSION['staff_id'] ?? 1;
$admin_user_id    = $_SESSION['user_id'];

// ─────────────────────────────────────────────────────────────────────────────
// AJAX / POST handler  (client management actions)
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    try {
        switch ($action) {

            case 'add_client':
                $data = [
                    'first_name'        => trim($_POST['first_name'] ?? ''),
                    'last_name'         => trim($_POST['last_name'] ?? ''),
                    'email'             => trim($_POST['email'] ?? ''),
                    'phone'             => trim($_POST['phone'] ?? ''),
                    'company_name'      => trim($_POST['company_name'] ?? ''),
                    'business_type'     => trim($_POST['business_type'] ?? ''),
                    'registration_date' => date('Y-m-d'),
                ];
                if (empty($data['first_name']) || empty($data['last_name']) || empty($data['email']))
                    throw new Exception("First Name, Last Name and Email are required.");
                $service_id = (int)($_POST['service_id'] ?? 0);
                if ($service_id <= 0) throw new Exception("Please select a service.");
                if (Client::emailExists($data['email'])) throw new Exception("This email is already registered.");
                if (!empty($data['phone']) && Client::phoneExists($data['phone'])) throw new Exception("This phone number is already registered.");
                $client_id = Client::create($data);
                if (!$client_id) throw new Exception("Failed to create client");
                Client::assignService($client_id, $service_id, $current_staff_id, null, 'pending');
                echo json_encode(['success' => true, 'client_id' => $client_id]);
                break;

            case 'add_service_to_existing':
                $client_id  = (int)($_POST['client_id'] ?? 0);
                $service_id = (int)($_POST['service_id'] ?? 0);
                if ($client_id <= 0 || $service_id <= 0) throw new Exception("Invalid client or service ID");
                Client::assignService($client_id, $service_id, $current_staff_id, null, 'pending');
                echo json_encode(['success' => true]);
                break;

            case 'edit_client':
                $client_id = (int)($_POST['client_id'] ?? 0);
                if ($client_id <= 0) throw new Exception("Invalid client ID");
                $data = [
                    'first_name'    => trim($_POST['first_name'] ?? ''),
                    'last_name'     => trim($_POST['last_name'] ?? ''),
                    'email'         => trim($_POST['email'] ?? ''),
                    'phone'         => trim($_POST['phone'] ?? ''),
                    'company_name'  => trim($_POST['company_name'] ?? ''),
                    'business_type' => trim($_POST['business_type'] ?? ''),
                ];
                if (empty($data['first_name']) || empty($data['last_name']) || empty($data['email']))
                    throw new Exception("First Name, Last Name and Email are required.");
                if (Client::emailExists($data['email'], $client_id)) throw new Exception("Email already in use.");
                if (!empty($data['phone']) && Client::phoneExists($data['phone'], $client_id)) throw new Exception("Phone already in use.");
                Client::update($client_id, $data);
                $serviceUpdated = false;
                if (isset($_POST['client_service_id'], $_POST['new_service_id'])) {
                    $cs_id = (int)$_POST['client_service_id'];
                    $ns_id = (int)$_POST['new_service_id'];
                    if ($cs_id > 0 && $ns_id > 0) {
                        $db   = Database::getInstance()->getConnection();
                        $stmt = $db->prepare("UPDATE client_services SET service_id=? WHERE client_service_id=? AND overall_status='pending'");
                        $stmt->execute([$ns_id, $cs_id]);
                        $serviceUpdated = $stmt->rowCount() > 0;
                    }
                }
                echo json_encode(['success' => true, 'service_updated' => $serviceUpdated]);
                break;

            case 'create_user':
                $client_id = (int)($_POST['client_id'] ?? 0);
                $username  = trim($_POST['username'] ?? '');
                $password  = $_POST['password'] ?? '';
                if ($client_id <= 0 || empty($username) || strlen($password) < 6)
                    throw new Exception("Invalid input: username or password too short");
                $error_msg = '';
                $success   = User::createClientUser($client_id, $username, $password, true, $error_msg);
                echo json_encode(['success' => $success, 'message' => $success ? 'Account created successfully!' : $error_msg]);
                break;

            case 'accept_request':
                $request_id = (int)($_POST['request_id'] ?? 0);
                if ($request_id <= 0) throw new Exception("Invalid request ID");
                if (!ServiceRequest::accept($request_id, $current_staff_id))
                    throw new Exception("Failed to accept request");
                $req = ServiceRequest::getById($request_id);
                $client_service_id = null;
                if ($req) {
                    $client_service_id = Client::assignService(
                        $req['client_id'], $req['service_id'],
                        $current_staff_id, $request_id, 'pending'
                    );
                }
                echo json_encode([
                    'success'           => true,
                    'client_service_id' => $client_service_id,
                    'client_id'         => $req['client_id'] ?? null,
                    'service_id'        => $req['service_id'] ?? null,
                ]);
                break;

            case 'reject_request':
                $request_id = (int)($_POST['request_id'] ?? 0);
                echo json_encode(['success' => ServiceRequest::reject($request_id)]);
                break;

            case 'get_client':
                $client_id = (int)($_POST['client_id'] ?? 0);
                $client    = Client::findById($client_id);
                if ($client) {
                    $clientServices            = Client::getClientServices($client_id);
                    $client['pending_service'] = null;
                    $client['active_services'] = [];
                    foreach ($clientServices as $cs) {
                        if ($cs['overall_status'] === 'pending') {
                            if (!$client['pending_service'])
                                $client['pending_service'] = [
                                    'client_service_id' => $cs['client_service_id'],
                                    'service_id'        => $cs['service_id'],
                                    'service_name'      => $cs['service_name'],
                                ];
                        } else {
                            $client['active_services'][] = [
                                'service_id'   => $cs['service_id'],
                                'service_name' => $cs['service_name'],
                                'status'       => $cs['overall_status'],
                            ];
                        }
                    }
                }
                echo json_encode($client ?: []);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Load data  — Client Management tab
// ─────────────────────────────────────────────────────────────────────────────
$clients          = Client::getAll() ?? [];
$services         = Service::getAllActive() ?? [];
$pending_requests = ServiceRequest::getAllPending() ?? [];
$pending_count    = count($pending_requests);

$staffObj = new Staff();
$allStaff = $staffObj->getAllStaffWithStats();

$pdo = Database::getInstance()->getConnection();

function runPythonRequirementMiner(array $payload): ?array
{
    $scriptPath = realpath(__DIR__ . '/../../scripts/requirement_miner.py');
    if (!$scriptPath || !is_file($scriptPath)) return null;

    $tempInput = tempnam(sys_get_temp_dir(), 'reqmine_');
    if (!$tempInput) return null;
    if (file_put_contents($tempInput, json_encode($payload, JSON_UNESCAPED_UNICODE)) === false) {
        @unlink($tempInput);
        return null;
    }

    $commands = [
        'python ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($tempInput),
        'py -3 ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($tempInput),
    ];

    foreach ($commands as $cmd) {
        $output = [];
        $exitCode = 1;
        exec($cmd . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) continue;

        $decoded = json_decode(implode("\n", $output), true);
        if (is_array($decoded)) {
            @unlink($tempInput);
            return $decoded;
        }
    }

    @unlink($tempInput);
    return null;
}

$txRows = $pdo->query("
    SELECT cs.service_id, cs.client_service_id, csr.requirement_name
    FROM client_services cs
    JOIN client_service_requirements csr ON csr.client_service_id = cs.client_service_id
    WHERE csr.requirement_name IS NOT NULL AND TRIM(csr.requirement_name) != ''
    ORDER BY cs.service_id, cs.client_service_id
")->fetchAll(PDO::FETCH_ASSOC);

$serviceTransactions = [];
foreach ($txRows as $row) {
    $sid = (int)$row['service_id'];
    $csid = (int)$row['client_service_id'];
    $name = trim((string)$row['requirement_name']);
    if ($sid <= 0 || $csid <= 0 || $name === '') continue;

    $normalized = preg_replace('/\s+/', ' ', $name);
    if (!isset($serviceTransactions[$sid])) $serviceTransactions[$sid] = [];
    if (!isset($serviceTransactions[$sid][$csid])) $serviceTransactions[$sid][$csid] = [];
    $serviceTransactions[$sid][$csid][$normalized] = true;
}

$serviceTxForPython = [];
foreach ($serviceTransactions as $sid => $txMap) {
    $serviceTxForPython[(string)$sid] = array_map(
        static fn($itemsMap) => array_keys($itemsMap),
        array_values($txMap)
    );
}

$stepSuggestionsRaw = $pdo->query("
    SELECT cs.service_id, csr.requirement_name, COUNT(*) AS usage_count
    FROM client_service_requirements csr
    JOIN client_services cs ON csr.client_service_id = cs.client_service_id
    WHERE csr.requirement_name IS NOT NULL AND csr.requirement_name != ''
    GROUP BY cs.service_id, csr.requirement_name
    ORDER BY cs.service_id, usage_count DESC
")->fetchAll(PDO::FETCH_ASSOC);

$serviceTotalsRaw = $pdo->query("
    SELECT cs.service_id, COUNT(DISTINCT cs.client_service_id) AS total_service_requests
    FROM client_services cs
    GROUP BY cs.service_id
")->fetchAll(PDO::FETCH_ASSOC);

$serviceTotals = [];
foreach ($serviceTotalsRaw as $row) {
    $serviceTotals[(int)$row['service_id']] = (int)$row['total_service_requests'];
}

$serviceNames = [];
foreach ($services as $svc) {
    $serviceNames[(int)$svc['service_id']] = $svc['service_name'];
}

$stepSuggestionsByService = [];
$stepInsightsByService = [];
$pythonMined = runPythonRequirementMiner([
    'service_transactions' => $serviceTxForPython,
    'min_confidence' => 0.60,
    'min_support_count' => 1,
    'min_suggestions_floor' => 3,
    'max_suggestions_cap' => 7,
]);

$pythonServices = is_array($pythonMined['services'] ?? null) ? $pythonMined['services'] : [];
foreach ($stepSuggestionsRaw as $row) {
    $sid = (int)$row['service_id'];
    $reqName = trim((string)$row['requirement_name']);
    $usage = (int)$row['usage_count'];
    if ($reqName === '' || $usage <= 0) continue;

    if (!isset($stepSuggestionsByService[$sid])) $stepSuggestionsByService[$sid] = [];
    if (!isset($stepInsightsByService[$sid])) $stepInsightsByService[$sid] = [];
    if (count($stepSuggestionsByService[$sid]) < 8)
        $stepSuggestionsByService[$sid][] = $reqName;

    $totalRequests = $serviceTotals[$sid] ?? 0;
    $confidence = $totalRequests > 0 ? round(($usage / $totalRequests) * 100, 1) : 0;
    if (count($stepInsightsByService[$sid]) < 10) {
        $stepInsightsByService[$sid][] = [
            'requirement_name' => $reqName,
            'usage_count' => $usage,
            'total_requests' => $totalRequests,
            'confidence_pct' => $confidence,
        ];
    }
}

foreach ($stepSuggestionsByService as $sid => $suggestions) {
    $serviceTx = $serviceTransactions[$sid] ?? [];
    $avgSteps = 0.0;
    if (!empty($serviceTx)) {
        $counts = array_map(static fn($itemsMap) => count($itemsMap), $serviceTx);
        $avgSteps = array_sum($counts) / max(1, count($counts));
    }
    $dynamicLimit = min(7, max(3, (int)round($avgSteps > 0 ? $avgSteps : 3)));

    $rowsForService = array_values(array_filter(
        $stepInsightsByService[$sid] ?? [],
        static fn($it) => (float)$it['confidence_pct'] >= 60.0
    ));
    usort($rowsForService, static function ($a, $b) {
        if ((int)$a['usage_count'] === (int)$b['usage_count']) {
            return strcmp((string)$a['requirement_name'], (string)$b['requirement_name']);
        }
        return (int)$b['usage_count'] <=> (int)$a['usage_count'];
    });

    $fallbackSuggestions = array_slice(
        array_map(static fn($it) => (string)$it['requirement_name'], $rowsForService),
        0,
        $dynamicLimit
    );
    if (empty($fallbackSuggestions) && !empty($suggestions)) {
        $fallbackSuggestions = [ (string)$suggestions[0] ];
    }

    $servicePython = $pythonServices[(string)$sid] ?? null;
    if (is_array($servicePython)) {
        $pySuggestions = [];
        foreach (($servicePython['suggestions'] ?? []) as $name) {
            $name = trim((string)$name);
            if ($name !== '') $pySuggestions[] = $name;
        }
        $stepSuggestionsByService[$sid] = !empty($pySuggestions) ? $pySuggestions : $fallbackSuggestions;

        $pyInsights = [];
        foreach (($servicePython['insights'] ?? []) as $it) {
            $n = trim((string)($it['requirement_name'] ?? ''));
            $u = (int)($it['usage_count'] ?? 0);
            $t = (int)($it['total_requests'] ?? 0);
            $c = (float)($it['confidence_pct'] ?? 0);
            if ($n === '' || $u <= 0 || $t <= 0) continue;
            $pyInsights[] = [
                'requirement_name' => $n,
                'usage_count' => $u,
                'total_requests' => $t,
                'confidence_pct' => $c,
            ];
        }
        if (!empty($pyInsights)) $stepInsightsByService[$sid] = array_slice($pyInsights, 0, 10);
    } else {
        $stepSuggestionsByService[$sid] = $fallbackSuggestions;
    }
}

$clientServiceRows = [];
foreach ($clients as $client) {
    $clientServices = Client::getClientServices($client['client_id']);
    if (empty($clientServices)) {
        $clientServiceRows[] = [
            'client'            => $client,
            'service'           => null,
            'client_service_id' => null,
            'status'            => 'pending',
            'has_requirements'  => false,
        ];
    } else {
        foreach ($clientServices as $cs) {
            $clientServiceRows[] = [
                'client'            => $client,
                'service'           => $cs,
                'client_service_id' => $cs['client_service_id'],
                'status'            => $cs['overall_status'],
                'has_requirements'  => Client::countRequirements($cs['client_service_id']) > 0,
            ];
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Load data  — Task Management tab
// ─────────────────────────────────────────────────────────────────────────────
$statusFilter = $_GET['status'] ?? 'all';
$search       = trim($_GET['search'] ?? '');
$activeTab    = $_GET['tab'] ?? 'clients';

$where  = [];
$params = [];
if ($statusFilter === 'new')         $where[] = "cs.overall_status = 'pending'";
elseif ($statusFilter === 'in_progress') $where[] = "cs.overall_status = 'in_progress'";
elseif ($statusFilter === 'completed')   $where[] = "cs.overall_status = 'completed'";
elseif ($statusFilter === 'overdue') {
    $where[] = "cs.deadline < CURDATE()";
    $where[] = "cs.overall_status != 'completed'";
}
if ($search !== '') {
    $where[]  = "(CONCAT(c.first_name,' ',c.last_name) LIKE ? OR c.email LIKE ? OR s.service_name LIKE ?)";
    $like     = "%$search%";
    $params   = [$like, $like, $like];
}
$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

$overdueCount = (int)$pdo->query("
    SELECT COUNT(*) FROM client_services cs
    WHERE cs.deadline < CURDATE() AND cs.overall_status != 'completed'
")->fetchColumn();

$taskStmt = $pdo->prepare("
    SELECT cs.client_service_id, cs.overall_status, cs.start_date, cs.deadline,
           c.first_name, c.last_name, c.email, c.phone,
           s.service_name, s.service_id,
           DATEDIFF(CURDATE(), cs.deadline) as days_overdue,
           (SELECT COUNT(*) FROM client_service_requirements WHERE client_service_id = cs.client_service_id) AS total_steps,
           (SELECT COUNT(*) FROM client_service_requirements WHERE client_service_id = cs.client_service_id AND status = 'completed') AS completed_steps
    FROM client_services cs
    JOIN clients c ON cs.client_id = c.client_id
    JOIN services s ON cs.service_id = s.service_id
    $whereClause
    ORDER BY
        CASE WHEN cs.deadline < CURDATE() AND cs.overall_status != 'completed' THEN 0 ELSE 1 END,
        CASE cs.overall_status WHEN 'pending' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'completed' THEN 3 WHEN 'on_hold' THEN 4 END,
        cs.deadline ASC, cs.start_date DESC
");
$taskStmt->execute($params);
$tasks = $taskStmt->fetchAll(PDO::FETCH_ASSOC);

$csToService = [];
foreach ($tasks as $t) $csToService[(int)$t['client_service_id']] = (int)$t['service_id'];

// ─────────────────────────────────────────────────────────────────────────────
// Load data  — Approval Queue tab
// ─────────────────────────────────────────────────────────────────────────────
$approvalStmt = $pdo->prepare("
    SELECT
        r.requirement_id, r.requirement_name, r.requirement_order,
        r.notes as requirement_notes,
        cs.client_service_id,
        c.client_id, c.first_name AS client_first_name, c.last_name AS client_last_name,
        s.service_name,
        st.staff_id, st.first_name AS staff_first_name, st.last_name AS staff_last_name,
        r.started_at
    FROM client_service_requirements r
    JOIN client_services cs ON r.client_service_id = cs.client_service_id
    JOIN clients c ON cs.client_id = c.client_id
    JOIN services s ON cs.service_id = s.service_id
    LEFT JOIN staff st ON r.assigned_staff_id = st.staff_id
    WHERE r.status = 'approval_pending'
    ORDER BY r.started_at DESC, r.requirement_id DESC
");
$approvalStmt->execute();
$pendingApprovals     = $approvalStmt->fetchAll(PDO::FETCH_ASSOC);
$approvalCount        = count($pendingApprovals);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Clients, Tasks &amp; Approvals</title>
    <link rel="stylesheet" href="../assets/css_file/admin_pages.css">
    <link rel="stylesheet" href="../assets/css_file/navigation_bar.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
    /* ─── Design tokens ──────────────────────────────────────────────────── */
    :root {
        --c-navy:       #1a2744;
        --c-navy-mid:   #243156;
        --c-blue:       #1e3a8a;
        --c-blue-light: #eef2ff;
        --c-blue-muted: #c7d2fe;
        --c-slate:      #64748b;
        --c-slate-light:#f1f5f9;
        --c-border:     #dde3ed;
        --c-border-mid: #c9d3e4;
        --c-surface:    #ffffff;
        --c-page:       #f3f4f6;
        --c-text-head:  #0f1c38;
        --c-text-body:  #374151;
        --c-text-muted: #64748b;
        --c-success:    #166534;
        --c-success-bg: #dcfce7;
        --c-success-bd: #bbf7d0;
        --c-warn:       #92400e;
        --c-warn-bg:    #fef3c7;
        --c-warn-bd:    #fde68a;
        --c-danger:     #991b1b;
        --c-danger-bg:  #fee2e2;
        --c-danger-bd:  #fecaca;
        --c-info:       #1e40af;
        --c-info-bg:    #dbeafe;
        --c-info-bd:    #bfdbfe;
        --shadow-xs:    0 1px 2px rgba(15, 23, 42, .05);
        --shadow-sm:    0 1px 3px rgba(15, 23, 42, .06);
        --shadow-md:    0 3px 10px rgba(15, 23, 42, .08);
        --radius-sm:    3px;
        --radius-md:    4px;
        --radius-lg:    4px;
        --radius-xl:    6px;
    }

    /* ─── Page layout ────────────────────────────────────────────────────── */
    .page-top {
        padding: 28px 32px 0;
        background: var(--c-surface);
        border-bottom: 1px solid var(--c-border);
    }
    .page-top-row {
        display: flex;
        align-items: baseline;
        gap: 10px;
        margin-bottom: 2px;
    }
    .page-top h1 {
        margin: 0;
        font-size: 1.35rem;
        font-weight: 700;
        color: var(--c-text-head);
        letter-spacing: -.01em;
    }
    .page-top p {
        margin: 0 0 18px;
        color: var(--c-text-muted);
        font-size: .85rem;
    }

    /* ─── Tab bar ────────────────────────────────────────────────────────── */
    .tab-bar {
        display: flex;
        gap: 0;
        margin-bottom: -1px;
    }
    .tab-btn {
        padding: 0 22px;
        height: 44px;
        border: none;
        background: none;
        font-size: .83rem;
        font-weight: 600;
        color: var(--c-text-muted);
        cursor: pointer;
        position: relative;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        letter-spacing: .01em;
        transition: color .15s;
        white-space: nowrap;
    }
    .tab-btn::after {
        content: '';
        position: absolute;
        bottom: 0; left: 0; right: 0;
        height: 2px;
        background: var(--c-blue);
        transform: scaleX(0);
        transition: transform .18s;
    }
    .tab-btn.active { color: var(--c-blue); }
    .tab-btn.active::after { transform: scaleX(1); }
    .tab-btn:hover:not(.active) { color: var(--c-text-head); background: #f8fafc; }

    .tab-badge {
        background: var(--c-danger);
        color: #fff;
        border-radius: 999px;
        padding: 1px 6px;
        font-size: .68rem;
        font-weight: 700;
        line-height: 1.55;
        letter-spacing: .02em;
    }

    .tab-panel { display: none; padding: 26px 32px; }
    .tab-panel.active { display: block; }

    /* ─── Action row ─────────────────────────────────────────────────────── */
    .actions-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 20px;
    }
    .filter-strip {
        display: flex;
        gap: 4px;
        align-items: center;
        flex-wrap: wrap;
    }
    .filter-strip-label {
        font-size: .75rem;
        font-weight: 600;
        color: var(--c-text-muted);
        text-transform: uppercase;
        letter-spacing: .06em;
        margin-right: 4px;
    }
    .filter-pill {
        padding: 5px 13px;
        border-radius: var(--radius-md);
        border: 1px solid var(--c-border);
        background: var(--c-surface);
        font-size: .78rem;
        font-weight: 600;
        color: var(--c-text-muted);
        cursor: pointer;
        text-decoration: none;
        transition: all .13s;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .filter-pill:hover { border-color: var(--c-blue); color: var(--c-blue); background: var(--c-blue-light); }
    .filter-pill.active { background: var(--c-navy); border-color: var(--c-navy); color: #fff; }
    .filter-pill.danger.active { background: var(--c-danger); border-color: var(--c-danger); color: #fff; }
    .filter-pill.danger:hover { border-color: var(--c-danger); color: var(--c-danger); background: var(--c-danger-bg); }

    /* ─── Buttons ────────────────────────────────────────────────────────── */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border: 1px solid transparent;
        border-radius: var(--radius-md);
        font-size: .82rem;
        font-weight: 600;
        cursor: pointer;
        transition: all .14s;
        letter-spacing: .01em;
        text-decoration: none;
    }
    .btn-primary { background: var(--c-blue); color: #fff; border-color: var(--c-blue); }
    .btn-primary:hover { background: #254fcc; border-color: #254fcc; }
    .btn-secondary { background: var(--c-surface); color: var(--c-text-body); border-color: var(--c-border); }
    .btn-secondary:hover { background: var(--c-slate-light); border-color: var(--c-border-mid); }
    .btn-success { background: var(--c-success-bg); color: var(--c-success); border-color: var(--c-success-bd); }
    .btn-success:hover { background: var(--c-success); color: #fff; border-color: var(--c-success); }
    .btn-danger { background: var(--c-danger-bg); color: var(--c-danger); border-color: var(--c-danger-bd); }
    .btn-danger:hover { background: var(--c-danger); color: #fff; border-color: var(--c-danger); }
    .btn-warn { background: var(--c-warn-bg); color: var(--c-warn); border-color: var(--c-warn-bd); }
    .btn-warn:hover { background: var(--c-warn); color: #fff; border-color: var(--c-warn); }
    .btn-sm { padding: 5px 12px; font-size: .77rem; }
    .btn-navy { background: var(--c-navy); color: #fff; border-color: var(--c-navy); }
    .btn-navy:hover { background: var(--c-navy-mid); border-color: var(--c-navy-mid); }

    /* ─── Data table ─────────────────────────────────────────────────────── */
    .data-table {
        width: 100%;
        border-collapse: collapse;
        background: var(--c-surface);
        border-radius: 0;
        overflow: visible;
        box-shadow: none;
        border: 1px solid var(--c-border);
    }
    .data-table thead th {
        background: #f8fafc;
        padding: 11px 16px;
        text-align: left;
        font-size: .71rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .07em;
        color: var(--c-text-muted);
        border-bottom: 1px solid var(--c-border-mid);
    }
    .data-table tbody td {
        padding: 12px 16px;
        border-bottom: 1px solid #e5e7eb;
        font-size: .85rem;
        vertical-align: middle;
        color: var(--c-text-body);
    }
    .data-table tbody tr:last-child td { border-bottom: none; }
    .data-table tbody tr:hover td { background: #f9fafb; }
    .data-table .cell-primary { font-weight: 600; color: var(--c-text-head); }
    .data-table .cell-sub { font-size: .76rem; color: var(--c-text-muted); margin-top: 2px; }

    /* ─── Status badges ──────────────────────────────────────────────────── */
    .badge {
        display: inline-flex;
        align-items: center;
        padding: 3px 9px;
        border-radius: var(--radius-sm);
        font-size: .71rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        border: 1px solid transparent;
    }
    .badge-pending     { background: var(--c-warn-bg);    color: var(--c-warn);    border-color: var(--c-warn-bd); }
    .badge-in_progress { background: var(--c-info-bg);    color: var(--c-info);    border-color: var(--c-info-bd); }
    .badge-completed   { background: var(--c-success-bg); color: var(--c-success); border-color: var(--c-success-bd); }
    .badge-on_hold     { background: var(--c-slate-light); color: var(--c-slate);  border-color: var(--c-border); }

    /* ─── Task cards ─────────────────────────────────────────────────────── */
    .task-card {
        background: var(--c-surface);
        border: 1px solid var(--c-border);
        border-radius: var(--radius-lg);
        padding: 18px 22px;
        margin-bottom: 10px;
        box-shadow: none;
        transition: box-shadow .18s, border-color .18s;
    }
    .task-card:hover { box-shadow: none; border-color: var(--c-border-mid); }
    .task-card.overdue { border-left: 3px solid var(--c-danger); border-color: var(--c-danger-bd); background: #fffafa; }
    .task-card.overdue:hover { box-shadow: 0 4px 16px rgba(153,27,27,.1); }

    .task-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; }
    .task-info { flex: 1; min-width: 0; }
    .task-client { font-size: .97rem; font-weight: 700; color: var(--c-text-head); margin-bottom: 4px; }
    .task-meta-row { font-size: .81rem; color: var(--c-text-muted); margin-top: 3px; }
    .task-meta-row strong { color: var(--c-text-body); font-weight: 600; }

    .overdue-tag {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 2px 9px;
        background: var(--c-danger-bg);
        color: var(--c-danger);
        border: 1px solid var(--c-danger-bd);
        border-radius: var(--radius-sm);
        font-size: .71rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        margin-bottom: 6px;
    }

    .progress-row { display: flex; align-items: center; gap: 10px; margin-top: 13px; }
    .progress-bar { flex: 1; height: 4px; background: var(--c-border); border-radius: 999px; overflow: hidden; }
    .progress-fill { height: 100%; background: var(--c-blue); border-radius: 999px; transition: width .4s; }
    .progress-label { font-size: .75rem; font-weight: 600; color: var(--c-text-muted); white-space: nowrap; }

    /* ─── Overdue banner ─────────────────────────────────────────────────── */
    .overdue-banner {
        display: flex;
        align-items: center;
        gap: 12px;
        background: #fffbf5;
        border: 1px solid var(--c-warn-bd);
        border-left: 3px solid #d97706;
        border-radius: var(--radius-md);
        padding: 12px 16px;
        margin-bottom: 18px;
        font-size: .85rem;
        color: var(--c-text-body);
    }
    .overdue-banner a { color: #b45309; font-weight: 700; text-decoration: underline; }

    /* ─── Approval cards ─────────────────────────────────────────────────── */
    .approval-card {
        background: var(--c-surface);
        border: 1px solid var(--c-border);
        border-radius: var(--radius-lg);
        padding: 20px 24px;
        margin-bottom: 14px;
        box-shadow: none;
        transition: box-shadow .18s;
    }
    .approval-card:hover { box-shadow: none; border-color: var(--c-border-mid); }
    .approval-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 14px;
        padding-bottom: 14px;
        border-bottom: 1px solid var(--c-border);
    }
    .approval-client-name { font-size: .97rem; font-weight: 700; color: var(--c-text-head); }
    .approval-service { font-size: .82rem; color: var(--c-text-muted); margin-top: 3px; }
    .approval-staff-label {
        font-size: .71rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .07em;
        color: var(--c-text-muted);
    }
    .approval-staff-name { font-size: .84rem; font-weight: 600; color: var(--c-text-body); margin-top: 2px; }

    .docs-header {
        font-size: .71rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .07em;
        color: var(--c-text-muted);
        margin: 14px 0 8px;
    }
    .docs-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 8px; }
    .doc-item {
        border: 1px solid var(--c-border);
        border-radius: var(--radius-md);
        padding: 10px 14px;
        cursor: pointer;
        transition: all .14s;
        background: #fafbfd;
    }
    .doc-item:hover { border-color: var(--c-blue); background: var(--c-blue-light); }
    .doc-name { font-size: .82rem; font-weight: 600; color: var(--c-text-head); word-break: break-word; }
    .doc-verified { font-size: .72rem; color: var(--c-success); font-weight: 600; margin-top: 3px; }

    .notes-label { font-size: .76rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--c-text-muted); margin-bottom: 5px; display: block; }
    .admin-notes {
        width: 100%;
        padding: 9px 12px;
        border: 1px solid var(--c-border);
        border-radius: var(--radius-md);
        font-size: .85rem;
        resize: vertical;
        min-height: 68px;
        box-sizing: border-box;
        outline: none;
        transition: border-color .18s;
        font-family: inherit;
        background: #fafbfd;
        color: var(--c-text-body);
    }
    .admin-notes:focus { border-color: var(--c-blue); background: var(--c-surface); }

    .no-approvals { text-align: center; padding: 64px 0; color: var(--c-text-muted); }
    .no-approvals svg { width: 52px; height: 52px; margin: 0 auto 14px; display: block; color: #4ade80; }
    .no-approvals h3 { font-size: 1.05rem; margin-bottom: 4px; color: var(--c-text-body); }
    .no-approvals p { font-size: .84rem; }

    /* ─── Divider label ──────────────────────────────────────────────────── */
    .section-divider {
        font-size: .71rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--c-text-muted);
        margin: 8px 0 16px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .section-divider::after { content: ''; flex: 1; height: 1px; background: var(--c-border); }

    /* ─── Modals ─────────────────────────────────────────────────────────── */
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(10,18,36,.4);
        backdrop-filter: blur(2px);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .modal-overlay.active { display: flex; }
    .modal-box {
        background: var(--c-surface);
        border-radius: var(--radius-xl);
        width: 100%;
        max-width: 680px;
        max-height: 92vh;
        display: flex;
        flex-direction: column;
        box-shadow: 0 24px 64px rgba(0,0,0,.2);
        animation: mIn .16s ease;
        border: 1px solid var(--c-border);
    }
    .modal-box.wide { max-width: 940px; }
    .modal-box.xl   { max-width: 1180px; }
    @keyframes mIn { from { transform: translateY(8px) scale(.98); opacity: 0; } to { transform: none; opacity: 1; } }
    .modal-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 22px;
        border-bottom: 1px solid var(--c-border);
        flex-shrink: 0;
        background: #fafbfd;
        border-radius: var(--radius-xl) var(--radius-xl) 0 0;
    }
    .modal-head h2 { margin: 0; font-size: .97rem; font-weight: 700; color: var(--c-text-head); }
    .modal-close-btn {
        background: none;
        border: none;
        font-size: 1.3rem;
        cursor: pointer;
        color: var(--c-text-muted);
        line-height: 1;
        padding: 2px 6px;
        border-radius: var(--radius-sm);
        transition: background .13s, color .13s;
    }
    .modal-close-btn:hover { background: var(--c-slate-light); color: var(--c-text-head); }
    .modal-body { padding: 22px; overflow-y: auto; flex: 1; }
    .modal-foot {
        padding: 14px 22px;
        border-top: 1px solid var(--c-border);
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 10px;
        flex-shrink: 0;
        background: #fafbfd;
        border-radius: 0 0 var(--radius-xl) var(--radius-xl);
    }

    /* ─── Forms ──────────────────────────────────────────────────────────── */
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
    .form-group { display: flex; flex-direction: column; gap: 5px; }
    .form-group label {
        font-size: .75rem;
        font-weight: 700;
        color: var(--c-text-body);
        text-transform: uppercase;
        letter-spacing: .05em;
    }
    .form-group input,
    .form-group select,
    .form-group textarea {
        padding: 8px 11px;
        border: 1px solid var(--c-border);
        border-radius: var(--radius-md);
        font-size: .86rem;
        outline: none;
        transition: border-color .18s, box-shadow .18s;
        font-family: inherit;
        background: var(--c-surface);
        color: var(--c-text-body);
    }
    .form-group input:focus,
    .form-group select:focus { border-color: var(--c-blue); box-shadow: 0 0 0 3px rgba(45,91,227,.1); }
    .form-group input[required],
    .form-group select[required] { border-left: 2px solid var(--c-danger); }
    .form-hint { font-size: .75rem; color: var(--c-text-muted); margin-top: 2px; }

    .radio-group {
        background: var(--c-page);
        border: 1px solid var(--c-border);
        border-radius: var(--radius-md);
        padding: 10px 12px;
        max-height: 220px;
        overflow-y: auto;
    }
    .radio-group label {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 5px 6px;
        cursor: pointer;
        font-size: .85rem;
        border-radius: var(--radius-sm);
        transition: background .12s;
        font-weight: 400;
        text-transform: none;
        letter-spacing: 0;
        color: var(--c-text-body);
    }
    .radio-group label:hover { background: var(--c-blue-light); color: var(--c-blue); }

    /* ─── Choice cards ───────────────────────────────────────────────────── */
    .choice-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .choice-card {
        border: 1px solid var(--c-border);
        border-radius: var(--radius-lg);
        padding: 28px 20px;
        text-align: center;
        cursor: pointer;
        transition: all .17s;
        background: var(--c-surface);
    }
    .choice-card:hover {
        border-color: var(--c-blue);
        background: var(--c-blue-light);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(45,91,227,.1);
    }
    .choice-card .icon { font-size: 1.9rem; margin-bottom: 10px; display: block; }
    .choice-card h3 { margin: 0 0 4px; font-size: .94rem; color: var(--c-text-head); font-weight: 700; }
    .choice-card p { margin: 0; font-size: .79rem; color: var(--c-text-muted); }

    /* ─── Client select list ─────────────────────────────────────────────── */
    .client-list-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border: 1px solid var(--c-border);
        border-radius: var(--radius-md);
        padding: 11px 14px;
        margin-bottom: 7px;
        transition: all .13s;
    }
    .client-list-item:hover { background: #fafbff; border-color: var(--c-blue-muted); }
    .client-list-item h4 { margin: 0 0 2px; font-size: .89rem; color: var(--c-text-head); }
    .client-list-item p  { margin: 2px 0; font-size: .77rem; color: var(--c-text-muted); }

    /* ─── Req table ──────────────────────────────────────────────────────── */
    .req-table { width: 100%; border-collapse: collapse; font-size: .83rem; }
    .req-table th {
        background: #f8fafc;
        padding: 10px 12px;
        text-align: left;
        font-size: .70rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--c-text-muted);
        border-bottom: 1px solid var(--c-border-mid);
    }
    .req-table td { padding: 11px 12px; border-bottom: 1px solid #e5e7eb; vertical-align: middle; color: var(--c-text-body); }
    .req-table tr:last-child td { border-bottom: none; }

    /* ─── Onboarding panel ───────────────────────────────────────────────── */
    .ob-banner {
        background: var(--c-blue-light);
        border: 1px solid var(--c-blue-muted);
        border-radius: var(--radius-md);
        padding: 13px 16px;
        display: flex;
        gap: 13px;
        align-items: center;
        margin-bottom: 20px;
    }
    .ob-banner-text strong { display: block; font-size: .93em; color: var(--c-info); font-weight: 700; }
    .ob-banner-text span   { font-size: .81em; color: var(--c-text-muted); }
    .ob-section-title {
        font-size: .71rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--c-text-muted);
        margin: 16px 0 8px;
        padding-bottom: 5px;
        border-bottom: 1px solid var(--c-border);
    }
    .ob-layout { display: flex; gap: 16px; align-items: flex-start; }
    .ob-steps-main { flex: 1; min-width: 0; }

    /* ─── Suggestions panel ──────────────────────────────────────────────── */
    .suggestions-panel {
        width: 200px;
        flex-shrink: 0;
        background: var(--c-page);
        border: 1px solid var(--c-border);
        border-radius: var(--radius-lg);
        padding: 13px;
    }
    .suggestions-panel h4 {
        margin: 0 0 10px;
        font-size: .71rem;
        text-transform: uppercase;
        letter-spacing: .07em;
        color: var(--c-text-muted);
        font-weight: 700;
    }
    .suggestions-panel .no-sug { font-size: .79rem; color: var(--c-text-muted); font-style: italic; margin: 0; }
    .suggestion-chip {
        display: block;
        width: 100%;
        text-align: left;
        background: var(--c-surface);
        border: 1px solid var(--c-border);
        border-radius: var(--radius-sm);
        padding: 6px 10px;
        margin-bottom: 5px;
        cursor: pointer;
        font-size: .78rem;
        color: var(--c-text-body);
        transition: background .13s, border-color .13s;
        word-break: break-word;
    }
    .suggestion-chip:hover { background: var(--c-blue-light); border-color: var(--c-blue-muted); color: var(--c-blue); }
    .suggestion-chip::before { content: '+ '; font-weight: 700; color: var(--c-blue); }

    /* ─── Step rows ──────────────────────────────────────────────────────── */
    .step-row-ob,
    .task-step-row {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        background: var(--c-page);
        border: 1px solid var(--c-border);
        border-radius: var(--radius-md);
        padding: 10px 12px;
        margin-bottom: 8px;
    }
    .step-num {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background: var(--c-navy);
        color: #fff;
        font-size: .72rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .step-inputs { flex: 1; display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-start; }
    .step-name-wrap { position: relative; flex: 1; min-width: 155px; }
    .step-name-input {
        width: 100%;
        padding: 7px 10px;
        border: 1px solid var(--c-border);
        border-radius: var(--radius-sm);
        font-size: .84rem;
        box-sizing: border-box;
        outline: none;
        transition: border-color .18s;
        color: var(--c-text-body);
        background: var(--c-surface);
    }
    .step-name-input:focus { border-color: var(--c-blue); box-shadow: 0 0 0 2px rgba(45,91,227,.1); }

    .autocomplete-list {
        position: absolute;
        top: 100%; left: 0; right: 0;
        background: var(--c-surface);
        border: 1px solid var(--c-border-mid);
        border-top: none;
        border-radius: 0 0 var(--radius-md) var(--radius-md);
        z-index: 9999;
        max-height: 150px;
        overflow-y: auto;
        box-shadow: var(--shadow-md);
        display: none;
    }
    .ac-item { padding: 7px 11px; cursor: pointer; font-size: .83rem; color: var(--c-text-body); }
    .ac-item:hover, .ac-item.active { background: var(--c-blue-light); color: var(--c-blue); }

    .staff-wrap { flex: 1; min-width: 148px; }
    .staff-select {
        width: 100%;
        padding: 7px 10px;
        border: 1px solid var(--c-border);
        border-radius: var(--radius-sm);
        font-size: .82rem;
        outline: none;
        background: var(--c-surface);
        color: var(--c-text-body);
    }
    .staff-select:focus { border-color: var(--c-blue); }
    .workload-hint { font-size: .70rem; margin-top: 3px; font-weight: 600; display: none; }
    .workload-hint.on { display: block; }

    .remove-step-btn {
        background: none;
        border: none;
        color: var(--c-danger);
        cursor: pointer;
        font-size: 1rem;
        padding: 0 2px;
        line-height: 1;
        flex-shrink: 0;
        margin-top: 7px;
        opacity: .7;
        transition: opacity .13s;
    }
    .remove-step-btn:hover { opacity: 1; }

    .add-step-btn {
        background: none;
        border: 1.5px dashed var(--c-border-mid);
        color: var(--c-text-muted);
        padding: 7px 16px;
        border-radius: var(--radius-md);
        cursor: pointer;
        font-size: .82rem;
        font-weight: 600;
        margin-top: 4px;
        transition: border-color .13s, color .13s;
    }
    .add-step-btn:hover { border-color: var(--c-blue); color: var(--c-blue); background: var(--c-blue-light); }

    .skip-link { font-size: .79rem; color: var(--c-text-muted); cursor: pointer; text-decoration: underline; }
    .skip-link:hover { color: var(--c-text-body); }

    /* ─── View step cards ────────────────────────────────────────────────── */
    .view-step-card { border: 1px solid var(--c-border); border-radius: var(--radius-md); overflow: hidden; margin-bottom: 8px; }
    .view-step-head {
        display: grid;
        grid-template-columns: 36px 1fr 1fr 110px;
        gap: 10px;
        align-items: center;
        padding: 11px 14px;
        background: #f8fafd;
        font-size: .85rem;
    }
    .files-section { padding: 11px 14px; border-top: 1px solid var(--c-border); }
    .files-section h4 { font-size: .78rem; margin: 0 0 8px; color: var(--c-text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
    .no-files { font-size: .79rem; color: var(--c-text-muted); font-style: italic; }
    .file-row { display: flex; align-items: center; gap: 10px; padding: 5px 0; }
    .file-name { font-size: .82rem; font-weight: 600; color: var(--c-text-head); }
    .file-size { font-size: .72rem; color: var(--c-text-muted); }
    .view-file-btn {
        padding: 4px 11px;
        background: var(--c-blue-light);
        color: var(--c-blue);
        border: 1px solid var(--c-blue-muted);
        border-radius: var(--radius-sm);
        cursor: pointer;
        font-size: .76rem;
        font-weight: 600;
        margin-left: auto;
        transition: all .13s;
    }
    .view-file-btn:hover { background: var(--c-blue); color: #fff; border-color: var(--c-blue); }

    /* ─── Preview modal ──────────────────────────────────────────────────── */
    .preview-img    { max-width: 100%; max-height: 70vh; display: block; margin: 0 auto; border-radius: var(--radius-md); }
    .preview-iframe { width: 100%; height: 70vh; border: none; border-radius: var(--radius-md); }
    .preview-download { text-align: center; padding: 30px; }
    .preview-download a { padding: 10px 22px; background: var(--c-blue); color: #fff; border-radius: var(--radius-md); text-decoration: none; font-weight: 600; font-size: .9rem; }

    /* ─── Search input ───────────────────────────────────────────────────── */
    .search-input {
        padding: 7px 13px;
        border: 1px solid var(--c-border);
        border-radius: var(--radius-md);
        font-size: .84rem;
        outline: none;
        transition: border-color .18s, box-shadow .18s;
        background: var(--c-surface);
        color: var(--c-text-body);
    }
    .search-input:focus { border-color: var(--c-blue); box-shadow: 0 0 0 3px rgba(45,91,227,.1); }

    /* ─── Step status badges ─────────────────────────────────────────────── */
    .s-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: var(--radius-sm);
        font-size: .69rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        border: 1px solid transparent;
    }
    .s-pending     { background: var(--c-warn-bg);    color: var(--c-warn);    border-color: var(--c-warn-bd); }
    .s-in_progress { background: var(--c-info-bg);    color: var(--c-info);    border-color: var(--c-info-bd); }
    .s-completed   { background: var(--c-success-bg); color: var(--c-success); border-color: var(--c-success-bd); }

    /* ─── Empty states ───────────────────────────────────────────────────── */
    .empty-state {
        text-align: center;
        padding: 64px 0;
        color: var(--c-text-muted);
    }
    .empty-state h3 { font-size: 1rem; margin-bottom: 5px; color: var(--c-text-body); }
    .empty-state p { font-size: .83rem; }
    </style>
</head>
<body>
<div class="container">
    <?php include '../partials/temporaryNavAdmin.php'; ?>

    <div class="main-content">

        <!-- Page heading + tabs -->
        <div class="page-top">
            <div class="page-top-row">
                <h1>Client Management</h1>
            </div>
            <p>Manage clients, tasks, and approval requests</p>

            <div class="tab-bar">
                <button class="tab-btn" data-tab="clients">
                    Clients
                    <?php if ($pending_count > 0): ?>
                        <span class="tab-badge"><?= $pending_count ?></span>
                    <?php endif; ?>
                </button>
                <button class="tab-btn" data-tab="tasks">
                    Tasks
                    <?php if ($overdueCount > 0): ?>
                        <span class="tab-badge"><?= $overdueCount ?></span>
                    <?php endif; ?>
                </button>
                <button class="tab-btn" data-tab="approvals">
                    Approvals
                    <?php if ($approvalCount > 0): ?>
                        <span class="tab-badge"><?= $approvalCount ?></span>
                    <?php endif; ?>
                </button>
                <button class="tab-btn" data-tab="insights">Insights</button>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════
             TAB 1 — CLIENTS
             ═══════════════════════════════════════════════════════════════════ -->
        <div id="tab-clients" class="tab-panel">
            <div class="actions-row">
                <div class="filter-strip">
                    <span class="filter-strip-label">Filter</span>
                    <button class="filter-pill active" data-filter="all">All</button>
                    <button class="filter-pill" data-filter="pending">Pending</button>
                    <button class="filter-pill" data-filter="in_progress">In Progress</button>
                    <button class="filter-pill" data-filter="completed">Completed</button>
                    <button class="filter-pill" data-filter="on_hold">On Hold</button>
                    <input type="text" id="clientSearch" class="search-input" placeholder="Search name or email&hellip;" style="margin-left:8px;width:220px;">
                </div>
                <div style="display:flex;gap:8px;">
                    <button class="btn btn-secondary" onclick="openModal('serviceRequestModal')" style="position:relative;">
                        Requests
                        <?php if ($pending_count > 0): ?>
                            <span class="tab-badge" style="position:absolute;top:-7px;right:-7px;"><?= $pending_count ?></span>
                        <?php endif; ?>
                    </button>
                    <button class="btn btn-navy" onclick="openModal('choiceModal')">+ Add Client</button>
                </div>
            </div>

            <?php if (empty($clientServiceRows)): ?>
                <div class="empty-state">
                    <h3>No clients yet</h3>
                    <p>Accept a request or add a client manually to get started.</p>
                </div>
            <?php else: ?>
                <table class="data-table" id="clientTable">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Contact</th>
                            <th>Service</th>
                            <th>Status</th>
                            <th>Requirements</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientServiceRows as $row):
                            $c   = $row['client'];
                            $svc = $row['service'];
                            $st  = $row['status'];
                        ?>
                        <tr data-status="<?= $st ?>">
                            <td>
                                <div class="cell-primary"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></div>
                                <div class="cell-sub"><?= $c['registration_date'] ?: '—' ?></div>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($c['email']) ?></div>
                                <div class="cell-sub"><?= htmlspecialchars($c['phone'] ?: '—') ?></div>
                            </td>
                            <td><?= $svc ? htmlspecialchars($svc['service_name']) : '<span style="color:var(--c-text-muted);font-style:italic;">None</span>' ?></td>
                            <td><span class="badge badge-<?= $st ?>"><?= ucfirst(str_replace('_', ' ', $st)) ?></span></td>
                            <td>
                                <?= $row['has_requirements']
                                    ? '<span style="color:var(--c-success);font-weight:700;font-size:.82rem;">Yes</span>'
                                    : '<span style="color:var(--c-text-muted);font-size:.82rem;">No</span>' ?>
                            </td>
                            <td>
                                <button class="btn btn-secondary btn-sm" onclick="openEditClientModal(<?= $c['client_id'] ?>)">Edit</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════
             TAB 2 — TASKS
             ═══════════════════════════════════════════════════════════════════ -->
        <div id="tab-tasks" class="tab-panel">
            <?php if ($overdueCount > 0): ?>
                <div class="overdue-banner">
                    <span>&#9888;</span>
                    <span>You have <strong><?= $overdueCount ?></strong> overdue service<?= $overdueCount > 1 ? 's' : '' ?> that require attention.</span>
                    <a href="?tab=tasks&status=overdue">View Now &rarr;</a>
                </div>
            <?php endif; ?>

            <div class="actions-row">
                <div class="filter-strip">
                    <span class="filter-strip-label">Filter</span>
                    <a class="filter-pill <?= $statusFilter==='all'         ? 'active' : '' ?>" href="?tab=tasks&status=all">All</a>
                    <a class="filter-pill danger <?= $statusFilter==='overdue' ? 'active' : '' ?>" href="?tab=tasks&status=overdue">
                        &#9888; Overdue<?= $overdueCount > 0 ? " ($overdueCount)" : '' ?>
                    </a>
                    <a class="filter-pill <?= $statusFilter==='new'         ? 'active' : '' ?>" href="?tab=tasks&status=new&tab=tasks">New</a>
                    <a class="filter-pill <?= $statusFilter==='in_progress' ? 'active' : '' ?>" href="?tab=tasks&status=in_progress&tab=tasks">In Progress</a>
                    <a class="filter-pill <?= $statusFilter==='completed'   ? 'active' : '' ?>" href="?tab=tasks&status=completed&tab=tasks">Completed</a>
                </div>
                <form method="get" style="display:flex;gap:8px;">
                    <input type="hidden" name="tab" value="tasks">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                    <input type="search" name="search" class="search-input" placeholder="Search client or service&hellip;" value="<?= htmlspecialchars($search) ?>" style="width:230px;">
                    <button type="submit" class="btn btn-primary" style="padding:7px 14px;">Search</button>
                </form>
            </div>

            <?php if (empty($tasks)): ?>
                <div class="empty-state">
                    No tasks found<?= $search ? ' for "' . htmlspecialchars($search) . '"' : '' ?>.
                </div>
            <?php else: ?>
                <?php foreach ($tasks as $task):
                    $isDone    = $task['overall_status'] === 'completed';
                    $isPending = $task['overall_status'] === 'pending';
                    $hasSteps  = $task['total_steps'] > 0;
                    $progress  = $hasSteps ? round(($task['completed_steps'] / $task['total_steps']) * 100) : 0;
                    $isOverdue = !$isDone && $task['deadline'] && strtotime($task['deadline']) < strtotime('today');
                ?>
                <div class="task-card <?= $isOverdue ? 'overdue' : '' ?>">
                    <div class="task-header">
                        <div class="task-info">
                            <?php if ($isOverdue): ?>
                                <div class="overdue-tag">&#9888; <?= $task['days_overdue'] ?> day<?= $task['days_overdue'] > 1 ? 's' : '' ?> overdue</div>
                            <?php endif; ?>
                            <div class="task-client"><?= htmlspecialchars($task['first_name'] . ' ' . $task['last_name']) ?></div>
                            <div class="task-meta-row">Service: <strong><?= htmlspecialchars($task['service_name']) ?></strong> &nbsp;&middot;&nbsp; Status: <strong><?= ucfirst(str_replace('_', ' ', $task['overall_status'])) ?></strong></div>
                            <div class="task-meta-row">
                                Started: <?= $task['start_date'] ? date('M d, Y', strtotime($task['start_date'])) : '—' ?>
                                &nbsp;&middot;&nbsp; Deadline:
                                <strong style="<?= $isOverdue ? 'color:var(--c-danger);' : '' ?>">
                                    <?= $task['deadline'] ? date('M d, Y', strtotime($task['deadline'])) : 'Not set' ?>
                                </strong>
                            </div>
                            <div class="task-meta-row"><?= htmlspecialchars($task['email']) ?><?= $task['phone'] ? ' &middot; ' . htmlspecialchars($task['phone']) : '' ?></div>
                        </div>
                        <?php if ($isDone): ?>
                            <button class="btn btn-success btn-sm task-action-btn" data-cs-id="<?= $task['client_service_id'] ?>" data-action="view">View Details</button>
                        <?php elseif ($isPending): ?>
                            <button class="btn btn-secondary btn-sm task-action-btn" data-cs-id="<?= $task['client_service_id'] ?>" data-service-id="<?= $task['service_id'] ?>" data-action="edit" style="color:var(--c-info);border-color:var(--c-info-bd);">Assign Staff</button>
                        <?php else: ?>
                            <button class="btn btn-warn btn-sm task-action-btn" data-cs-id="<?= $task['client_service_id'] ?>" data-service-id="<?= $task['service_id'] ?>" data-action="edit">Manage Steps</button>
                        <?php endif; ?>
                    </div>
                    <?php if ($hasSteps): ?>
                        <div class="progress-row">
                            <span class="progress-label" style="min-width:60px;color:var(--c-text-muted);">Progress</span>
                            <div class="progress-bar"><div class="progress-fill" style="width:<?= $progress ?>%"></div></div>
                            <span class="progress-label"><?= $task['completed_steps'] ?> / <?= $task['total_steps'] ?></span>
                        </div>
                    <?php else: ?>
                        <div style="color:var(--c-text-muted);font-size:.79rem;margin-top:10px;font-style:italic;">No steps assigned yet</div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════
             TAB 3 — APPROVALS
             ═══════════════════════════════════════════════════════════════════ -->
        <div id="tab-approvals" class="tab-panel">
            <?php if (empty($pendingApprovals)): ?>
                <div class="no-approvals">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <h3>All Clear</h3>
                    <p>No pending approvals at this time.</p>
                </div>
            <?php else: ?>
                <?php foreach ($pendingApprovals as $req):
                    $req_id = $req['requirement_id'];
                    $uploadStmt = $pdo->prepare("
                        SELECT document_id, document_name AS original_name, document_url AS file_path, upload_date AS uploaded_at, file_type
                        FROM documents
                        WHERE related_to_type = 'requirement' AND related_to_id = ?
                        ORDER BY upload_date DESC
                    ");
                    $uploadStmt->execute([$req_id]);
                    $uploads = $uploadStmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <div class="approval-card" data-req-id="<?= $req_id ?>" data-staff-id="<?= $req['staff_id'] ?>" data-cs-id="<?= $req['client_service_id'] ?>">
                    <div class="approval-card-header">
                        <div>
                            <div class="approval-client-name"><?= htmlspecialchars($req['client_first_name'] . ' ' . $req['client_last_name']) ?></div>
                            <div class="approval-service"><?= htmlspecialchars($req['service_name']) ?></div>
                        </div>
                        <div style="text-align:right;">
                            <div class="approval-staff-label">Assigned Staff</div>
                            <div class="approval-staff-name"><?= htmlspecialchars($req['staff_first_name'] . ' ' . $req['staff_last_name']) ?></div>
                        </div>
                    </div>

                    <div style="font-weight:700;font-size:.91rem;color:var(--c-text-head);margin-bottom:3px;"><?= htmlspecialchars($req['requirement_name']) ?></div>
                    <div style="font-size:.81rem;color:var(--c-text-muted);margin-bottom:12px;">All documents verified and marked complete by staff. Awaiting admin review.</div>

                    <div class="docs-header">Submitted Documents (<?= count($uploads) ?>)</div>
                    <div class="docs-grid">
                        <?php if (empty($uploads)): ?>
                            <div style="grid-column:1/-1;text-align:center;padding:14px;color:var(--c-text-muted);font-size:.82rem;">No documents uploaded</div>
                        <?php else: foreach ($uploads as $up):
                            $ext = strtolower(pathinfo($up['original_name'], PATHINFO_EXTENSION) ?: ($up['file_type'] ?? ''));
                        ?>
                            <div class="doc-item approval-doc"
                                 data-path="<?= htmlspecialchars($up['file_path']) ?>"
                                 data-type="<?= htmlspecialchars($ext) ?>"
                                 data-name="<?= htmlspecialchars($up['original_name']) ?>">
                                <div class="doc-name"><?= htmlspecialchars($up['original_name']) ?></div>
                                <div class="doc-verified">Verified by staff</div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>

                    <div style="margin-top:14px;">
                        <label class="notes-label">Review Notes <span style="font-weight:400;text-transform:none;">(Optional)</span></label>
                        <textarea class="admin-notes" placeholder="Add notes or instructions before approving&hellip;"></textarea>
                    </div>

                    <div style="display:flex;gap:8px;margin-top:14px;">
                        <button class="btn btn-danger btn-sm approval-action-btn" data-action="rejected">Reject &amp; Return</button>
                        <button class="btn btn-success btn-sm approval-action-btn" data-action="completed">Approve &amp; Proceed</button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════
             TAB 4 — INSIGHTS
             ═══════════════════════════════════════════════════════════════════ -->
        <div id="tab-insights" class="tab-panel">
            <div class="actions-row" style="margin-bottom:14px;">
                <div class="filter-strip">
                    <span class="filter-strip-label">Data-Mined Requirement Insights</span>
                </div>
            </div>

            <?php if (empty($stepInsightsByService)): ?>
                <div class="empty-state">
                    <h3>No historical data yet</h3>
                    <p>Once requirement steps are saved for services, pattern insights will appear here.</p>
                </div>
            <?php else: ?>
                <?php foreach ($stepInsightsByService as $sid => $insights): ?>
                    <div class="task-card" style="margin-bottom:12px;">
                        <div class="task-client" style="margin-bottom:10px;">
                            <?= htmlspecialchars($serviceNames[(int)$sid] ?? ('Service #' . (int)$sid)) ?>
                        </div>
                        <table class="req-table">
                            <thead>
                                <tr>
                                    <th>Requirement Pattern</th>
                                    <th>Support Count</th>
                                    <th>Confidence</th>
                                    <th>Interpretation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($insights as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['requirement_name']) ?></td>
                                        <td><?= (int)$item['usage_count'] ?></td>
                                        <td><?= number_format((float)$item['confidence_pct'], 1) ?>%</td>
                                        <td style="font-size:.8rem;color:var(--c-text-muted);">
                                            Appeared in <?= (int)$item['usage_count'] ?> out of <?= (int)$item['total_requests'] ?> request<?= ((int)$item['total_requests'] === 1 ? '' : 's') ?>.
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div><!-- /main-content -->
</div><!-- /container -->


<!-- ═══════════════════════════════════════════════════════════════════════════
     MODALS
     ═══════════════════════════════════════════════════════════════════════════ -->

<!-- Choice Modal -->
<div id="choiceModal" class="modal-overlay">
    <div class="modal-box" style="max-width:480px;">
        <div class="modal-head"><h2>Add Client</h2><button class="modal-close-btn" data-close="choiceModal">&times;</button></div>
        <div class="modal-body">
            <div class="choice-grid">
                <div class="choice-card" onclick="openModal('existingClientModal');closeModal('choiceModal')">
                    <span class="icon">&#128100;</span>
                    <h3>Existing Client</h3>
                    <p>Add a service to an existing client record</p>
                </div>
                <div class="choice-card" onclick="openAddClientModal()">
                    <span class="icon">&#43;</span>
                    <h3>New Client</h3>
                    <p>Create a new client record from scratch</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Existing Client Select -->
<div id="existingClientModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head"><h2>Select Existing Client</h2><button class="modal-close-btn" data-close="existingClientModal">&times;</button></div>
        <div class="modal-body">
            <input type="text" id="existingClientSearch" class="search-input" style="width:100%;margin-bottom:14px;" placeholder="Search by name, email or phone&hellip;">
            <div style="max-height:380px;overflow-y:auto;">
                <?php foreach ($clients as $client): ?>
                <div class="client-list-item" data-search="<?= strtolower($client['first_name'].' '.$client['last_name'].' '.$client['email'].' '.$client['phone']) ?>">
                    <div>
                        <h4><?= htmlspecialchars($client['first_name'].' '.$client['last_name']) ?></h4>
                        <p><?= htmlspecialchars($client['email']) ?></p>
                        <p><?= htmlspecialchars($client['phone'] ?: 'No phone on file') ?></p>
                    </div>
                    <button class="btn btn-primary btn-sm" onclick="selectExistingClient(<?= $client['client_id'] ?>,'<?= htmlspecialchars(addslashes($client['first_name'].' '.$client['last_name'])) ?>')">Select</button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Service Selection (existing client) -->
<div id="serviceSelectionModal" class="modal-overlay">
    <div class="modal-box" style="max-width:440px;">
        <div class="modal-head"><h2>Select Service</h2><button class="modal-close-btn" data-close="serviceSelectionModal">&times;</button></div>
        <div class="modal-body">
            <p style="margin-bottom:14px;font-size:.87rem;">Client: <strong id="selectedClientName"></strong></p>
            <input type="hidden" id="selectedClientId">
            <div class="radio-group">
                <?php foreach ($services as $s): ?>
                    <label><input type="radio" name="selected_service" value="<?= $s['service_id'] ?>"> <?= htmlspecialchars($s['service_name']) ?></label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-secondary" data-close="serviceSelectionModal">Cancel</button>
            <button class="btn btn-navy" onclick="saveServiceToExisting()">Add Service</button>
        </div>
    </div>
</div>

<!-- Service Requests Modal -->
<div id="serviceRequestModal" class="modal-overlay">
    <div class="modal-box xl">
        <div class="modal-head"><h2>Pending Service Requests</h2><button class="modal-close-btn" data-close="serviceRequestModal">&times;</button></div>
        <div class="modal-body">
            <?php if (empty($pending_requests)): ?>
                <div class="empty-state">No pending requests at this time.</div>
            <?php else: ?>
                <table class="req-table">
                    <thead>
                        <tr>
                            <th>Client</th><th>Email</th><th>Phone</th><th>Service</th>
                            <th>Date / Time</th><th>Notes</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_requests as $req):
                            $rc = Client::findById($req['client_id']);
                            $rs = Service::findById($req['service_id']);
                        ?>
                        <tr>
                            <td><?= htmlspecialchars(($rc['first_name']??'').' '.($rc['last_name']??'')) ?></td>
                            <td><?= htmlspecialchars($rc['email']??'—') ?></td>
                            <td><?= htmlspecialchars($rc['phone']??'—') ?></td>
                            <td><?= htmlspecialchars($rs['service_name']??'—') ?></td>
                            <td><?= $req['preferred_date'] ?><br><small style="color:var(--c-text-muted);"><?= $req['preferred_time']?:'—' ?></small></td>
                            <td><?= htmlspecialchars($req['additional_notes']?:'None') ?></td>
                            <td style="white-space:nowrap;">
                                <button class="btn btn-success btn-sm" onclick="openOnboardingModal(<?= $req['request_id'] ?>,'<?= htmlspecialchars(addslashes(($rc['first_name']??'').' '.($rc['last_name']??''))) ?>','<?= htmlspecialchars(addslashes($rs['service_name']??'—')) ?>',<?= (int)$req['service_id'] ?>)">Accept</button>
                                <button class="btn btn-danger btn-sm" style="margin-left:4px;" onclick="rejectRequest(<?= $req['request_id'] ?>)">Reject</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add / Edit Client Modal -->
<div id="clientModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head"><h2 id="clientModalTitle">Add New Client</h2><button class="modal-close-btn" data-close="clientModal">&times;</button></div>
        <div class="modal-body">
            <form id="clientForm">
                <input type="hidden" id="client_id" name="client_id" value="0">
                <div class="form-row">
                    <div class="form-group"><label>First Name *</label><input type="text" id="first_name" name="first_name" required></div>
                    <div class="form-group"><label>Last Name *</label><input type="text" id="last_name" name="last_name" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Email *</label><input type="email" id="email" name="email" required></div>
                    <div class="form-group"><label>Phone</label><input type="tel" id="phone" name="phone"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Company</label><input type="text" id="company_name" name="company_name"></div>
                    <div class="form-group"><label>Business Type</label><input type="text" id="business_type" name="business_type"></div>
                </div>
                <div class="form-group" id="serviceSelectionDiv">
                    <label>Service * (select one)</label>
                    <div class="radio-group">
                        <?php foreach ($services as $s): ?>
                            <label><input type="radio" name="service_id" value="<?= $s['service_id'] ?>"> <?= htmlspecialchars($s['service_name']) ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-group" id="editPendingServicesDiv" style="display:none;">
                    <label>Change Pending Service</label>
                    <input type="hidden" id="client_service_id" name="client_service_id">
                    <div class="radio-group">
                        <?php foreach ($services as $s): ?>
                            <label><input type="radio" name="new_service_id" value="<?= $s['service_id'] ?>"> <?= htmlspecialchars($s['service_name']) ?></label>
                        <?php endforeach; ?>
                    </div>
                    <p class="form-hint">Only changeable while the service is still pending.</p>
                </div>
                <div class="form-group" id="activeServicesDiv" style="display:none;">
                    <label>Active Services</label>
                    <div id="activeServicesList" style="padding:10px;background:var(--c-page);border-radius:var(--radius-md);border:1px solid var(--c-border);min-height:36px;"></div>
                    <p class="form-hint">In-progress or completed services cannot be changed here.</p>
                </div>
            </form>
        </div>
        <div class="modal-foot">
            <button class="btn btn-secondary" data-close="clientModal">Cancel</button>
            <button class="btn btn-navy" onclick="saveClient()">Save</button>
        </div>
    </div>
</div>

<!-- Create User Modal -->
<div id="userModal" class="modal-overlay">
    <div class="modal-box" style="max-width:440px;">
        <div class="modal-head"><h2>Create Client Account</h2><button class="modal-close-btn" data-close="userModal">&times;</button></div>
        <div class="modal-body">
            <form id="userForm">
                <input type="hidden" id="user_client_id" name="client_id">
                <div class="form-group" style="margin-bottom:14px;"><label>Username *</label><input type="text" id="username" name="username" required autocomplete="off"></div>
                <div class="form-row">
                    <div class="form-group"><label>Password *</label><input type="password" id="password" name="password" required minlength="6"></div>
                    <div class="form-group"><label>Confirm Password *</label><input type="password" id="confirm_password" required minlength="6"></div>
                </div>
            </form>
        </div>
        <div class="modal-foot">
            <button class="btn btn-secondary" data-close="userModal">Cancel</button>
            <button class="btn btn-navy" onclick="saveUser()">Create Account</button>
        </div>
    </div>
</div>

<!-- Onboarding / Accept Modal -->
<div id="onboardingModal" class="modal-overlay">
    <div class="modal-box wide">
        <div class="modal-head"><h2>Accept &amp; Setup Service</h2><button class="modal-close-btn" data-close="onboardingModal">&times;</button></div>
        <div class="modal-body">
            <div class="ob-banner">
                <div class="ob-banner-text">
                    <strong id="ob-client-name">—</strong>
                    <span>Service: <strong id="ob-service-name">—</strong></span>
                </div>
            </div>
            <input type="hidden" id="ob-request-id">
            <input type="hidden" id="ob-service-id">

            <div class="ob-section-title">Define Steps &amp; Assign Staff</div>
            <p style="font-size:.81rem;color:var(--c-text-muted);margin:0 0 14px;">Set up the initial steps below. Additional steps can be added from the Tasks tab at any time.</p>

            <div class="ob-layout">
                <div class="ob-steps-main">
                    <div id="ob-steps-container"></div>
                    <button type="button" class="add-step-btn" id="ob-add-step-btn">+ Add Step</button>
                </div>
                <div class="suggestions-panel">
                    <h4>Common Steps</h4>
                    <div id="ob-suggestions-list"><p class="no-sug">No suggestions yet.</p></div>
                </div>
            </div>

            <div class="ob-section-title" style="margin-top:18px;">Deadline</div>
            <input type="date" id="ob-deadline" style="padding:8px 11px;border:1px solid var(--c-border);border-radius:var(--radius-md);font-size:.86rem;outline:none;">
        </div>
        <div class="modal-foot">
            <span class="skip-link" onclick="acceptWithoutSteps()">Skip setup for now</span>
            <button class="btn btn-success" id="ob-accept-btn" onclick="submitOnboarding()">Accept &amp; Save Setup</button>
        </div>
    </div>
</div>

<!-- Task: Assign/Edit Steps Modal -->
<div id="assignModal" class="modal-overlay">
    <div class="modal-box wide">
        <div class="modal-head"><h2>Assign Staff &amp; Define Steps</h2><button class="modal-close-btn" data-close="assignModal">&times;</button></div>
        <div class="modal-body">
            <p id="assignModalInfo" style="margin:0 0 16px;font-size:.85rem;color:var(--c-text-muted);"></p>
            <form id="assignStepsForm" method="post" action="../api/save_client_service_steps.php">
                <input type="hidden" name="client_service_id" id="assignModalCSId">
                <div style="display:flex;gap:16px;align-items:flex-start;">
                    <div style="flex:1;min-width:0;">
                        <div id="assignStepsContainer"></div>
                        <button type="button" class="add-step-btn" id="addAssignStepBtn" style="margin-top:8px;">+ Add Step</button>
                    </div>
                    <div class="suggestions-panel" id="assignSuggestionsPanel">
                        <h4>Common Steps</h4>
                        <div id="assignSuggestionsList"><p class="no-sug">No suggestions yet.</p></div>
                    </div>
                </div>
                <div style="margin-top:18px;">
                    <label style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:6px;color:var(--c-text-body);">Deadline</label>
                    <input type="date" name="deadline" id="assignDeadline" style="padding:8px 11px;border:1px solid var(--c-border);border-radius:var(--radius-md);font-size:.86rem;outline:none;">
                </div>
            </form>
        </div>
        <div class="modal-foot">
            <button class="btn btn-secondary" data-close="assignModal">Cancel</button>
            <button class="btn btn-navy" onclick="document.getElementById('assignStepsForm').requestSubmit()">Save Assignments</button>
        </div>
    </div>
</div>

<!-- Task: View-Only Modal -->
<div id="viewModal" class="modal-overlay">
    <div class="modal-box wide">
        <div class="modal-head"><h2>Service Details</h2><button class="modal-close-btn" data-close="viewModal">&times;</button></div>
        <div class="modal-body">
            <p id="viewModalInfo" style="margin:0 0 16px;font-size:.85rem;color:var(--c-text-muted);"></p>
            <div id="viewStepsContainer"></div>
            <div style="margin-top:14px;">
                <label style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:4px;color:var(--c-text-body);">Deadline</label>
                <div id="viewDeadline" style="font-weight:600;color:var(--c-text-head);font-size:.9rem;"></div>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-secondary" data-close="viewModal">Close</button>
        </div>
    </div>
</div>

<!-- Approval: File Preview Modal -->
<div id="previewModal" class="modal-overlay">
    <div class="modal-box wide">
        <div class="modal-head"><h2 id="previewTitle">Document Preview</h2><button class="modal-close-btn" data-close="previewModal">&times;</button></div>
        <div class="modal-body"><div id="previewContent"></div></div>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════════════════════════════
     JAVASCRIPT  (unchanged logic)
     ═══════════════════════════════════════════════════════════════════════════ -->
<script>
const Toast = Swal.mixin({ toast:true, position:'top-end', showConfirmButton:false, timer:3500, timerProgressBar:true });

const staffList = <?= json_encode(array_map(fn($s) => [
    'id'           => (int)$s['staff_id'],
    'name'         => trim($s['first_name'].' '.$s['last_name']),
    'active_tasks' => (int)$s['active_tasks_count'],
], $allStaff), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;

const staffSorted     = [...staffList].sort((a,b) => a.active_tasks - b.active_tasks);
const stepSuggestions = <?= json_encode($stepSuggestionsByService, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;
const stepInsights    = <?= json_encode($stepInsightsByService, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;
const csToService     = <?= json_encode($csToService, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;

/* ── Tab switching ───────────────────────────────────────────────── */
const urlParams = new URLSearchParams(location.search);
const initTab   = urlParams.get('tab') || 'clients';

document.querySelectorAll('.tab-btn').forEach(btn => {
    const id = btn.dataset.tab;
    if (id === initTab) { btn.classList.add('active'); document.getElementById('tab-'+id)?.classList.add('active'); }
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('tab-'+id)?.classList.add('active');
        const u = new URL(location); u.searchParams.set('tab', id); history.replaceState({}, '', u);
    });
});

/* ── Modal helpers ───────────────────────────────────────────────── */
function openModal(id)  { document.getElementById(id)?.classList.add('active'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('active'); }

document.addEventListener('click', e => {
    const closer = e.target.closest('[data-close]');
    if (closer) { closeModal(closer.dataset.close); return; }
    if (e.target.classList.contains('modal-overlay')) closeModal(e.target.id);
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
});

/* ── AJAX helpers ────────────────────────────────────────────────── */
function post(params) {
    return fetch('', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams(params).toString() }).then(r => r.json());
}
function postForm(fd) { return fetch('', {method:'POST', body:fd}).then(r => r.json()); }

/* ── Client tab ──────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('#tab-clients .filter-pill[data-filter]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('#tab-clients .filter-pill[data-filter]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const f = btn.dataset.filter;
            document.querySelectorAll('#clientTable tbody tr').forEach(r => {
                r.style.display = (f==='all' || r.dataset.status===f) ? '' : 'none';
            });
        });
    });

    document.getElementById('clientSearch')?.addEventListener('input', function() {
        const t = this.value.toLowerCase();
        document.querySelectorAll('#clientTable tbody tr').forEach(r => {
            r.style.display = r.textContent.toLowerCase().includes(t) ? '' : 'none';
        });
    });

    document.getElementById('existingClientSearch')?.addEventListener('input', function() {
        const t = this.value.toLowerCase();
        document.querySelectorAll('#existingClientModal .client-list-item').forEach(item => {
            item.style.display = item.dataset.search.includes(t) ? '' : 'none';
        });
    });

    document.querySelectorAll('.task-action-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const csId = btn.dataset.csId;
            const svcId = btn.dataset.serviceId;
            btn.dataset.action === 'view' ? openViewModal(csId) : openAssignModal(csId, svcId);
        });
    });

    document.getElementById('ob-add-step-btn')?.addEventListener('click', () => obAddStep());
    document.getElementById('addAssignStepBtn')?.addEventListener('click', () => assignAddStep());

    document.getElementById('assignStepsForm')?.addEventListener('submit', async e => {
        e.preventDefault();
        try {
            const res  = await fetch(e.target.action, {method:'POST', body:new FormData(e.target)});
            const data = await res.json();
            if (data.success) {
                Toast.fire({icon:'success', title:'Assignments saved!'});
                closeModal('assignModal');
                setTimeout(() => location.reload(), 1200);
            } else Swal.fire('Error', data.error || 'Could not save', 'error');
        } catch { Swal.fire('Error', 'Network error', 'error'); }
    });

    document.querySelectorAll('.approval-action-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const card    = this.closest('.approval-card');
            const reqId   = card.dataset.reqId;
            const staffId = card.dataset.staffId;
            const csId    = card.dataset.csId;
            const action  = this.dataset.action;
            const notes   = card.querySelector('.admin-notes').value.trim();
            const isApprove = action === 'completed';

            Swal.fire({
                title: `${isApprove ? 'Approve' : 'Reject'} this submission?`,
                text:  isApprove
                    ? 'The requirement will be marked as completed and staff will be notified.'
                    : 'The requirement will be returned to staff for revision.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: isApprove ? '#166534' : '#991b1b',
                confirmButtonText: isApprove ? 'Yes, approve' : 'Yes, reject',
            }).then(result => {
                if (!result.isConfirmed) return;
                Swal.fire({ title:'Processing…', allowOutsideClick:false, showConfirmButton:false, didOpen:()=>Swal.showLoading() });
                card.querySelectorAll('.btn').forEach(b => b.disabled = true);

                fetch('../api/update_requirement_status.php', {
                    method: 'POST',
                    headers: {'Content-Type':'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({ requirement_id:reqId, staff_id:staffId, cs_id:csId, status:action, admin_notes:notes })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({ icon:'success', title:isApprove?'Approved':'Rejected', text:data.message||'Done', timer:2000, showConfirmButton:false });
                        card.style.transition = 'all .3s';
                        card.style.opacity    = '0';
                        card.style.transform  = 'scale(.97)';
                        setTimeout(() => {
                            card.remove();
                            if (!document.querySelector('.approval-card')) {
                                document.getElementById('tab-approvals').innerHTML = `
                                    <div class="no-approvals">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <h3>All Clear</h3><p>No pending approvals at this time.</p>
                                    </div>`;
                            }
                        }, 2000);
                    } else {
                        Swal.fire({ icon:'error', title:'Failed', text:data.error||'Unknown error' });
                        card.querySelectorAll('.btn').forEach(b => b.disabled = false);
                    }
                })
                .catch(() => {
                    Swal.fire({ icon:'error', title:'Network Error', text:'Could not connect to server.' });
                    card.querySelectorAll('.btn').forEach(b => b.disabled = false);
                });
            });
        });
    });

    document.querySelectorAll('.approval-doc').forEach(item => {
        item.addEventListener('click', () => {
            const path = item.dataset.path, type = item.dataset.type, name = item.dataset.name;
            document.getElementById('previewTitle').textContent = name;
            const content = document.getElementById('previewContent');
            content.innerHTML = '';
            if (['jpg','jpeg','png','gif','webp'].includes(type))
                content.innerHTML = `<img src="${path}" class="preview-img" alt="Preview">`;
            else if (type === 'pdf')
                content.innerHTML = `<iframe src="${path}" class="preview-iframe"></iframe>`;
            else
                content.innerHTML = `<div class="preview-download"><p style="margin-bottom:16px;color:var(--c-text-muted);">Cannot preview this file type</p><a href="${path}" download>Download File</a></div>`;
            openModal('previewModal');
        });
    });
});

/* ── Client CRUD ─────────────────────────────────────────────────── */
function openAddClientModal() {
    closeModal('choiceModal');
    document.getElementById('clientModalTitle').textContent         = 'Add New Client';
    document.getElementById('clientForm').reset();
    document.getElementById('client_id').value                     = '0';
    document.getElementById('serviceSelectionDiv').style.display   = 'block';
    document.getElementById('editPendingServicesDiv').style.display = 'none';
    document.getElementById('activeServicesDiv').style.display      = 'none';
    openModal('clientModal');
}

async function openEditClientModal(clientId) {
    document.getElementById('clientModalTitle').textContent         = 'Edit Client';
    document.getElementById('serviceSelectionDiv').style.display   = 'none';
    document.getElementById('editPendingServicesDiv').style.display = 'none';
    document.getElementById('activeServicesDiv').style.display      = 'none';
    try {
        const data = await post({action:'get_client', client_id:clientId});
        if (!data?.client_id) throw new Error('No data');
        ['first_name','last_name','email','phone','company_name','business_type'].forEach(f => {
            document.getElementById(f).value = data[f] || '';
        });
        document.getElementById('client_id').value = data.client_id;
        if (data.pending_service) {
            document.getElementById('editPendingServicesDiv').style.display = 'block';
            document.getElementById('client_service_id').value = data.pending_service.client_service_id;
            const r = document.querySelector(`input[name="new_service_id"][value="${data.pending_service.service_id}"]`);
            if (r) r.checked = true;
        }
        if (data.active_services?.length) {
            document.getElementById('activeServicesDiv').style.display = 'block';
            document.getElementById('activeServicesList').innerHTML = data.active_services.map(s =>
                `<div style="padding:7px 10px;margin:3px 0;background:var(--c-surface);border-radius:var(--radius-sm);border-left:2px solid var(--c-blue);">
                    <strong style="color:var(--c-text-head);">${s.service_name}</strong>
                    <span style="font-size:.77rem;color:var(--c-text-muted);margin-left:8px;">${s.status.replace('_',' ')}</span>
                 </div>`
            ).join('');
        }
    } catch { Toast.fire({icon:'error', title:'Failed to load client'}); }
    openModal('clientModal');
}

function selectExistingClient(clientId, name) {
    document.getElementById('selectedClientId').value        = clientId;
    document.getElementById('selectedClientName').textContent = name;
    document.querySelectorAll('input[name="selected_service"]').forEach(r => r.checked = false);
    closeModal('existingClientModal');
    openModal('serviceSelectionModal');
}

async function saveServiceToExisting() {
    const clientId = document.getElementById('selectedClientId').value;
    const svcRadio = document.querySelector('input[name="selected_service"]:checked');
    if (!svcRadio) { Toast.fire({icon:'warning', title:'Please select a service'}); return; }
    try {
        const data = await post({action:'add_service_to_existing', client_id:clientId, service_id:svcRadio.value});
        if (data.success) { Toast.fire({icon:'success', title:'Service added!'}); closeModal('serviceSelectionModal'); setTimeout(()=>location.reload(),3600); }
        else Swal.fire('Error', data.message||'Failed', 'error');
    } catch { Swal.fire('Error','Network error','error'); }
}

async function saveClient() {
    let valid = true;
    ['first_name','last_name','email'].forEach(id => {
        const el = document.getElementById(id);
        if (!el.value.trim()) { el.style.borderColor='var(--c-danger)'; valid=false; }
        else el.style.borderColor='';
    });
    const isEdit = parseInt(document.getElementById('client_id').value) > 0;
    if (!isEdit && !document.querySelector('input[name="service_id"]:checked')) {
        Toast.fire({icon:'warning', title:'Please select a service'}); valid=false;
    }
    if (!valid) return;
    const fd = new FormData(document.getElementById('clientForm'));
    fd.append('action', isEdit ? 'edit_client' : 'add_client');
    try {
        const data = await postForm(fd);
        if (data.success) {
            if (!isEdit) {
                closeModal('clientModal');
                document.getElementById('user_client_id').value = data.client_id;
                document.getElementById('userForm').reset();
                Toast.fire({icon:'success', title:'Client created!'});
                openModal('userModal');
            } else {
                Toast.fire({icon:'success', title: data.service_updated ? 'Client & service updated!' : 'Client updated!'});
                closeModal('clientModal');
                setTimeout(()=>location.reload(),3600);
            }
        } else Swal.fire('Error', data.message||'Failed', 'error');
    } catch { Swal.fire('Error','Network error','error'); }
}

async function saveUser() {
    const pw = document.getElementById('password').value;
    const cp = document.getElementById('confirm_password').value;
    if (pw !== cp)    { Swal.fire('Error',"Passwords don't match!",'error'); return; }
    if (pw.length < 6){ Swal.fire('Error','Password too short (minimum 6 characters).','error'); return; }
    const fd = new FormData(document.getElementById('userForm'));
    fd.append('action','create_user');
    try {
        const data = await postForm(fd);
        if (data.success) { Toast.fire({icon:'success',title:'Account created!'}); closeModal('userModal'); setTimeout(()=>location.reload(),3600); }
        else Swal.fire('Error',data.message||'Failed','error');
    } catch { Swal.fire('Error','Network error','error'); }
}

async function rejectRequest(id) {
    const r = await Swal.fire({title:'Reject this request?',text:'This action cannot be undone.',icon:'warning',showCancelButton:true,confirmButtonText:'Yes, reject',confirmButtonColor:'#991b1b'});
    if (!r.isConfirmed) return;
    try {
        const data = await post({action:'reject_request', request_id:id});
        if (data.success) { Toast.fire({icon:'success',title:'Request rejected.'}); setTimeout(()=>location.reload(),3600); }
        else Swal.fire('Error',data.message||'Failed','error');
    } catch { Swal.fire('Error','Network error','error'); }
}

/* ── Step builder helpers ────────────────────────────────────────── */
function buildStaffOptions(selectedId=null) {
    let html = '<option value="">— Select staff —</option>';
    staffSorted.forEach((s,i) => {
        const label = s.active_tasks===0 ? 'Available' : `${s.active_tasks} task${s.active_tasks>1?'s':''}`;
        const rec   = i===0 ? ' (Recommended)' : '';
        const sel   = s.id==selectedId ? ' selected' : '';
        html += `<option value="${s.id}"${sel} data-tasks="${s.active_tasks}">${s.name} — ${label}${rec}</option>`;
    });
    return html;
}

function workloadHint(tasks) {
    if (tasks===0)  return {text:'Available', color:'var(--c-success)'};
    if (tasks>=10)  return {text:`High load: ${tasks} tasks`, color:'var(--c-danger)'};
    if (tasks>=5)   return {text:`${tasks} active tasks`, color:'var(--c-warn)'};
    return {text:`${tasks} active task${tasks>1?'s':''}`, color:'var(--c-blue)'};
}

function attachAC(input, getSugs) {
    const wrap = document.createElement('div');
    wrap.className = 'step-name-wrap';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);
    const drop = document.createElement('div');
    drop.className = 'autocomplete-list';
    wrap.appendChild(drop);
    let idx = -1;
    input.addEventListener('input', () => {
        const val = input.value.trim().toLowerCase();
        drop.innerHTML = ''; drop.style.display='none'; idx=-1;
        if (!val) return;
        const matches = getSugs().filter(s => s.toLowerCase().includes(val));
        if (!matches.length) return;
        matches.forEach(m => {
            const item = document.createElement('div');
            item.className='ac-item'; item.textContent=m;
            item.addEventListener('mousedown', e => { e.preventDefault(); input.value=m; drop.style.display='none'; });
            drop.appendChild(item);
        });
        drop.style.display='block';
    });
    input.addEventListener('keydown', e => {
        const items = drop.querySelectorAll('.ac-item');
        if (e.key==='ArrowDown') idx=Math.min(idx+1, items.length-1);
        else if (e.key==='ArrowUp') idx=Math.max(idx-1,0);
        else if (e.key==='Enter' && idx>=0) { e.preventDefault(); input.value=items[idx].textContent; drop.style.display='none'; return; }
        else if (e.key==='Escape') { drop.style.display='none'; return; }
        items.forEach((it,i) => it.classList.toggle('active', i===idx));
    });
    input.addEventListener('blur', () => setTimeout(()=>{ drop.style.display='none'; },150));
}

/* ── Onboarding modal ────────────────────────────────────────────── */
let obSugs = [], obActiveInput = null;

function openOnboardingModal(reqId, clientName, serviceName, serviceId) {
    document.getElementById('ob-request-id').value       = reqId;
    document.getElementById('ob-service-id').value       = serviceId;
    document.getElementById('ob-client-name').textContent  = clientName;
    document.getElementById('ob-service-name').textContent = serviceName;
    document.getElementById('ob-deadline').value = '';
    document.getElementById('ob-steps-container').innerHTML = '';
    obSugs = stepSuggestions[serviceId] || [];
    renderObSugs();
    const seedSteps = [...obSugs];
    if (seedSteps.length) {
        seedSteps.forEach(name => obAddStep(name));
    } else {
        obAddStep();
    }
    closeModal('serviceRequestModal');
    openModal('onboardingModal');
}

function renderObSugs() {
    const list = document.getElementById('ob-suggestions-list');
    if (!obSugs.length) { list.innerHTML='<p class="no-sug">No suggestions for this service yet.</p>'; return; }
    list.innerHTML = obSugs.map(n => `<button type="button" class="suggestion-chip" data-name="${n.replace(/"/g,'&quot;')}">${n}</button>`).join('');
    list.querySelectorAll('.suggestion-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            if (obActiveInput) { obActiveInput.value=chip.dataset.name; obActiveInput.dispatchEvent(new Event('input')); obActiveInput=null; }
            else obAddStep(chip.dataset.name);
        });
    });
}

function obAddStep(name='') {
    const cont  = document.getElementById('ob-steps-container');
    const order = cont.querySelectorAll('.step-row-ob').length + 1;
    const row   = document.createElement('div');
    row.className = 'step-row-ob';
    row.innerHTML = `
        <div class="step-num">${order}</div>
        <div class="step-inputs">
            <input type="text" class="step-name-input" placeholder="Step name…" value="${name.replace(/"/g,'&quot;')}" style="flex:1;min-width:155px;">
            <div class="staff-wrap">
                <select class="staff-select">${buildStaffOptions()}</select>
                <div class="workload-hint"></div>
            </div>
        </div>
        <button type="button" class="remove-step-btn">&times;</button>`;
    cont.appendChild(row);
    const nameInput = row.querySelector('.step-name-input');
    nameInput.addEventListener('focus', () => { obActiveInput = nameInput; });
    attachAC(nameInput, () => obSugs);
    const sel  = row.querySelector('.staff-select');
    const hint = row.querySelector('.workload-hint');
    sel.addEventListener('change', () => {
        const opt = sel.options[sel.selectedIndex]; const tasks = parseInt(opt?.dataset.tasks??-1);
        if (isNaN(tasks)||tasks<0) { hint.classList.remove('on'); return; }
        const h = workloadHint(tasks); hint.textContent=h.text; hint.style.color=h.color; hint.classList.add('on');
    });
    row.querySelector('.remove-step-btn').addEventListener('click', () => {
        if (cont.querySelectorAll('.step-row-ob').length===1) { Toast.fire({icon:'warning',title:'At least one step is required'}); return; }
        row.remove(); obRenumber();
    });
}

function obRenumber() {
    document.querySelectorAll('#ob-steps-container .step-row-ob').forEach((r,i) => r.querySelector('.step-num').textContent=i+1);
}

async function submitOnboarding() {
    const requestId = document.getElementById('ob-request-id').value;
    const deadline  = document.getElementById('ob-deadline').value;
    const rows      = document.querySelectorAll('#ob-steps-container .step-row-ob');
    const steps=[]; let valid=true;
    rows.forEach((row,i) => {
        const name=row.querySelector('.step-name-input').value.trim();
        const staffId=row.querySelector('.staff-select').value;
        if (!name||!staffId) valid=false;
        steps.push({order:i+1, name, staff_id:staffId});
    });
    if (!valid) { Toast.fire({icon:'warning',title:'Please fill all step names and assign staff'}); return; }
    const btn = document.getElementById('ob-accept-btn');
    btn.disabled=true; btn.textContent='Processing…';
    try {
        const acceptData = await post({action:'accept_request', request_id:requestId});
        if (!acceptData.success) throw new Error(acceptData.message||'Failed to accept');
        const csId = acceptData.client_service_id;
        if (!csId) throw new Error('No client_service_id returned');
        const fd = new FormData();
        fd.append('client_service_id', csId);
        if (deadline) fd.append('deadline', deadline);
        steps.forEach(s => { fd.append(`steps[${s.order}][name]`,s.name); fd.append(`steps[${s.order}][staff_id]`,s.staff_id); fd.append(`steps[${s.order}][order]`,s.order); fd.append(`steps[${s.order}][requirement_id]`,''); });
        const stepsData = await (await fetch('../api/save_client_service_steps.php',{method:'POST',body:fd})).json();
        if (!stepsData.success) throw new Error(stepsData.error||'Failed to save steps');
        Swal.fire({icon:'success',title:'Done!',text:'Request accepted and steps saved.',timer:2500,showConfirmButton:false});
        closeModal('onboardingModal');
        setTimeout(()=>location.reload(),2600);
    } catch(err) {
        Swal.fire({icon:'error',title:'Error',text:err.message});
        btn.disabled=false; btn.textContent='Accept & Save Setup';
    }
}

async function acceptWithoutSteps() {
    const r = await Swal.fire({title:'Skip setup?',text:'The request will be accepted, but no steps will be assigned yet.',icon:'question',showCancelButton:true,confirmButtonText:'Yes, accept only'});
    if (!r.isConfirmed) return;
    try {
        const data = await post({action:'accept_request', request_id:document.getElementById('ob-request-id').value});
        if (data.success) { Toast.fire({icon:'success',title:'Request accepted.'}); closeModal('onboardingModal'); setTimeout(()=>location.reload(),3600); }
        else throw new Error(data.message);
    } catch(err) { Swal.fire({icon:'error',title:'Error',text:err.message}); }
}

/* ── Task assign modal ───────────────────────────────────────────── */
let assignSugs=[], assignActiveInput=null;

function openAssignModal(csId, serviceId) {
    document.getElementById('assignModalCSId').value = csId;
    assignSugs = stepSuggestions[serviceId] || [];
    renderAssignSugs();
    loadCSData(csId, 'edit');
    openModal('assignModal');
}

function renderAssignSugs() {
    const list = document.getElementById('assignSuggestionsList');
    if (!assignSugs.length) { list.innerHTML='<p class="no-sug">No suggestions yet.</p>'; return; }
    list.innerHTML = assignSugs.map(n=>`<button type="button" class="suggestion-chip" data-name="${n.replace(/"/g,'&quot;')}">${n}</button>`).join('');
    list.querySelectorAll('.suggestion-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            if (assignActiveInput) { assignActiveInput.value=chip.dataset.name; assignActiveInput.dispatchEvent(new Event('input')); assignActiveInput=null; }
            else assignAddStep(chip.dataset.name);
        });
    });
}

function openViewModal(csId) { loadCSData(csId,'view'); openModal('viewModal'); }

async function loadCSData(csId, mode) {
    try {
        const res  = await fetch(`../api/get_client_service_details.php?client_service_id=${csId}`);
        const data = await res.json();
        if (!data.success) throw new Error(data.error);
        const info = `Client: <strong>${data.client_name}</strong> &nbsp;&middot;&nbsp; Service: <strong>${data.service_name}</strong>`;
        if (mode==='edit') {
            document.getElementById('assignModalInfo').innerHTML = info;
            document.getElementById('assignDeadline').value = data.deadline||'';
            const cont = document.getElementById('assignStepsContainer');
            cont.innerHTML='';
            if (data.steps?.length) {
                data.steps.forEach(s =>
                    assignAddStep(s.requirement_name||'', s.assigned_staff_id||null, s.requirement_id||null, s.status||'pending')
                );
            } else {
                const defaults = [...assignSugs];
                if (defaults.length) defaults.forEach(name => assignAddStep(name));
                else assignAddStep();
            }
        } else {
            document.getElementById('viewModalInfo').innerHTML = info;
            document.getElementById('viewDeadline').textContent = data.deadline||'Not set';
            const cont = document.getElementById('viewStepsContainer');
            cont.innerHTML='';
            if (!data.steps?.length) { cont.innerHTML='<p style="text-align:center;color:var(--c-text-muted);padding:24px;">No requirements defined.</p>'; return; }
            data.steps.forEach(s => {
                const color = {completed:'var(--c-success)',in_progress:'var(--c-info)',on_hold:'var(--c-warn)'}[s.status]||'var(--c-text-muted)';
                let filesHtml='';
                if (s.files?.length) {
                    filesHtml='<div class="files-section"><h4>Uploaded Files</h4>'+s.files.map(f=>`
                        <div class="file-row">
                            <span>${fileIcon(f.file_type)}</span>
                            <div><div class="file-name">${f.document_name}</div><div class="file-size">${fmtSize(f.file_size_kb)}</div></div>
                            <button class="view-file-btn" onclick="window.open('${f.document_url}','_blank')">View</button>
                        </div>`).join('')+'</div>';
                } else filesHtml='<div class="files-section"><div class="no-files">No files uploaded</div></div>';
                const div=document.createElement('div'); div.className='view-step-card';
                div.innerHTML=`
                    <div class="view-step-head">
                        <div class="step-num">${s.requirement_order}</div>
                        <div style="font-weight:600;color:var(--c-text-head);">${s.requirement_name}</div>
                        <div style="font-size:.82rem;color:var(--c-text-muted);">${s.assigned_staff_name||'—'}</div>
                        <div style="font-weight:700;font-size:.78rem;color:${color};text-transform:uppercase;letter-spacing:.04em;">${s.status.replace('_',' ')}</div>
                    </div>${filesHtml}`;
                cont.appendChild(div);
            });
        }
    } catch { Swal.fire({icon:'error',title:'Failed to load details',toast:true,position:'top-end',timer:4000}); }
}

function assignAddStep(name='', staffId=null, reqId=null, status='pending') {
    const cont  = document.getElementById('assignStepsContainer');
    const order = cont.querySelectorAll('.task-step-row').length + 1;
    const row   = document.createElement('div');
    row.className='task-step-row'; row.dataset.reqId=reqId||'';
    const canRemove = status==='pending';
    let badge='';
    if (status==='completed')   badge='<span class="s-badge s-completed">Done</span>';
    if (status==='in_progress') badge='<span class="s-badge s-in_progress">In Progress</span>';
    row.innerHTML=`
        <div class="step-num">${order}</div>
        <div style="flex:1;display:flex;flex-direction:column;gap:5px;">
            <input type="text" name="steps[${order}][name]" class="step-name-input"
                   placeholder="Step name…" value="${(name||'').replace(/"/g,'&quot;')}" required
                   style="padding:7px 10px;border:1px solid var(--c-border);border-radius:var(--radius-sm);font-size:.84rem;outline:none;">
            ${badge}
        </div>
        <div class="staff-wrap">
            <select name="steps[${order}][staff_id]" class="staff-select" required>${buildStaffOptions(staffId)}</select>
            <div class="workload-hint"></div>
        </div>
        ${canRemove?'<button type="button" class="remove-step-btn">&times;</button>':''}
        <input type="hidden" name="steps[${order}][order]" value="${order}">
        <input type="hidden" name="steps[${order}][requirement_id]" value="${reqId||''}">`;
    cont.appendChild(row);
    const nameInput=row.querySelector('.step-name-input');
    nameInput.addEventListener('focus',()=>{ assignActiveInput=nameInput; });
    attachAC(nameInput,()=>assignSugs);
    const sel=row.querySelector('.staff-select'), hint=row.querySelector('.workload-hint');
    sel.addEventListener('change',()=>{
        const opt=sel.options[sel.selectedIndex]; const tasks=parseInt(opt?.dataset.tasks??-1);
        if (isNaN(tasks)||tasks<0){hint.classList.remove('on');return;}
        const h=workloadHint(tasks); hint.textContent=h.text; hint.style.color=h.color; hint.classList.add('on');
    });
    if (staffId) sel.dispatchEvent(new Event('change'));
    if (canRemove) {
        row.querySelector('.remove-step-btn').addEventListener('click',()=>{
            Swal.fire({title:'Remove this step?',icon:'warning',showCancelButton:true,confirmButtonText:'Remove',confirmButtonColor:'#991b1b'})
            .then(r=>{ if(r.isConfirmed){row.remove();assignRenumber();} });
        });
    }
}

function assignRenumber() {
    document.querySelectorAll('#assignStepsContainer .task-step-row').forEach((r,i)=>{
        const n=i+1; r.querySelector('.step-num').textContent=n;
        const ni=r.querySelector('input[name*="[name]"]'), se=r.querySelector('select'),
              oi=r.querySelector('input[name*="[order]"]'), ri=r.querySelector('input[name*="[requirement_id]"]');
        if(ni) ni.name=`steps[${n}][name]`;
        if(se) se.name=`steps[${n}][staff_id]`;
        if(oi){oi.name=`steps[${n}][order]`;oi.value=n;}
        if(ri) ri.name=`steps[${n}][requirement_id]`;
    });
}

/* ── Utilities ───────────────────────────────────────────────────── */
function fileIcon(t) {
    if (!t) return '📄'; t=t.toLowerCase();
    if (t.includes('pdf'))  return '📕';
    if (t.includes('doc'))  return '📘';
    if (t.includes('xls'))  return '📗';
    if (t.includes('image')||t.includes('jpg')||t.includes('png')) return '🖼️';
    if (t.includes('zip'))  return '📦';
    return '📄';
}
function fmtSize(kb) { if(!kb) return 'Unknown size'; return kb<1024?kb+' KB':(kb/1024).toFixed(2)+' MB'; }
</script>
</body>
</html>