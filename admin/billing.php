<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('auth/login.php');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $action = $_POST['action'];
    
    if ($action == 'add_item') {
        $stmt = $pdo->prepare("INSERT INTO billing_items (item_name, item_type, price) VALUES (?, ?, ?)");
        $stmt->execute([sanitize($_POST['item_name']), sanitize($_POST['item_type']), (float)$_POST['price']]);
        $message = 'Billing item added!';
    } elseif ($action == 'create_invoice') {
        $patient_id = (int)$_POST['patient_id'];
        $appointment_id = isset($_POST['appointment_id']) && $_POST['appointment_id'] ? (int)$_POST['appointment_id'] : null;
        $total = 0;
        $items = [];
        
        $inv_num = 'INV-' . date('Y') . '-' . str_pad($pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_number, 10) AS UNSIGNED)), 0) + 1 FROM invoices")->fetchColumn(), 5, '0', STR_PAD_LEFT);
        
        $pdo->beginTransaction();
        try {
            foreach ($_POST['item_name'] as $i => $name) {
                if ($name) {
                    $qty = (int)($_POST['item_qty'][$i] ?? 1);
                    $price = (float)($_POST['item_price'][$i] ?? 0);
                    $line_total = $qty * $price;
                    $total += $line_total;
                    $items[] = ['name' => $name, 'type' => $_POST['item_type'][$i] ?? 'other', 'qty' => $qty, 'price' => $price, 'total' => $line_total];
                }
            }
            
            $discount = (float)($_POST['discount'] ?? 0);
            $tax_rate = (float)($_POST['tax_rate'] ?? 0);
            $tax = ($total - $discount) * ($tax_rate / 100);
            $final = $total - $discount + $tax;
            
            $stmt = $pdo->prepare("INSERT INTO invoices (invoice_number, patient_id, appointment_id, total_amount, discount, tax, final_amount, patient_payable, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$inv_num, $patient_id, $appointment_id, $total, $discount, $tax, $final, $final]);
            $invoice_id = $pdo->lastInsertId();
            
            $stmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, item_name, item_type, quantity, unit_price, total) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($items as $item) {
                $stmt->execute([$invoice_id, $item['name'], $item['type'], $item['qty'], $item['price'], $item['total']]);
            }
            
            $pdo->commit();
            setMessage("Invoice $inv_num created!", "success");
            redirect("admin/billing.php?view=" . $invoice_id);
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to create invoice!';
        }
    } elseif ($action == 'pay') {
        $invoice_id = (int)$_POST['invoice_id'];
        $payment_method = sanitize($_POST['payment_method']);
        $paid_amount = (float)$_POST['paid_amount'];
        
        $stmt = $pdo->prepare("UPDATE invoices SET status = CASE WHEN paid_amount + ? >= final_amount THEN 'paid' ELSE 'partially_paid' END, payment_method = ?, paid_amount = paid_amount + ?, paid_at = NOW() WHERE invoice_id = ?");
        $stmt->execute([$paid_amount, $payment_method, $paid_amount, $invoice_id]);
        $message = 'Payment recorded!';
    }
}

$billing_items = [];
$stmt = $pdo->prepare("SELECT * FROM billing_items WHERE is_active = 1 ORDER BY item_type, item_name");
$stmt->execute();
$billing_items = $stmt->fetchAll();

$patients = [];
$stmt = $pdo->prepare("SELECT * FROM patients ORDER BY full_name");
$stmt->execute();
$patients = $stmt->fetchAll();

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$invoices = [];
$sql = "SELECT i.*, p.full_name as patient_name FROM invoices i JOIN patients p ON i.patient_id = p.patient_id WHERE 1=1";
$params = [];
if ($filter != 'all') { $sql .= " AND i.status = ?"; $params[] = $filter; }
$sql .= " ORDER BY i.created_at DESC LIMIT 50";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

$selected_invoice = null;
if (isset($_GET['view'])) {
    $stmt = $pdo->prepare("SELECT i.*, p.full_name as patient_name, p.phone, p.age, p.gender FROM invoices i JOIN patients p ON i.patient_id = p.patient_id WHERE i.invoice_id = ?");
    $stmt->execute([(int)$_GET['view']]);
    $selected_invoice = $stmt->fetch();
    
    $stmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
    $stmt->execute([(int)$_GET['view']]);
    $selected_invoice['items'] = $stmt->fetchAll();
}
?>

<?php include '../includes/header.php'; ?>

<?php if ($selected_invoice): ?>
<div class="box" style="margin-top: 70px;" id="invoice-print">
    <div class="box-header">
        <h2 class="box-title"><i class="fas fa-file-invoice-dollar"></i> Invoice <?php echo htmlspecialchars($selected_invoice['invoice_number']); ?></h2>
        <div>
            <button onclick="window.print()" class="btn btn-sm btn-primary no-print"><i class="fas fa-print"></i> Print</button>
            <a href="billing.php" class="btn btn-sm btn-secondary no-print">Back</a>
        </div>
    </div>
    
    <div style="display:flex;justify-content:space-between;margin-bottom:20px;">
        <div>
            <h3 style="color:#667eea;">MediQueue Hospital</h3>
            <p style="color:#666;">Hospital Road, City - 000000</p>
            <p style="color:#666;">Phone: 1800-XXX-XXXX</p>
        </div>
        <div style="text-align:right;">
            <h3>Invoice</h3>
            <p><strong><?php echo htmlspecialchars($selected_invoice['invoice_number']); ?></strong></p>
            <p>Date: <?php echo date('d M Y', strtotime($selected_invoice['created_at'])); ?></p>
            <p>Status: <span class="badge badge-<?php echo ['draft'=>'secondary','pending'=>'warning','paid'=>'success','partially_paid'=>'info','refunded'=>'danger'][$selected_invoice['status']] ?? 'secondary'; ?>"><?php echo ucfirst(str_replace('_',' ',$selected_invoice['status'])); ?></span></p>
        </div>
    </div>
    
    <div style="background:#f8f9fa;padding:15px;border-radius:8px;margin-bottom:20px;">
        <h4>Bill To:</h4>
        <p><strong><?php echo htmlspecialchars($selected_invoice['patient_name']); ?></strong> | Age: <?php echo $selected_invoice['age']; ?> | Gender: <?php echo $selected_invoice['gender']; ?></p>
        <p>Phone: <?php echo htmlspecialchars($selected_invoice['phone']); ?></p>
    </div>
    
    <table>
        <thead>
            <tr><th>#</th><th>Item</th><th>Type</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr>
        </thead>
        <tbody>
            <?php $i=1; foreach ($selected_invoice['items'] as $item): ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                <td><?php echo ucfirst($item['item_type']); ?></td>
                <td><?php echo $item['quantity']; ?></td>
                <td>₹<?php echo number_format($item['unit_price'], 2); ?></td>
                <td>₹<?php echo number_format($item['total'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div style="text-align:right;margin-top:20px;">
        <p>Subtotal: <strong>₹<?php echo number_format($selected_invoice['total_amount'], 2); ?></strong></p>
        <?php if ($selected_invoice['discount']): ?><p>Discount: -₹<?php echo number_format($selected_invoice['discount'], 2); ?></p><?php endif; ?>
        <?php if ($selected_invoice['tax']): ?><p>Tax: +₹<?php echo number_format($selected_invoice['tax'], 2); ?></p><?php endif; ?>
        <p style="font-size:18px;">Grand Total: <strong style="color:#667eea;">₹<?php echo number_format($selected_invoice['final_amount'], 2); ?></strong></p>
        <?php if ($selected_invoice['paid_amount']): ?><p>Paid: <strong style="color:#48bb78;">₹<?php echo number_format($selected_invoice['paid_amount'], 2); ?></strong></p><?php endif; ?>
    </div>
    
    <?php if ($selected_invoice['status'] != 'paid' && $selected_invoice['status'] != 'refunded'): ?>
    <div class="no-print" style="margin-top:20px;background:#f8f9fa;padding:15px;border-radius:8px;">
        <h4>Record Payment</h4>
        <form method="POST" style="display:flex;gap:0.5rem;align-items:flex-end;">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="pay">
            <input type="hidden" name="invoice_id" value="<?php echo $selected_invoice['invoice_id']; ?>">
            <div>
                <label>Amount (₹)</label>
                <input type="number" name="paid_amount" class="form-control" step="0.01" value="<?php echo $selected_invoice['final_amount'] - $selected_invoice['paid_amount']; ?>" required>
            </div>
            <div>
                <label>Method</label>
                <select name="payment_method" class="form-control">
                    <option value="cash">Cash</option>
                    <option value="card">Card</option>
                    <option value="upi">UPI</option>
                    <option value="insurance">Insurance</option>
                </select>
            </div>
            <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Pay</button>
        </form>
    </div>
    <?php endif; ?>
</div>

<style>.badge{padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;}.badge-success{background:#d1fae5;color:#065f46;}.badge-warning{background:#fef3c7;color:#92400e;}.badge-danger{background:#fee2e2;color:#991b1b;}.badge-info{background:#dbeafe;color:#1e40af;}.badge-secondary{background:#e5e7eb;color:#374151;}</style>

<?php else: ?>

<div class="box" style="max-width: 800px;">
    <div class="box-header">
        <h2 class="box-title"><i class="fas fa-plus-circle"></i> Create Invoice</h2>
    </div>
    
    <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="create_invoice">
        
        <div class="grid" style="grid-template-columns: 1fr 1fr;">
            <div class="form-group">
                <label class="form-label">Patient *</label>
                <select name="patient_id" class="form-control" required>
                    <option value="">-- Select --</option>
                    <?php foreach ($patients as $p): ?>
                        <option value="<?php echo $p['patient_id']; ?>"><?php echo htmlspecialchars($p['full_name']); ?> (<?php echo $p['age']; ?> yrs)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Appointment ID</label>
                <input type="number" name="appointment_id" class="form-control" placeholder="Optional">
            </div>
        </div>
        
        <h4 style="margin-bottom:0.5rem;">Items</h4>
        <div id="invoice-items">
            <div class="invoice-item" style="display:grid;grid-template-columns:3fr 1fr 1fr 1fr 1fr;gap:0.5rem;margin-bottom:0.5rem;">
                <div><label class="form-label">Item</label>
                    <select name="item_name[]" class="form-control" onchange="fillPrice(this)">
                        <option value="">-- Select --</option>
                        <?php foreach ($billing_items as $bi): ?>
                            <option value="<?php echo htmlspecialchars($bi['item_name']); ?>" data-price="<?php echo $bi['price']; ?>" data-type="<?php echo $bi['item_type']; ?>"><?php echo htmlspecialchars($bi['item_name']); ?> (₹<?php echo $bi['price']; ?>)</option>
                        <?php endforeach; ?>
                        <option value="custom">Custom Item</option>
                    </select>
                </div>
                <div><label class="form-label">Type</label><input type="text" name="item_type[]" class="form-control" value="other"></div>
                <div><label class="form-label">Qty</label><input type="number" name="item_qty[]" class="form-control" value="1" min="1"></div>
                <div><label class="form-label">Price (₹)</label><input type="number" name="item_price[]" class="form-control item-price" step="0.01"></div>
                <div style="display:flex;align-items:flex-end;"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.invoice-item').remove()"><i class="fas fa-times"></i></button></div>
            </div>
        </div>
        
        <button type="button" class="btn btn-secondary" onclick="addItem()" style="margin-bottom:1rem;"><i class="fas fa-plus"></i> Add Item</button>
        
        <div class="grid" style="grid-template-columns: 1fr 1fr;">
            <div class="form-group">
                <label class="form-label">Discount (₹)</label>
                <input type="number" name="discount" class="form-control" step="0.01" value="0">
            </div>
            <div class="form-group">
                <label class="form-label">Tax Rate (%)</label>
                <input type="number" name="tax_rate" class="form-control" step="0.01" value="0">
            </div>
        </div>
        
        <button type="submit" class="btn btn-primary"><i class="fas fa-file-invoice-dollar"></i> Create Invoice</button>
    </form>
</div>

<div class="box" style="margin-top: 1.5rem;">
    <div class="box-header">
        <h2 class="box-title"><i class="fas fa-list"></i> Recent Invoices</h2>
    </div>
    
    <div style="margin-bottom:1rem;display:flex;gap:0.5rem;">
        <?php foreach (['all','draft','pending','paid','partially_paid'] as $f): ?>
        <a href="?filter=<?php echo $f; ?>" class="btn <?php echo $filter==$f?'btn-primary':'btn-secondary'; ?>" style="font-size:13px;padding:5px 12px;"><?php echo ucfirst(str_replace('_',' ',$f)); ?></a>
        <?php endforeach; ?>
    </div>
    
    <div class="table-container">
        <table>
            <thead>
                <tr><th>Invoice #</th><th>Patient</th><th>Amount</th><th>Status</th><th>Date</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $inv): 
                    $colors = ['draft'=>'secondary','pending'=>'warning','paid'=>'success','partially_paid'=>'info','refunded'=>'danger'];
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($inv['invoice_number']); ?></strong></td>
                    <td><?php echo htmlspecialchars($inv['patient_name']); ?></td>
                    <td>₹<?php echo number_format($inv['final_amount'], 2); ?></td>
                    <td><span class="badge badge-<?php echo $colors[$inv['status']] ?? 'secondary'; ?>"><?php echo ucfirst(str_replace('_',' ',$inv['status'])); ?></span></td>
                    <td><?php echo date('d M Y', strtotime($inv['created_at'])); ?></td>
                    <td><a href="?view=<?php echo $inv['invoice_id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> View</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($invoices)): ?>
                <tr><td colspan="6" style="text-align:center;">No invoices found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function addItem() {
    const html = document.querySelector('.invoice-item').outerHTML;
    document.getElementById('invoice-items').insertAdjacentHTML('beforeend', html);
}
function fillPrice(sel) {
    const opt = sel.options[sel.selectedIndex];
    const row = sel.closest('.invoice-item');
    if (opt.dataset.price) {
        row.querySelector('.item-price').value = opt.dataset.price;
        row.querySelector('[name="item_type[]"]').value = opt.dataset.type || 'other';
    }
}
</script>

<style>.badge{padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;}.badge-success{background:#d1fae5;color:#065f46;}.badge-warning{background:#fef3c7;color:#92400e;}.badge-danger{background:#fee2e2;color:#991b1b;}.badge-info{background:#dbeafe;color:#1e40af;}.badge-secondary{background:#e5e7eb;color:#374151;}</style>

<?php endif; include '../includes/footer.php'; ?>
