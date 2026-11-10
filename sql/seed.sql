USE hospital_queue;

-- Demo users. Passwords:
-- admin@hospital.com / Admin@123
-- dr.*@hospital.com / Doctor@123
-- reception@hospital.com / Reception@123
-- patient@example.com / Patient@123
INSERT IGNORE INTO users (full_name, email, password, role, is_active) VALUES
('Admin User', 'admin@hospital.com', '$2y$12$4cmXtNDQx7dcK5yrCU3ATevUX8LXtz.rXrcvzDjq7R6B6PDwrXS4G', 'admin', 1),
('Dr. Rajesh Sharma', 'dr.rajesh@hospital.com', '$2y$12$o3dRJYb5foi0WPIfzQIZ.ec8d6s7G1D6fWvxQf17E35.ntt21qi/u', 'doctor', 1),
('Dr. Priya Singh', 'dr.priya@hospital.com', '$2y$12$o3dRJYb5foi0WPIfzQIZ.ec8d6s7G1D6fWvxQf17E35.ntt21qi/u', 'doctor', 1),
('Dr. Amit Verma', 'dr.amit@hospital.com', '$2y$12$o3dRJYb5foi0WPIfzQIZ.ec8d6s7G1D6fWvxQf17E35.ntt21qi/u', 'doctor', 1),
('Dr. Neha Gupta', 'dr.neha@hospital.com', '$2y$12$o3dRJYb5foi0WPIfzQIZ.ec8d6s7G1D6fWvxQf17E35.ntt21qi/u', 'doctor', 1),
('Dr. Sanjay Patel', 'dr.sanjay@hospital.com', '$2y$12$o3dRJYb5foi0WPIfzQIZ.ec8d6s7G1D6fWvxQf17E35.ntt21qi/u', 'doctor', 1),
('Reception User', 'reception@hospital.com', '$2y$12$jlu.uZvqUN/7rCrqH/JiWOqkiArqQTHu3eF9p7npip4ofGW9Q7qwO', 'receptionist', 1),
('John Doe', 'patient@example.com', '$2y$12$bCj4liW.cK8UN2HHVaZggOgUljLQxK847IlgL6zSazZCSyu4IgnGe', 'patient', 1);

INSERT IGNORE INTO departments (department_name) VALUES
('Cardiology'),
('Orthopedics'),
('Neurology'),
('Pediatrics'),
('Gynecology'),
('General Medicine'),
('ENT'),
('Ophthalmology');

INSERT IGNORE INTO doctors (user_id, full_name, department_id, specialization) VALUES
((SELECT user_id FROM users WHERE email = 'dr.rajesh@hospital.com'), 'Dr. Rajesh Sharma', (SELECT department_id FROM departments WHERE department_name = 'Cardiology'), 'Heart Specialist'),
((SELECT user_id FROM users WHERE email = 'dr.priya@hospital.com'), 'Dr. Priya Singh', (SELECT department_id FROM departments WHERE department_name = 'Orthopedics'), 'Bone Specialist'),
((SELECT user_id FROM users WHERE email = 'dr.amit@hospital.com'), 'Dr. Amit Verma', (SELECT department_id FROM departments WHERE department_name = 'Neurology'), 'Brain Specialist'),
((SELECT user_id FROM users WHERE email = 'dr.neha@hospital.com'), 'Dr. Neha Gupta', (SELECT department_id FROM departments WHERE department_name = 'Pediatrics'), 'Child Specialist'),
((SELECT user_id FROM users WHERE email = 'dr.sanjay@hospital.com'), 'Dr. Sanjay Patel', (SELECT department_id FROM departments WHERE department_name = 'Gynecology'), 'Women Health');

INSERT INTO patients (user_id, full_name, gender, age, relation, phone, is_senior)
SELECT user_id, 'John Doe', 'Male', 35, 'Self', '9876543210', 0
FROM users
WHERE email = 'patient@example.com'
  AND NOT EXISTS (SELECT 1 FROM patients WHERE phone = '9876543210');

INSERT IGNORE INTO doctor_schedule (doctor_id, day_of_week, start_time, end_time, slot_duration, is_active)
SELECT d.doctor_id, days.day_num, '09:00:00', '17:00:00', 10, 1
FROM doctors d
JOIN (
    SELECT 1 AS day_num UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
) days
WHERE d.full_name IN ('Dr. Rajesh Sharma', 'Dr. Priya Singh', 'Dr. Amit Verma', 'Dr. Neha Gupta', 'Dr. Sanjay Patel');

INSERT IGNORE INTO lab_tests (test_name, department, price, description) VALUES
('Complete Blood Count (CBC)', 'Hematology', 500.00, 'Measures RBC, WBC, platelets, hemoglobin'),
('Blood Sugar (Fasting)', 'Pathology', 200.00, 'Fasting blood glucose test'),
('Blood Sugar (PP)', 'Pathology', 250.00, 'Post-prandial blood glucose test'),
('Lipid Profile', 'Pathology', 800.00, 'Cholesterol and triglyceride levels'),
('Liver Function Test (LFT)', 'Pathology', 600.00, 'Liver enzymes and bilirubin'),
('Kidney Function Test (KFT)', 'Pathology', 700.00, 'Creatinine, urea, electrolytes'),
('Thyroid Profile (T3, T4, TSH)', 'Endocrinology', 900.00, 'Thyroid hormone levels'),
('Urine Analysis', 'Pathology', 300.00, 'Complete urine examination'),
('X-Ray Chest', 'Radiology', 500.00, 'Chest X-ray PA view'),
('ECG', 'Cardiology', 400.00, 'Electrocardiogram'),
('Ultrasound Abdomen', 'Radiology', 1200.00, 'Abdominal ultrasound'),
('HbA1c', 'Pathology', 600.00, 'Glycated hemoglobin test'),
('Dengue NS1 Antigen', 'Microbiology', 800.00, 'Dengue fever detection'),
('Typhoid (Widal Test)', 'Microbiology', 400.00, 'Typhoid fever detection'),
('Vitamin D', 'Pathology', 1000.00, 'Vitamin D levels');

INSERT IGNORE INTO insurance_providers (provider_name, contact_phone, contact_email) VALUES
('Star Health Insurance', '1800-425-2255', 'support@starhealth.in'),
('ICICI Lombard', '1800-266-6646', 'health@icicilombard.com'),
('HDFC Ergo', '022-6234-6234', 'support@hdfcergo.com'),
('Max Bupa', '1860-500-8888', 'support@maxbupa.com'),
('Bajaj Allianz', '1800-22-5858', 'health@bajajallianz.co.in');

INSERT IGNORE INTO wards (ward_name, ward_type, total_beds, description) VALUES
('General Ward A', 'general', 20, 'General ward with basic facilities'),
('General Ward B', 'general', 20, 'General ward with basic facilities'),
('Semi Private Ward', 'semi_private', 10, 'Shared rooms with 2 beds'),
('Private Ward', 'private', 8, 'Single occupancy rooms'),
('ICU', 'icu', 10, 'Intensive Care Unit'),
('Emergency Ward', 'emergency', 6, 'Emergency observation beds'),
('Pediatric Ward', 'pediatric', 8, 'Children ward'),
('Maternity Ward', 'maternity', 10, 'Maternity and delivery ward');

INSERT IGNORE INTO beds (ward_id, bed_number, floor)
SELECT w.ward_id, CONCAT(LEFT(w.ward_name, 1), '-', n.n), FLOOR((n.n - 1) / 10) + 1
FROM wards w
JOIN (
    SELECT 1 AS n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
    UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10
    UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15
    UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20
) n ON n.n <= w.total_beds;

INSERT IGNORE INTO pharmacy_categories (category_name) VALUES
('Tablets'),
('Capsules'),
('Syrups'),
('Injections'),
('Ointments & Creams'),
('Drops'),
('Inhalers'),
('Surgical Items');

INSERT IGNORE INTO billing_items (item_name, item_type, price) VALUES
('General Consultation', 'consultation', 300.00),
('Specialist Consultation', 'consultation', 500.00),
('Senior Doctor Consultation', 'consultation', 800.00),
('General Ward (per day)', 'bed', 500.00),
('Semi Private (per day)', 'bed', 1500.00),
('Private Room (per day)', 'bed', 3000.00),
('ICU (per day)', 'bed', 5000.00),
('X-Ray', 'procedure', 500.00),
('ECG', 'procedure', 400.00),
('Ultrasound', 'procedure', 1200.00),
('Dressing/Bandage', 'procedure', 200.00),
('IV Fluid', 'procedure', 300.00);

INSERT IGNORE INTO prescription_templates (doctor_id, template_name, condition_type, diagnosis, prescription, notes)
SELECT doctor_id, 'Common Cold & Flu', 'Respiratory', 'Upper Respiratory Tract Infection',
       'Tab. Cetirizine 10mg - 1 at night for 5 days\nTab. Paracetamol 500mg - SOS for fever\nSyp. Cough Syrup - 10ml TDS for 5 days',
       'Rest, hydration, steam inhalation advised'
FROM doctors WHERE full_name = 'Dr. Rajesh Sharma';

INSERT IGNORE INTO prescription_templates (doctor_id, template_name, condition_type, diagnosis, prescription, notes)
SELECT doctor_id, 'Hypertension', 'Cardiology', 'Essential Hypertension',
       'Tab. Amlodipine 5mg - 1 daily morning\nTab. Telmisartan 40mg - 1 daily night',
       'Low salt diet, regular BP monitoring, follow up after 2 weeks'
FROM doctors WHERE full_name = 'Dr. Rajesh Sharma';

INSERT IGNORE INTO prescription_templates (doctor_id, template_name, condition_type, diagnosis, prescription, notes)
SELECT doctor_id, 'Diabetes Type 2', 'Endocrinology', 'Type 2 Diabetes Mellitus',
       'Tab. Metformin 500mg - 1 BD after meals\nTab. Glimepiride 1mg - 1 before breakfast',
       'Diabetic diet, exercise, monitor blood sugar, HbA1c after 3 months'
FROM doctors WHERE full_name = 'Dr. Rajesh Sharma';

INSERT IGNORE INTO prescription_templates (doctor_id, template_name, condition_type, diagnosis, prescription, notes)
SELECT doctor_id, 'Acid Reflux / GERD', 'Gastroenterology', 'Gastroesophageal Reflux Disease',
       'Cap. Pantoprazole 40mg - 1 empty stomach morning\nSyp. Antacid Gel - 10ml TDS after meals',
       'Avoid spicy/oily food, small frequent meals, no late night eating'
FROM doctors WHERE full_name = 'Dr. Rajesh Sharma';

INSERT IGNORE INTO ambulances (vehicle_number, driver_name, driver_phone, ambulance_type, status, current_lat, current_lng) VALUES
('AMB-001', 'Ravi Kumar', '9876500001', 'basic', 'available', 19.0760000, 72.8777000),
('AMB-002', 'Suresh Yadav', '9876500002', 'bls', 'available', 19.0760000, 72.8777000),
('AMB-003', 'Amit Singh', '9876500003', 'als', 'available', 19.0760000, 72.8777000);

-- Sample consultations (doctor must start these for patients to join)
INSERT IGNORE INTO consultations (appointment_id, patient_id, doctor_id, room_name, scheduled_at, duration_minutes, status)
SELECT 
    a.appointment_id, 
    a.patient_id, 
    a.doctor_id, 
    CONCAT('medi-', a.doctor_id, '-demo-room'),
    DATE_ADD(NOW(), INTERVAL 2 MINUTE),
    30,
    'scheduled'
FROM appointments a
WHERE a.status = 'confirmed'
LIMIT 2;
