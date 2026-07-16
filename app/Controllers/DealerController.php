<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Upload;
use App\Services\NotificationService;

class DealerController extends Controller
{
    public function showRegister(): void
    {
        $this->view('dealers/register', ['title' => 'Dealer Registration'], 'guest');
    }

    public function register(): void
    {
        $this->validateCsrf();
        $data = [
            'business_name' => $this->input('business_name'),
            'contact_person' => $this->input('contact_person'),
            'email' => $this->input('email'),
            'phone' => $this->input('phone'),
            'gst_number' => $this->input('gst_number'),
            'pan_number' => $this->input('pan_number'),
            'address_line1' => $this->input('address_line1'),
            'address_line2' => $this->input('address_line2'),
            'city' => $this->input('city'),
            'state' => $this->input('state'),
            'pincode' => $this->input('pincode'),
        ];

        if ($data['business_name'] === '' || $data['email'] === '' || $data['phone'] === '') {
            flash('error', 'Business name, email and phone are required.');
            store_old($data);
            $this->redirect('/dealers/register');
        }

        $db = $this->db();
        $db->prepare(
            'INSERT INTO dealers (business_name, contact_person, email, phone, gst_number, pan_number, status)
             VALUES (?,?,?,?,?,?,\'pending\')'
        )->execute([
            $data['business_name'], $data['contact_person'], $data['email'],
            $data['phone'], $data['gst_number'], $data['pan_number'],
        ]);
        $dealerId = (int)$db->lastInsertId();

        if ($data['address_line1'] !== '') {
            $db->prepare(
                'INSERT INTO dealer_addresses (dealer_id, address_line1, address_line2, city, state, pincode, is_primary)
                 VALUES (?,?,?,?,?,?,1)'
            )->execute([
                $dealerId, $data['address_line1'], $data['address_line2'],
                $data['city'], $data['state'], $data['pincode'],
            ]);
        }

        NotificationService::notifyRole(
            'super_admin',
            'New Dealer Registration',
            $data['business_name'] . ' applied for dealer onboarding.',
            'dealer',
            'dealers',
            $dealerId
        );

        clear_old();
        flash('success', 'Registration submitted. Our team will review and approve your account.');
        $this->redirect('/login');
    }

    public function index(): void
    {
        require_permission('manage_dealers');
        $status = $this->input('status');
        $search = $this->input('search');
        $page = max(1, (int)($this->input('page') ?: 1));
        $perPage = 15;
        $offset = ($page - 1) * $perPage;

        $where = ['1=1'];
        $params = [];
        if ($status !== '') {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $where[] = '(business_name LIKE ? OR email LIKE ? OR dealer_code LIKE ? OR phone LIKE ?)';
            $q = '%' . $search . '%';
            array_push($params, $q, $q, $q, $q);
        }
        $sqlWhere = implode(' AND ', $where);

        $countStmt = $this->db()->prepare("SELECT COUNT(*) FROM dealers WHERE {$sqlWhere}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $this->db()->prepare(
            "SELECT * FROM dealers WHERE {$sqlWhere} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        $dealers = $stmt->fetchAll();

        $this->view('dealers/index', [
            'title' => 'Dealers',
            'dealers' => $dealers,
            'status' => $status,
            'search' => $search,
            'page' => $page,
            'totalPages' => max(1, (int)ceil($total / $perPage)),
        ]);
    }

    public function store(): void
    {
        require_permission('manage_dealers');
        $this->validateCsrf();

        $this->db()->prepare(
            'INSERT INTO dealers (business_name, contact_person, email, phone, gst_number, pan_number, status)
             VALUES (?,?,?,?,?,?,\'pending\')'
        )->execute([
            $this->input('business_name'),
            $this->input('contact_person'),
            $this->input('email'),
            $this->input('phone'),
            $this->input('gst_number'),
            $this->input('pan_number'),
        ]);
        $id = (int)$this->db()->lastInsertId();
        Audit::log('create', 'dealers', 'dealers', $id);
        flash('success', 'Dealer created.');
        $this->redirect('/dealers');
    }

    public function update(string $id): void
    {
        require_permission('manage_dealers');
        $this->validateCsrf();
        $dealerId = (int)$id;
        $this->db()->prepare(
            'UPDATE dealers SET business_name=?, contact_person=?, email=?, phone=?, gst_number=?, pan_number=? WHERE id=?'
        )->execute([
            $this->input('business_name'),
            $this->input('contact_person'),
            $this->input('email'),
            $this->input('phone'),
            $this->input('gst_number'),
            $this->input('pan_number'),
            $dealerId,
        ]);

        $addressLine1 = trim((string)$this->input('address_line1'));
        $addressLine2 = trim((string)$this->input('address_line2'));
        $city = trim((string)$this->input('city'));
        $state = trim((string)$this->input('state'));
        $pincode = trim((string)$this->input('pincode'));

        $addrStmt = $this->db()->prepare(
            'SELECT id FROM dealer_addresses WHERE dealer_id = ? AND is_primary = 1 LIMIT 1'
        );
        $addrStmt->execute([$dealerId]);
        $primaryAddressId = $addrStmt->fetchColumn();

        if ($addressLine1 !== '') {
            if ($primaryAddressId) {
                $this->db()->prepare(
                    'UPDATE dealer_addresses
                     SET address_line1=?, address_line2=?, city=?, state=?, pincode=?
                     WHERE id=?'
                )->execute([
                    $addressLine1,
                    $addressLine2 !== '' ? $addressLine2 : null,
                    $city,
                    $state,
                    $pincode,
                    (int)$primaryAddressId,
                ]);
            } else {
                $this->db()->prepare(
                    'INSERT INTO dealer_addresses (dealer_id, address_line1, address_line2, city, state, pincode, is_primary)
                     VALUES (?,?,?,?,?,?,1)'
                )->execute([
                    $dealerId,
                    $addressLine1,
                    $addressLine2 !== '' ? $addressLine2 : null,
                    $city,
                    $state,
                    $pincode,
                ]);
            }
        } elseif ($primaryAddressId) {
            $this->db()->prepare('DELETE FROM dealer_addresses WHERE id = ?')->execute([(int)$primaryAddressId]);
        }

        Audit::log('update', 'dealers', 'dealers', $dealerId);
        flash('success', 'Dealer updated.');
        $this->redirect('/dealers/' . $dealerId);
    }

    public function destroy(string $id): void
    {
        require_permission('manage_dealers');
        $this->validateCsrf();
        $dealerId = (int)$id;
        $this->db()->prepare("UPDATE dealers SET status = 'suspended' WHERE id = ?")->execute([$dealerId]);
        Audit::log('delete', 'dealers', 'dealers', $dealerId);
        flash('success', 'Dealer suspended.');
        $this->redirect('/dealers');
    }

    public function show(string $id): void
    {
        require_permission('manage_dealers');
        $dealerId = (int)$id;

        $stmt = $this->db()->prepare('SELECT * FROM dealers WHERE id = ?');
        $stmt->execute([$dealerId]);
        $dealer = $stmt->fetch();
        if (!$dealer) {
            flash('error', 'Dealer not found.');
            $this->redirect('/dealers');
        }

        $addr = $this->db()->prepare('SELECT * FROM dealer_addresses WHERE dealer_id = ?');
        $addr->execute([$dealerId]);
        $addresses = $addr->fetchAll();

        $docs = $this->db()->prepare('SELECT * FROM dealer_documents WHERE dealer_id = ? ORDER BY created_at DESC');
        $docs->execute([$dealerId]);
        $documents = $docs->fetchAll();

        $orders = $this->db()->prepare(
            'SELECT * FROM orders WHERE dealer_id = ? ORDER BY created_at DESC LIMIT 10'
        );
        $orders->execute([$dealerId]);
        $recentOrders = $orders->fetchAll();

        $breakdown = $this->db()->prepare(
            'SELECT status, COUNT(*) AS cnt FROM orders WHERE dealer_id = ? GROUP BY status'
        );
        $breakdown->execute([$dealerId]);
        $statusBreakdown = $breakdown->fetchAll();

        $leadsCnt = $this->db()->prepare('SELECT COUNT(*) FROM leads WHERE dealer_id = ?');
        $leadsCnt->execute([$dealerId]);
        $totalLeads = (int)$leadsCnt->fetchColumn();

        $linkedUser = null;
        if (!empty($dealer['user_id'])) {
            $uStmt = $this->db()->prepare(
                'SELECT id, email, first_name, last_name, phone, is_active, last_login_at, created_at
                 FROM users WHERE id = ? LIMIT 1'
            );
            $uStmt->execute([(int)$dealer['user_id']]);
            $linkedUser = $uStmt->fetch() ?: null;
        }

        $this->view('dealers/show', [
            'title' => $dealer['business_name'],
            'dealer' => $dealer,
            'addresses' => $addresses,
            'documents' => $documents,
            'recentOrders' => $recentOrders,
            'statusBreakdown' => $statusBreakdown,
            'totalLeads' => $totalLeads,
            'linkedUser' => $linkedUser,
        ]);
    }

    public function approve(string $id): void
    {
        require_permission('manage_dealers');
        $this->validateCsrf();
        $dealerId = (int)$id;
        $status = $this->input('status');

        if (!in_array($status, ['approved', 'rejected', 'suspended'], true)) {
            flash('error', 'Invalid status.');
            $this->redirect('/dealers/' . $dealerId);
        }

        $stmt = $this->db()->prepare('SELECT * FROM dealers WHERE id = ?');
        $stmt->execute([$dealerId]);
        $dealer = $stmt->fetch();
        if (!$dealer) {
            flash('error', 'Dealer not found.');
            $this->redirect('/dealers');
        }

        $code = $dealer['dealer_code'];
        $userId = $dealer['user_id'];

        if ($status === 'approved' && !$userId) {
            $roleStmt = $this->db()->query("SELECT id FROM roles WHERE slug = 'dealer' LIMIT 1");
            $roleId = (int)$roleStmt->fetchColumn();
            $tempPass = 'Dealer@' . random_int(1000, 9999);
            $this->db()->prepare(
                'INSERT INTO users (role_id, email, password_hash, first_name, last_name, phone, is_active, is_verified)
                 VALUES (?,?,?,?,?,?,1,1)'
            )->execute([
                $roleId,
                $dealer['email'],
                password_hash($tempPass, PASSWORD_BCRYPT),
                $dealer['contact_person'],
                'Dealer',
                $dealer['phone'],
            ]);
            $userId = (int)$this->db()->lastInsertId();

            if (!$code) {
                $seq = (int)$this->db()->query('SELECT COUNT(*) FROM dealers WHERE dealer_code IS NOT NULL')->fetchColumn() + 1;
                $code = 'SKD-' . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);
            }

            flash('success', "Dealer approved. Login: {$dealer['email']} / Temp password: {$tempPass}");
        } else {
            flash('success', 'Dealer status updated to ' . $status . '.');
        }

        $this->db()->prepare(
            'UPDATE dealers SET status = ?, user_id = COALESCE(?, user_id), dealer_code = COALESCE(?, dealer_code) WHERE id = ?'
        )->execute([$status, $userId, $code, $dealerId]);

        Audit::log('update', 'dealers', 'dealers', $dealerId, ['status' => $dealer['status']], ['status' => $status]);
        $this->redirect('/dealers/' . $dealerId);
    }

    public function uploadDocument(string $id): void
    {
        require_permission('manage_dealers');
        $this->validateCsrf();
        $dealerId = (int)$id;
        $type = $this->input('document_type') ?: 'other';
        $path = Upload::store($_FILES['document'] ?? [], 'dealer-documents');
        if (!$path) {
            flash('error', 'Upload failed. Allowed: images or PDF.');
            $this->redirect('/dealers/' . $dealerId);
        }
        $this->db()->prepare(
            'INSERT INTO dealer_documents (dealer_id, document_type, file_url) VALUES (?,?,?)'
        )->execute([$dealerId, $type, $path]);
        Audit::log('create', 'dealers', 'dealer_documents', (int)$this->db()->lastInsertId());
        flash('success', 'Document uploaded.');
        $this->redirect('/dealers/' . $dealerId);
    }

    /**
     * Admin: set / reset password for the dealer login user.
     * Existing passwords are hashed and cannot be displayed — a new one is set and shown once.
     */
    public function resetPassword(string $id): void
    {
        require_permission('manage_dealers');
        $this->validateCsrf();
        $dealerId = (int)$id;

        $stmt = $this->db()->prepare('SELECT * FROM dealers WHERE id = ?');
        $stmt->execute([$dealerId]);
        $dealer = $stmt->fetch();
        if (!$dealer) {
            flash('error', 'Dealer not found.');
            $this->redirect('/dealers');
        }

        $password = $this->input('password');
        $generate = isset($_POST['generate']) && $_POST['generate'] === '1';

        if ($generate || $password === '') {
            $password = 'Dealer@' . random_int(1000, 9999) . chr(random_int(65, 90));
        }

        if (strlen($password) < 6) {
            flash('error', 'Password must be at least 6 characters.');
            $this->redirect('/dealers/' . $dealerId);
        }

        $userId = (int)($dealer['user_id'] ?? 0);

        // Create linked user if dealer was approved without an account (edge case)
        if ($userId <= 0) {
            if ($dealer['status'] !== 'approved') {
                flash('error', 'Approve the dealer first to create a login account.');
                $this->redirect('/dealers/' . $dealerId);
            }
            $roleId = (int)$this->db()->query("SELECT id FROM roles WHERE slug = 'dealer' LIMIT 1")->fetchColumn();
            $this->db()->prepare(
                'INSERT INTO users (role_id, email, password_hash, first_name, last_name, phone, is_active, is_verified)
                 VALUES (?,?,?,?,?,?,1,1)'
            )->execute([
                $roleId,
                $dealer['email'],
                password_hash($password, PASSWORD_BCRYPT),
                $dealer['contact_person'],
                'Dealer',
                $dealer['phone'],
            ]);
            $userId = (int)$this->db()->lastInsertId();
            $this->db()->prepare('UPDATE dealers SET user_id = ? WHERE id = ?')->execute([$userId, $dealerId]);
        } else {
            $this->db()->prepare('UPDATE users SET password_hash = ?, is_active = 1 WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_BCRYPT), $userId]);
        }

        // Keep login email in sync with dealer email
        $this->db()->prepare('UPDATE users SET email = ?, phone = ? WHERE id = ?')
            ->execute([$dealer['email'], $dealer['phone'], $userId]);

        Audit::log('update', 'dealers', 'users', $userId, null, ['password' => 'reset_by_admin']);
        flash('success', "Password updated. Login: {$dealer['email']} / New password: {$password}");
        $this->redirect('/dealers/' . $dealerId);
    }

    public function toggleUser(string $id): void
    {
        require_permission('manage_dealers');
        $this->validateCsrf();
        $dealerId = (int)$id;
        $stmt = $this->db()->prepare('SELECT user_id, email FROM dealers WHERE id = ?');
        $stmt->execute([$dealerId]);
        $dealer = $stmt->fetch();
        if (!$dealer || empty($dealer['user_id'])) {
            flash('error', 'No login account linked to this dealer.');
            $this->redirect('/dealers/' . $dealerId);
        }
        $this->db()->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = ?')
            ->execute([(int)$dealer['user_id']]);
        Audit::log('update', 'dealers', 'users', (int)$dealer['user_id']);
        flash('success', 'Dealer login access toggled.');
        $this->redirect('/dealers/' . $dealerId);
    }
}
