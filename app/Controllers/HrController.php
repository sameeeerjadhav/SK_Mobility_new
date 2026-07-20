<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;

class HrController extends Controller
{
    public function index(): void
    {
        require_role('super_admin');
        $tab = $this->input('tab') ?: 'employees';

        $stats = [
            'employees' => (int)$this->db()->query("SELECT COUNT(*) FROM employees WHERE status='active'")->fetchColumn(),
            'payroll' => (float)$this->db()->query(
                'SELECT COALESCE(SUM(net_salary),0) FROM salary_records WHERE month=MONTH(CURDATE()) AND year=YEAR(CURDATE())'
            )->fetchColumn(),
            'avg' => (float)$this->db()->query(
                "SELECT COALESCE(AVG(basic_salary),0) FROM employees WHERE status='active'"
            )->fetchColumn(),
        ];

        $empTotal = (int)$this->db()->query('SELECT COUNT(*) FROM employees')->fetchColumn();
        $salTotal = (int)$this->db()->query('SELECT COUNT(*) FROM salary_records')->fetchColumn();
        $empPager = paginate($empTotal, max(1, (int)($this->input('page') ?: 1)), 20);
        $salPager = paginate($salTotal, max(1, (int)($this->input('page') ?: 1)), 20);

        $employees = $this->db()->query(
            "SELECT * FROM employees ORDER BY created_at DESC
             LIMIT {$empPager['per_page']} OFFSET {$empPager['offset']}"
        )->fetchAll();
        $salaries = $this->db()->query(
            "SELECT sr.*, e.first_name, e.last_name, e.employee_code
             FROM salary_records sr JOIN employees e ON e.id = sr.employee_id
             ORDER BY sr.created_at DESC
             LIMIT {$salPager['per_page']} OFFSET {$salPager['offset']}"
        )->fetchAll();

        $this->view('hr/index', [
            'title' => 'HR Management',
            'stats' => $stats,
            'employees' => $employees,
            'salaries' => $salaries,
            'tab' => $tab,
            'pagination' => $tab === 'payroll' ? $salPager : $empPager,
            'filters' => ['tab' => $tab],
            'employeeOptions' => $this->db()->query(
                "SELECT id, employee_code, first_name, last_name FROM employees WHERE status='active' ORDER BY first_name"
            )->fetchAll(),
        ]);
    }

    public function storeEmployee(): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $code = 'EMP-' . str_pad((string)((int)$this->db()->query('SELECT COUNT(*) FROM employees')->fetchColumn() + 1), 4, '0', STR_PAD_LEFT);
        $this->db()->prepare(
            'INSERT INTO employees (employee_code, first_name, last_name, email, phone, department, designation, date_of_joining, basic_salary, status)
             VALUES (?,?,?,?,?,?,?,?,?,\'active\')'
        )->execute([
            $code,
            $this->input('first_name'),
            $this->input('last_name'),
            $this->input('email'),
            trim((string)$this->input('phone')) !== '' ? format_phone($this->input('phone')) : null,
            $this->input('department'),
            $this->input('designation'),
            $this->input('date_of_joining') ?: null,
            (float)$this->input('basic_salary'),
        ]);
        Audit::log('create', 'hr', 'employees', (int)$this->db()->lastInsertId());
        flash('success', "Employee {$code} created.");
        $this->redirect('/hr');
    }

    public function updateEmployee(string $id): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $empId = (int)$id;
        $this->db()->prepare(
            'UPDATE employees SET first_name=?, last_name=?, email=?, phone=?, department=?, designation=?, date_of_joining=?, basic_salary=?, status=? WHERE id=?'
        )->execute([
            $this->input('first_name'),
            $this->input('last_name'),
            $this->input('email'),
            trim((string)$this->input('phone')) !== '' ? format_phone($this->input('phone')) : null,
            $this->input('department'),
            $this->input('designation'),
            $this->input('date_of_joining') ?: null,
            (float)$this->input('basic_salary'),
            $this->input('status') ?: 'active',
            $empId,
        ]);
        Audit::log('update', 'hr', 'employees', $empId);
        flash('success', 'Employee updated.');
        $this->redirect('/hr');
    }

    public function deleteEmployee(string $id): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $this->db()->prepare('DELETE FROM employees WHERE id = ?')->execute([(int)$id]);
        Audit::log('delete', 'hr', 'employees', (int)$id);
        flash('success', 'Employee deleted.');
        $this->redirect('/hr');
    }

    public function storeSalary(): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $basic = (float)$this->input('basic_salary');
        $allow = (float)$this->input('allowances');
        $deduct = (float)$this->input('deductions');
        $net = $basic + $allow - $deduct;

        $this->db()->prepare(
            'INSERT INTO salary_records (employee_id, month, year, basic_salary, allowances, deductions, net_salary, payment_date, payment_mode, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            (int)$this->input('employee_id'),
            (int)$this->input('month'),
            (int)$this->input('year'),
            $basic, $allow, $deduct, $net,
            $this->input('payment_date') ?: null,
            $this->input('payment_mode') ?: null,
            $this->input('notes'),
        ]);
        Audit::log('create', 'hr', 'salary_records', (int)$this->db()->lastInsertId());
        flash('success', 'Salary recorded.');
        $this->redirect('/hr?tab=salaries');
    }

    public function deleteSalary(string $id): void
    {
        require_role('super_admin');
        $this->validateCsrf();
        $this->db()->prepare('DELETE FROM salary_records WHERE id = ?')->execute([(int)$id]);
        Audit::log('delete', 'hr', 'salary_records', (int)$id);
        flash('success', 'Salary record deleted.');
        $this->redirect('/hr?tab=salaries');
    }
}
