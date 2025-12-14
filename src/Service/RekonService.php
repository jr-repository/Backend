<?php
namespace App\Service;
use App\Core\Database;

use App\Repository\RekonRepository;
use App\Repository\UserRepository;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class RekonService
{
    private $repo;
    private $userRepo;

    public function __construct()
    {
        $this->repo = new RekonRepository();
        $this->userRepo = new UserRepository();
    }

    public function getList($type, $keyword, $status, $from, $to)
    {
        if ($type === 'CIMB') {
            $data = $this->repo->findAllCimb($keyword, $status, $from, $to);
            foreach ($data as &$row) {
                $fileList = [];
                if (!empty($row['files'])) {
                    foreach (explode(',', $row['files']) as $path) {
                        $fileList[] = "https://kasbon2.multimitralogistik.id/" . $path;
                    }
                }
                $row['file_list'] = $fileList;
                $row['creator_name'] = $row['creator_name'] ?? 'System';
                $row['updater_name'] = $row['updater_name'] ?? '-';
                $row['line_no'] = intval($row['line_no']);
                $row['debit'] = floatval($row['debit']);
                $row['credit'] = floatval($row['credit']);
                $row['balance'] = floatval($row['balance']);
            }
            return $data;
        } else {
            $data = $this->repo->findAllMandiri($keyword, $status, $from, $to);
            foreach ($data as &$row) {
                $fileList = [];
                if (!empty($row['files'])) {
                    foreach (explode(',', $row['files']) as $path) {
                        $fileList[] = "https://kasbon2.multimitralogistik.id/" . $path;
                    }
                }
                $row['files'] = $fileList;
                $row['creator_name'] = $row['creator_name'] ?? 'System';
                $row['updater_name'] = $row['updater_name_display'] ?? '-';
                $row['debit'] = floatval($row['debit']);
                $row['credit'] = floatval($row['credit']);
            }
            return $data;
        }
    }

    public function importMandiri($file, $userId)
    {
        $spreadsheet = IOFactory::load($file['tmp_name']);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();
        $success = 0; $fail = 0; $errors = [];

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty($row[0]) && empty($row[3])) continue;

            $trxCode = $row[3] ?? '';
            if (empty($trxCode)) { $fail++; continue; }
            if ($this->repo->checkDuplicate('bank_reconciliations', $trxCode)) {
                $fail++; $errors[] = "Trx Code $trxCode duplicate";
                continue;
            }

            $date = is_numeric($row[1]) ? Date::excelToDateTimeObject($row[1])->format('Y-m-d') : $row[1];
            $valDate = is_numeric($row[2]) ? Date::excelToDateTimeObject($row[2])->format('Y-m-d') : $row[2];

            $data = [
                'account_no' => $row[0] ?? '',
                'date' => $date,
                'val_date' => $valDate,
                'transaction_code' => $trxCode,
                'description1' => $row[4] ?? '',
                'description2' => $row[5] ?? '',
                'reference_no' => $row[6] ?? '',
                'debit' => floatval($row[7] ?? 0),
                'credit' => floatval($row[8] ?? 0),
                'created_by' => $userId
            ];

            if ($this->repo->createMandiri($data)) $success++; else { $fail++; $errors[] = "Failed insert $trxCode"; }
        }
        return ['success' => true, 'message' => "Sukses: $success, Gagal: $fail", 'errors' => $errors];
    }

    public function importCimb($file, $userId)
    {
        $spreadsheet = IOFactory::load($file['tmp_name']);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();
        $success = 0; $fail = 0; $errors = [];
        $startRow = 8;

        for ($i = $startRow; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty($row[11])) continue;

            $trxCode = $row[11] ?? '';
            if (empty($trxCode)) { $fail++; continue; }
            if ($this->repo->checkDuplicate('bank_reconciliations_cimb', $trxCode)) {
                $fail++; $errors[] = "Trx Code $trxCode duplicate";
                continue;
            }

            $postDate = $row[1] ?? date('Y-m-d H:i:s');
            if (is_numeric($postDate)) $postDate = Date::excelToDateTimeObject($postDate)->format('Y-m-d H:i:s');
            else $postDate = date('Y-m-d H:i:s', strtotime(str_replace('/', '-', $postDate)));

            $effDate = $row[4] ?? $postDate;
            if (is_numeric($effDate)) $effDate = Date::excelToDateTimeObject($effDate)->format('Y-m-d H:i:s');
            else $effDate = date('Y-m-d H:i:s', strtotime(str_replace('/', '-', $effDate)));

            $data = [
                'line_no' => intval($row[0] ?? 0),
                'post_date' => $postDate,
                'eff_date' => $effDate,
                'cheque_no' => $row[5] ?? '',
                'description' => $row[6] ?? '',
                'debit' => floatval(str_replace(',', '', $row[7] ?? 0)),
                'credit' => floatval(str_replace(',', '', $row[8] ?? 0)),
                'balance' => floatval(str_replace(',', '', $row[10] ?? 0)),
                'transaction_code' => $trxCode,
                'reference_no' => $row[12] ?? '',
                'payment_type' => $row[13] ?? '',
                'bank_reference' => $row[15] ?? '',
                'created_by' => $userId
            ];

            if ($this->repo->createCimb($data)) $success++; else { $fail++; $errors[] = "Failed insert $trxCode"; }
        }
        return ['success' => true, 'message' => "Sukses: $success, Gagal: $fail", 'errors' => $errors];
    }

    public function createManual($data, $type)
    {
        $table = ($type === 'CIMB') ? 'bank_reconciliations_cimb' : 'bank_reconciliations';
        if ($this->repo->checkDuplicate($table, $data['transaction_code'])) {
            return ['success' => false, 'message' => 'Transaction Code already exists'];
        }

        if ($type === 'CIMB') {
            $data['line_no'] = $data['line_no'] ?? 0;
            $data['eff_date'] = $data['eff_date'] ?? $data['post_date'];
            return $this->repo->createCimb($data) ? ['success' => true] : ['success' => false];
        } else {
            return $this->repo->createMandiri($data) ? ['success' => true] : ['success' => false];
        }
    }

    public function processAction($id, $action, $note, $userId, $files, $type = 'MANDIRI')
    {
        $perms = $this->userRepo->getUserApprovals($userId);
        if (!in_array('REKON', $perms)) {
            return ['success' => false, 'message' => 'AKSES DITOLAK: Tidak ada izin Approval Rekon.'];
        }

        $table = ($type === 'CIMB') ? 'bank_reconciliations_cimb' : 'bank_reconciliations';
        $fileTable = ($type === 'CIMB') ? 'bank_reconciliation_files_cimb' : 'bank_reconciliation_files';
        
        // CIMB uses integer ID for updated_by in existing structure (based on ActionCimb.php)
        // Mandiri uses string name for updated_by (based on Action.php). 
        // We will unify logic in Repo but pass correct param.
        // Actually, ListCimb.php joins updated_by with users table, so it must be INT.
        // List.php uses COALESCE(r.updated_by), likely it was string 'System'.
        // Refactor: We enforce integer userId for both if schema allows, but for safety 
        // we follow the pattern: Mandiri used string in legacy? 
        // Legacy Action.php: `updated_by = ?, ... bind_param("sssi", ... $user)` -> $user is string name.
        // Legacy ActionCimb.php: `updated_by = ?, ... bind_param("ssii", ... $userId)` -> $userId is int.
        // To maintain 100% compatibility with DB schema we must follow this divergence.
        
        $userFieldVal = ($type === 'CIMB') ? $userId : 'System'; // Or fetch name for Mandiri
        if ($type !== 'CIMB') {
            $db = Database::getInstance()->getConnection();
            $u = $db->query("SELECT name FROM users WHERE id = $userId")->fetch_assoc();
            $userFieldVal = $u ? $u['name'] : 'System';
        }

        if ($this->repo->updateStatus($table, $id, $action, $note, $userFieldVal)) {
            if (!empty($files['name'][0])) {
                $targetDir = __DIR__ . '/../../uploads/rekon/';
                if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);
                
                $count = count($files['name']);
                for ($i = 0; $i < $count; $i++) {
                    $fileName = basename($files['name'][$i]);
                    $tmpName = $files['tmp_name'][$i];
                    $fileType = $files['type'][$i];
                    $newFileName = time() . '_' . rand(100, 999) . '_' . $fileName;
                    
                    if (move_uploaded_file($tmpName, $targetDir . $newFileName)) {
                        $this->repo->addFile($fileTable, $id, $fileName, 'uploads/rekon/' . $newFileName, $fileType);
                    }
                }
            }
            return ['success' => true, 'message' => 'Status berhasil diperbarui'];
        }
        return ['success' => false, 'message' => 'Database Error'];
    }

    public function deleteTransaction($id, $type)
    {
        $table = ($type === 'CIMB') ? 'bank_reconciliations_cimb' : 'bank_reconciliations';
        $check = $this->repo->getById($table, $id);
        if ($check && $check['status'] === 'Approved') {
            return ['success' => false, 'message' => 'Data Approved tidak bisa dihapus.'];
        }
        return $this->repo->delete($table, $id) ? ['success' => true] : ['success' => false];
    }

    public function updateTransaction($id, $data)
    {
        $check = $this->repo->getById('bank_reconciliations', $id);
        if ($check['status'] === 'Approved') return ['success' => false, 'message' => 'Data Approved tidak bisa diedit'];
        return $this->repo->updateMandiri($id, $data) ? ['success' => true] : ['success' => false];
    }

    public function getDashboardStats($type)
    {
        $table = ($type === 'CIMB') ? 'bank_reconciliations_cimb' : 'bank_reconciliations';
        return [
            'totals' => $this->repo->getSummary($table),
            'status_counts' => $this->repo->getStatusCounts($table),
            'recent' => $this->repo->getRecent($table)
        ];
    }
}