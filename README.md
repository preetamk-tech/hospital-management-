# 🏥 MediQueue - Smart Hospital Queue & Management System

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](https://php.net)
[![Database](https://img.shields.io/badge/Database-MySQL%20%7C%20TiDB%20Cloud-00758F?logo=mysql&logoColor=white)](https://tidbcloud.com)
[![Docker](https://img.shields.io/badge/Container-Docker%20Ready-2496ED?logo=docker&logoColor=white)](https://docker.com)
[![Maps](https://img.shields.io/badge/Maps-Leaflet.js%20%2B%20OSM-199900?logo=leaflet&logoColor=white)](https://leafletjs.com)
[![Video Consultation](https://img.shields.io/badge/Telemedicine-Jitsi%20Meet-17A8E8?logo=jitsi&logoColor=white)](https://meet.jit.si)
[![AI Assistant](https://img.shields.io/badge/AI%20Chatbot-Groq%20LLaMA%203.3-F05032?logo=meta&logoColor=white)](https://groq.com)

**MediQueue** is an enterprise-grade, full-stack Hospital Management System (HMS) engineered to eliminate hospital overcrowding and streamline operations. It features real-time queue management, priority-based token dispatching, remote virtual queues, live GPS ambulance tracking, browser-based telemedicine, inpatient bed allocation, pharmacy stock tracking, billing, and AI-powered patient assistance.

---

## 🌟 Key Features

### 1. 🎟️ Smart Priority Queue & Token System
- **Algorithmic Priority Sorting:**
  - `E` (**Emergency**): Immediate #1 priority in doctor queues.
  - `S` (**Senior Citizen** - Age 60+): Automated high-priority placement.
  - `G` (**General**): Regular appointments.
  - `V` (**Virtual Queue**): Allows patients to wait remotely from home and check in upon arrival.
- **Live Waiting Hall Dashboard (`live_dashboard.php`):** Public display screen updating in real time for hospital waiting halls.
- **Dynamic QR Code Generation:** Patients can scan their token QR code to track live status from their mobile devices.

### 2. 🚑 Emergency Ambulance Service & Live GPS Tracking
- **Interactive Leaflet.js Map:** Patients pin their exact pickup location or use one-click browser Geolocation.
- **Reverse Geocoding:** Auto-resolves street addresses via OpenStreetMap Nominatim.
- **Driver Dashboard:** Real-time trip status workflow (`assigned` → `dispatched` → `at_pickup` → `en_route` → `at_hospital` → `completed`) with live GPS coordinate streaming.

### 3. 📹 Browser-Based Telemedicine (Jitsi Meet)
- Zero third-party software installation required.
- End-to-end encrypted video consultation rooms connecting doctors and remote patients.
- One-click prescription writer and instant PDF download upon consultation completion.

### 4. 🛏️ Inpatient Bed & Ward Management
- Visual occupancy tracking across **General, Semi-Private, Private, ICU, Emergency, Pediatric, and Maternity** wards.
- Live bed availability indicators, admission timestamps, and expected discharge dates.

### 5. 💊 Pharmacy & Inventory Control
- Track stock quantities, expiry dates, batch numbers, and automatic reorder thresholds.
- Direct prescription-to-pharmacy dispensing.

### 6. 💳 Automated Invoicing & Lab Management
- Itemized billing with procedures, consultation fees, bed charges, and discounts.
- Lab test bookings, diagnostic catalog, and report generation.

### 7. 🤖 MediBot - AI Hospital Assistant
- Hybrid AI architecture: Powered by **Groq Cloud API (`llama-3.3-70b-versatile`)** with an offline intent-matching fallback engine to guide visitors and patients.

### 8. 🛡️ Security & Audit Compliance
- **CSRF Protection** across all mutating requests.
- **Rate Limiting & Brute-Force Defense** tracking failed login attempts.
- **SQL Injection Prevention** with PDO Prepared Statements.
- **Audit Logs:** Immutable audit trail recording user IDs, IPs, timestamps, and JSON-diffs of modified rows.

---

## 👥 Role-Based Access Control (RBAC)

The application provides 5 tailored dashboards:

| Role | Access URL | Capabilities |
|---|---|---|
| 👑 **Admin** | `/admin/dashboard.php` | Full hospital analytics, doctor schedules, beds, pharmacy, billing, audit logs |
| 🩺 **Doctor** | `/doctor/dashboard.php` | Today's patient queue, Call Next, Jitsi video consult, vitals, PDF prescriptions |
| 📋 **Receptionist** | `/receptionist/dashboard.php` | Walk-in registration, emergency tokens, ambulance dispatch, queue overview |
| 🧑‍🦱 **Patient** | `/patient/dashboard.php` | Token tracker, book appointments, virtual queue, ambulance request, medical history |
| 🚐 **Driver** | `/drivers/dashboard.php` | Active ambulance trips, navigation route map, live GPS location broadcaster |

---

## 🛠️ Technology Stack

- **Backend:** PHP 8.2 (Vanilla OOP & PDO)
- **Database:** MySQL / TiDB Cloud Serverless
- **Real-Time Engine:** Ratchet WebSockets (`cboden/ratchet`) & Auto-Polling Fallback
- **Mailing:** PHPMailer (`phpmailer/phpmailer`) with Gmail SMTP / TLS
- **Frontend:** HTML5, CSS3, Modern JavaScript (ES6+), CSS Grid/Flexbox
- **Icons & Fonts:** Font Awesome 6.5.1, Google Fonts (*Inter* & *Poppins*)
- **Maps:** Leaflet.js 1.9.4 & OpenStreetMap
- **Video:** Jitsi Meet API (`meet.jit.si`)
- **Containerization:** Docker (`php:8.2-apache`)
- **Cloud Hosting Target:** Render.com (Web Service) + TiDB Cloud (Serverless MySQL)

---

## 📂 Project Structure

```
hospital/
├── admin/               # Super Admin modules (Analytics, Pharmacy, Beds, Billing)
├── api/                 # REST API endpoints (Chatbot, Tracking, Tokens, PDF, QR)
├── assets/              # Static assets (CSS, JS, Images, Icons)
│   ├── css/             # Stylesheets (Responsive design, dark mode, animations)
│   └── js/              # Client scripts (Leaflet map, chatbot, notifications, websocket)
├── auth/                # Authentication (Login, Register, OTP, Reset Password)
├── config/              # Central configurations
│   ├── db.php           # PDO database connection & cloud SSL handler
│   ├── email.php        # PHPMailer SMTP credentials
│   └── security.php     # CSRF tokens, rate limiting, and sanitizers
├── doctor/              # Doctor portal (Consultations, queue, prescriptions, vitals)
├── drivers/             # Ambulance driver interface & location broadcaster
├── includes/            # Reusable header, footer, navigation & audit helper
├── patient/             # Patient self-service portal & ambulance booking
├── receptionist/        # Receptionist walk-in & emergency token management
├── sql/                 # Database schema and seed data
│   ├── schema.sql       # Complete DDL table definitions
│   └── seed.sql         # Default test users and medical records
├── websocket/           # WebSocket server daemon (Ratchet)
├── Dockerfile           # Production container configuration for Render
└── index.php            # Public landing page
```

---

## 🚀 Quick Start Guide (Local Setup with XAMPP)

### Prerequisites
- [XAMPP](https://www.apachefriends.org/) (PHP 8.1+ & MySQL)
- Git

### Installation Steps

1. **Clone the repository into your XAMPP web root:**
   ```bash
   cd /Applications/XAMPP/xamppfiles/htdocs/  # On macOS
   # OR C:\xampp\htdocs\                      # On Windows
   git clone https://github.com/YOUR_USERNAME/hospital-management.git hospital
   cd hospital
   ```

2. **Start Apache & MySQL in XAMPP.**

3. **Import Database:**
   - Open `http://localhost/phpmyadmin` in your browser.
   - Create a new database named `hospital_queue` (`utf8mb4_general_ci`).
   - Import `sql/schema.sql` followed by `sql/seed.sql`.

4. **Launch the Real-time WebSocket Server (Optional for Live Push):**
   ```bash
   bash start_websocket.sh
   ```

5. **Open in Browser:**
   ```
   http://localhost/hospital/
   ```

---

## 🔑 Demo Login Credentials

Pre-configured accounts for testing:

| Role | Email | Password |
|---|---|---|
| **Admin** | `admin@hospital.com` | `Admin@123` |
| **Doctor** | `dr.rajesh@hospital.com` | `Doctor@123` |
| **Receptionist** | `reception@hospital.com` | `Reception@123` |
| **Patient** | `patient@example.com` | `Patient@123` |

---

## ☁️ 24/7 Cloud Deployment (Render + TiDB Cloud)

This project includes a production-ready `Dockerfile` and cloud database adapter.

1. **Database:** Deploy a free MySQL cluster on [TiDB Cloud](https://tidbcloud.com) (Serverless tier) and import `sql/schema.sql`.
2. **Web Service:** Push this repo to GitHub and connect it to [Render.com](https://render.com) as a **Web Service** (Docker environment).
3. Set the following environment variables on Render (or use the pre-configured defaults in `config/db.php`):
   - `DB_HOST`: Your TiDB / MySQL host
   - `DB_PORT`: `4000` (or `3306`)
   - `DB_NAME`: `hospital_queue`
   - `DB_USER`: Database username
   - `DB_PASS`: Database password

---

## 📄 License
This project is open-source and available under the [MIT License](LICENSE).
