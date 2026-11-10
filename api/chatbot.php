<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = array_filter(array_map('trim', explode(',', getenv('ALLOWED_API_ORIGINS') ?: 'http://localhost,http://127.0.0.1')));
if ($origin && in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$message = $input['message'] ?? '';
$history = $input['history'] ?? [];
$context = $input['context'] ?? [];
$role = $_SESSION['role'] ?? ($context['role'] ?? 'visitor');
$page = $context['page'] ?? basename($_SERVER['HTTP_REFERER'] ?? '');
$userName = $_SESSION['full_name'] ?? ($context['userName'] ?? 'User');

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is required']);
    exit;
}

function localCommandReply($message, $role) {
    $text = strtolower($message);
    $routes = [
        'visitor' => [
            ['keys' => ['login', 'sign in'], 'reply' => "Use the Login option from the home page and enter your registered email and password. After login, the system opens the right dashboard for your role: patient, doctor, receptionist, or admin."],
            ['keys' => ['register', 'sign up'], 'reply' => "Use Register as Patient from the home page, fill your personal details, and create a strong password. After approval/login, you can book appointments and track tokens."],
            ['keys' => ['appointment', 'book'], 'reply' => "Patients can book appointments after login. Receptionists can also create walk-in and emergency appointments from their dashboard."],
            ['keys' => ['queue', 'live', 'token'], 'reply' => "Open Live Queue from the home page to see current queue movement. Logged-in patients can track their own token and virtual queue status."],
            ['keys' => ['video', 'consult'], 'reply' => "Yes. The system supports online video consultation through consultation rooms. A patient joins from Consultations when a scheduled video visit is available."],
            ['keys' => ['service', 'module', 'available'], 'reply' => "Available modules include appointments, real-time queue, virtual queue, video consultation, prescriptions, lab tests, billing, pharmacy, beds, ambulance, insurance, analytics, attendance, and audit logs."]
        ],
        'patient' => [
            ['keys' => ['track', 'token', 'queue position'], 'reply' => "Open My Token to see your token number, queue position, current serving token, and estimated wait time. Use Virtual Queue if you want to wait remotely."],
            ['keys' => ['book appointment', 'appointment'], 'reply' => "Open Book Appointment, choose patient, doctor, and date, then submit. After booking, the system creates a token and can send an appointment confirmation email."],
            ['keys' => ['virtual queue', 'remote'], 'reply' => "Open Join Virtual Queue, select an available doctor, and join. You can track your V-token from Virtual Queue and check in when called."],
            ['keys' => ['lab', 'result'], 'reply' => "Open Lab Tests to book a test and view completed results. Admin manages available tests and uploads results."],
            ['keys' => ['prescription', 'pdf'], 'reply' => "Open Medical History. If the doctor generated a prescription PDF, use the PDF/download action beside that visit."],
            ['keys' => ['video', 'consult'], 'reply' => "Open Consultations and click Join when a scheduled online consultation is available. The video room opens using the consultation room page."]
        ],
        'doctor' => [
            ['keys' => ['call', 'token'], 'reply' => "Use Doctor Dashboard to call the next waiting token. The system updates the queue and sends a token-called notification/email to the patient when email is configured."],
            ['keys' => ['vital', 'bp', 'spo2', 'temperature'], 'reply' => "Open Vitals, select the patient/appointment, and record BP, temperature, SpO2, heart rate, weight, and notes."],
            ['keys' => ['prescription', 'template'], 'reply' => "Open Prescription Templates to create reusable templates. During medical record entry, use diagnosis, prescription, notes, and then generate/download PDF if needed."],
            ['keys' => ['follow'], 'reply' => "Open Follow-ups to schedule the next visit for a patient after consultation."],
            ['keys' => ['video', 'consult'], 'reply' => "Open Telemedicine, schedule or start a consultation, then use the consultation room to join the Jitsi video call."],
            ['keys' => ['leave'], 'reply' => "Open Leave Management, submit your leave dates and reason, and wait for admin approval."]
        ],
        'receptionist' => [
            ['keys' => ['walk', 'token'], 'reply' => "Open Walk-in Appointment to register a walk-in patient and generate a token."],
            ['keys' => ['emergency'], 'reply' => "Open Emergency Booking to create a priority emergency token."],
            ['keys' => ['queue'], 'reply' => "Open View Queue to monitor waiting, called, completed, and cancelled tokens for today."],
            ['keys' => ['ambulance'], 'reply' => "Open Ambulance to view or manage active ambulance bookings and statuses."],
            ['keys' => ['history'], 'reply' => "Open Patient History and search by patient details to review appointments, feedback, and medical records."]
        ],
        'admin' => [
            ['keys' => ['analytics', 'chart'], 'reply' => "Open Analytics to view users, appointments, tokens, queue time, doctor performance, department stats, revenue, and bed occupancy charts."],
            ['keys' => ['billing', 'invoice'], 'reply' => "Open Billing & Invoices to create invoices, add invoice items, update payment status, and track patient payable amounts."],
            ['keys' => ['pharmacy', 'inventory', 'stock'], 'reply' => "Open Pharmacy to manage medicine categories, stock, expiry dates, reorder levels, and availability."],
            ['keys' => ['bed', 'room'], 'reply' => "Open Bed Management to create wards, add beds, assign patients, and monitor occupancy."],
            ['keys' => ['audit', 'compliance'], 'reply' => "Audit logs record important system actions with user, IP, before/after values, and timestamp. Use them to trace sensitive operations."],
            ['keys' => ['attendance', 'staff'], 'reply' => "Open Attendance to clock staff in/out, mark absences, and review daily attendance status."]
        ]
    ];

    foreach (($routes[$role] ?? []) as $item) {
        foreach ($item['keys'] as $key) {
            if (strpos($text, $key) !== false) {
                return $item['reply'];
            }
        }
    }

    $featureList = "Important modules: real-time queue, token emails/reminders, video consultation, prescription PDF, lab tests/results, vitals, follow-ups, billing, pharmacy, analytics, beds, ambulance, insurance, attendance, audit logs, virtual queue, and mobile API.";
    return $featureList . " Ask me things like: track my token, book appointment, lab results, prescription PDF, video consult, billing, inventory, or analytics.";
}

$apiKey = getenv('GROQ_API_KEY') ?: '';
if (!$apiKey) {
    echo json_encode(['reply' => localCommandReply($message, $role)]);
    exit;
}

$systemPrompt = "You are MediBot, a helpful AI assistant inside the MediQueue Hospital Management System.
Current user: {$userName}
Current role: {$role}
Current page: {$page}

Guide users only through features available in this project:
- Real-time queue updates and token tracking
- Token call emails and appointment reminders
- Online video consultation using consultation rooms
- Prescription PDF downloads and medical history
- Lab booking and results
- Vitals, follow-ups, pharmacy, billing, insurance, beds, ambulance, attendance, analytics, audit logs
- Virtual queue and mobile app/API

Be friendly, professional, and concise. Always remind users to consult doctors for medical advice. Respond in the same language the user writes in.";

$messages = [['role' => 'system', 'content' => $systemPrompt]];

foreach ($history as $msg) {
    $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
}

$messages[] = ['role' => 'user', 'content' => $message];

$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'model' => 'llama-3.3-70b-versatile',
        'messages' => $messages,
        'temperature' => 0.7,
        'max_tokens' => 500
    ])
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to get response from AI']);
    exit;
}

$data = json_decode($response, true);
$reply = $data['choices'][0]['message']['content'] ?? 'Sorry, I could not process your request.';

echo json_encode(['reply' => $reply]);
