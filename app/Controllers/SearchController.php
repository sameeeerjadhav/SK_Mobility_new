<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;

class SearchController extends Controller
{
    public function index(): void
    {
        require_auth();
        $q = trim($this->input('q'));
        if (strlen($q) < 2) {
            $this->json(['results' => []]);
        }
        $like = '%' . $q . '%';
        $db = $this->db();
        $results = [];

        $orders = $db->prepare(
            'SELECT id, order_number AS title, status AS meta FROM orders WHERE order_number LIKE ? OR customer_name LIKE ? LIMIT 8'
        );
        $orders->execute([$like, $like]);
        foreach ($orders->fetchAll() as $r) {
            $results[] = ['type' => 'Order', 'title' => $r['title'], 'meta' => $r['meta'], 'url' => url('orders/' . $r['id'])];
        }

        if (can('manage_dealers') || Auth::role() === 'super_admin') {
            $dealers = $db->prepare(
                'SELECT id, business_name AS title, dealer_code AS meta FROM dealers WHERE business_name LIKE ? OR dealer_code LIKE ? OR email LIKE ? LIMIT 8'
            );
            $dealers->execute([$like, $like, $like]);
            foreach ($dealers->fetchAll() as $r) {
                $results[] = ['type' => 'Dealer', 'title' => $r['title'], 'meta' => $r['meta'], 'url' => url('dealers/' . $r['id'])];
            }
        }

        $vehicles = $db->prepare(
            'SELECT id, name AS title, brand AS meta FROM vehicles WHERE is_active=1 AND (name LIKE ? OR brand LIKE ?) LIMIT 8'
        );
        $vehicles->execute([$like, $like]);
        foreach ($vehicles->fetchAll() as $r) {
            $results[] = ['type' => 'Vehicle', 'title' => $r['title'], 'meta' => $r['meta'], 'url' => url('vehicles/' . $r['id'])];
        }

        if (can('view_leads')) {
            $leads = $db->prepare(
                'SELECT id, customer_name AS title, customer_phone AS meta FROM leads WHERE customer_name LIKE ? OR customer_phone LIKE ? LIMIT 8'
            );
            $leads->execute([$like, $like]);
            foreach ($leads->fetchAll() as $r) {
                $results[] = ['type' => 'Lead', 'title' => $r['title'], 'meta' => $r['meta'], 'url' => url('leads')];
            }
        }

        $this->json(['results' => $results]);
    }
}
