<?php

namespace App\Services\Report;

use App\Services\BaseService;
use PDO;

class ReportService extends BaseService
{
    public function getDashboardMetrics(int $organizationId): array
    {
        if (!$this->pdo) {
            return [
                'total_athletes' => 0,
                'total_coaches' => 0,
                'total_teams' => 0,
                'upcoming_tournaments' => 0,
                'upcoming_matches' => 0,
                'todays_training' => 0,
                'venue_bookings' => 0,
                'pending_leave' => 0,
                'low_inventory' => 0,
            ];
        }

        // 1. Total Athletes
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM athletes WHERE organization_id = :org AND deleted_at IS NULL");
        $stmt->execute([':org' => $organizationId]);
        $totalAthletes = (int)$stmt->fetchColumn();

        // 2. Total Coaches
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT cp.id) 
            FROM coach_profiles cp 
            JOIN employees e ON cp.employee_id = e.id 
            WHERE cp.organization_id = :org AND cp.deleted_at IS NULL AND e.deleted_at IS NULL
        ");
        $stmt->execute([':org' => $organizationId]);
        $totalCoaches = (int)$stmt->fetchColumn();

        // 3. Total Teams
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM teams WHERE organization_id = :org AND deleted_at IS NULL");
        $stmt->execute([':org' => $organizationId]);
        $totalTeams = (int)$stmt->fetchColumn();

        // 4. Upcoming Tournaments
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM tournaments 
            WHERE organization_id = :org AND end_date >= CURDATE() AND status != 'cancelled' AND deleted_at IS NULL
        ");
        $stmt->execute([':org' => $organizationId]);
        $upcomingTournaments = (int)$stmt->fetchColumn();

        // 5. Upcoming Matches / Fixtures
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM fixtures 
            WHERE organization_id = :org AND scheduled_date >= CURDATE() AND status != 'completed' AND status != 'cancelled' AND deleted_at IS NULL
        ");
        $stmt->execute([':org' => $organizationId]);
        $upcomingMatches = (int)$stmt->fetchColumn();

        // 6. Today's Training Sessions
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM training_sessions 
            WHERE organization_id = :org AND training_date = CURDATE() AND status != 'cancelled' AND deleted_at IS NULL
        ");
        $stmt->execute([':org' => $organizationId]);
        $todaysTraining = (int)$stmt->fetchColumn();

        // 7. Venue Bookings (Today & Upcoming)
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM venue_bookings 
            WHERE organization_id = :org AND booking_date >= CURDATE() AND status != 'cancelled' AND deleted_at IS NULL
        ");
        $stmt->execute([':org' => $organizationId]);
        $venueBookings = (int)$stmt->fetchColumn();

        // 8. Pending Leave Requests
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM leave_requests 
            WHERE organization_id = :org AND status = 'pending'
        ");
        $stmt->execute([':org' => $organizationId]);
        $pendingLeave = (int)$stmt->fetchColumn();

        // 9. Low Inventory Alerts
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM inventory_items 
            WHERE organization_id = :org AND quantity <= minimum_stock_level AND deleted_at IS NULL
        ");
        $stmt->execute([':org' => $organizationId]);
        $lowInventory = (int)$stmt->fetchColumn();

        return [
            'total_athletes' => $totalAthletes,
            'total_coaches' => $totalCoaches,
            'total_teams' => $totalTeams,
            'upcoming_tournaments' => $upcomingTournaments,
            'upcoming_matches' => $upcomingMatches,
            'todays_training' => $todaysTraining,
            'venue_bookings' => $venueBookings,
            'pending_leave' => $pendingLeave,
            'low_inventory' => $lowInventory,
        ];
    }

    public function getUpcomingFixtures(int $organizationId, int $limit = 5): array
    {
        if (!$this->pdo) return [];

        $stmt = $this->pdo->prepare("
            SELECT f.id, f.fixture_reference, f.round_name, f.scheduled_date, f.scheduled_start_time, f.status,
                   t.name as tournament_name,
                   ht.name as home_team_name, ht.team_code as home_team_code,
                   at.name as away_team_name, at.team_code as away_team_code,
                   v.name as venue_name,
                   vf.name as facility_name
            FROM fixtures f
            LEFT JOIN tournaments t ON f.tournament_id = t.id
            LEFT JOIN teams ht ON f.home_team_id = ht.id
            LEFT JOIN teams at ON f.away_team_id = at.id
            LEFT JOIN venues v ON f.venue_id = v.id
            LEFT JOIN venue_facilities vf ON f.facility_id = vf.id
            WHERE f.organization_id = :org AND f.deleted_at IS NULL
            ORDER BY f.scheduled_date ASC, f.scheduled_start_time ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':org', $organizationId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getTodaySessions(int $organizationId, int $limit = 5): array
    {
        if (!$this->pdo) return [];

        $stmt = $this->pdo->prepare("
            SELECT ts.id, ts.training_reference, ts.title, ts.training_type, ts.training_date, ts.start_time, ts.end_time, ts.status,
                   tm.name as team_name,
                   CONCAT(e.first_name, ' ', COALESCE(e.last_name, '')) as coach_name,
                   v.name as venue_name,
                   vf.name as facility_name,
                   s.name as sport_name
            FROM training_sessions ts
            LEFT JOIN teams tm ON ts.team_id = tm.id
            LEFT JOIN sports s ON tm.sport_id = s.id
            LEFT JOIN coach_profiles cp ON ts.coach_id = cp.id
            LEFT JOIN employees e ON cp.employee_id = e.id
            LEFT JOIN venues v ON ts.venue_id = v.id
            LEFT JOIN venue_facilities vf ON ts.facility_id = vf.id
            WHERE ts.organization_id = :org AND ts.deleted_at IS NULL
            ORDER BY ts.training_date DESC, ts.start_time ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':org', $organizationId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getOperationalReports(int $organizationId): array
    {
        if (!$this->pdo) return [];

        // 1. Athletes by sport
        $stmt = $this->pdo->prepare("
            SELECT s.name as sport_name, COUNT(a.id) as count 
            FROM sports s 
            LEFT JOIN athletes a ON a.current_sport_id = s.id AND a.organization_id = :org AND a.deleted_at IS NULL 
            GROUP BY s.id, s.name 
            ORDER BY count DESC
        ");
        $stmt->execute([':org' => $organizationId]);
        $athletesBySport = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // 2. Teams by sport
        $stmt = $this->pdo->prepare("
            SELECT s.name as sport_name, COUNT(t.id) as count 
            FROM sports s 
            LEFT JOIN teams t ON t.sport_id = s.id AND t.organization_id = :org AND t.deleted_at IS NULL 
            GROUP BY s.id, s.name 
            ORDER BY count DESC
        ");
        $stmt->execute([':org' => $organizationId]);
        $teamsBySport = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // 3. Training Attendance metrics
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_records,
                SUM(CASE WHEN attendance_status = 'present' THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN attendance_status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN attendance_status = 'excused' THEN 1 ELSE 0 END) as excused_count
            FROM training_attendance ta
            JOIN training_sessions ts ON ta.training_session_id = ts.id
            WHERE ts.organization_id = :org AND ts.deleted_at IS NULL
        ");
        $stmt->execute([':org' => $organizationId]);
        $attendanceStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_records' => 0, 'present_count' => 0, 'absent_count' => 0, 'excused_count' => 0];
        $totalAtt = (int)($attendanceStats['total_records'] ?? 0);
        $presentAtt = (int)($attendanceStats['present_count'] ?? 0);
        $attendanceRate = $totalAtt > 0 ? round(($presentAtt / $totalAtt) * 100, 1) : 0;
        $attendanceStats['attendance_rate'] = $attendanceRate;

        // 4. Tournament & Match Activity
        $stmt = $this->pdo->prepare("
            SELECT 
                (SELECT COUNT(*) FROM tournaments WHERE organization_id = :org AND deleted_at IS NULL) as total_tournaments,
                (SELECT COUNT(*) FROM fixtures WHERE organization_id = :org AND deleted_at IS NULL) as total_fixtures,
                (SELECT COUNT(*) FROM matches m JOIN fixtures f ON m.fixture_id = f.id WHERE f.organization_id = :org AND m.status = 'completed') as completed_matches,
                (SELECT COUNT(*) FROM matches m JOIN fixtures f ON m.fixture_id = f.id WHERE f.organization_id = :org AND m.status != 'completed') as pending_matches
        ");
        $stmt->execute([':org' => $organizationId]);
        $tournamentActivity = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // 5. Venue Utilization
        $stmt = $this->pdo->prepare("
            SELECT v.name as venue_name, COUNT(vb.id) as booking_count
            FROM venues v
            LEFT JOIN venue_bookings vb ON vb.venue_id = v.id AND vb.organization_id = :org AND vb.deleted_at IS NULL
            WHERE v.organization_id = :org AND v.deleted_at IS NULL
            GROUP BY v.id, v.name
            ORDER BY booking_count DESC
        ");
        $stmt->execute([':org' => $organizationId]);
        $venueUtilization = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // 6. Leave Summary
        $stmt = $this->pdo->prepare("
            SELECT 
                status, COUNT(*) as count
            FROM leave_requests
            WHERE organization_id = :org
            GROUP BY status
        ");
        $stmt->execute([':org' => $organizationId]);
        $leaveStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        // 7. Inventory Valuation & Summary
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_items,
                SUM(quantity) as total_units,
                SUM(quantity * unit_cost) as total_valuation,
                SUM(CASE WHEN quantity <= minimum_stock_level THEN 1 ELSE 0 END) as low_stock_count
            FROM inventory_items
            WHERE organization_id = :org AND deleted_at IS NULL
        ");
        $stmt->execute([':org' => $organizationId]);
        $inventoryStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // 8. Payroll Summary
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(DISTINCT employee_id) as total_employees,
                COALESCE(SUM(gross_salary), 0) as total_gross,
                COALESCE(SUM(net_salary), 0) as total_net
            FROM payroll
            WHERE organization_id = :org
        ");
        $stmt->execute([':org' => $organizationId]);
        $payrollStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'athletes_by_sport' => $athletesBySport,
            'teams_by_sport' => $teamsBySport,
            'attendance_stats' => $attendanceStats,
            'tournament_activity' => $tournamentActivity,
            'venue_utilization' => $venueUtilization,
            'leave_stats' => $leaveStats,
            'inventory_stats' => $inventoryStats,
            'payroll_stats' => $payrollStats,
        ];
    }

    // =========================================================================
    // MEMBER 5: 1. INVENTORY REPORTS
    // =========================================================================

    /**
     * Inventory summary report with category distribution and stock health.
     */
    public function getInventorySummary(int $organizationId, array $filters = []): array
    {
        if (!$this->pdo) return [];

        $where = ["ii.organization_id = :org", "ii.deleted_at IS NULL"];
        $params = [':org' => $organizationId];

        if (!empty($filters['category_id'])) {
            $where[] = "ii.category_id = :cat_id";
            $params[':cat_id'] = (int)$filters['category_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = "ii.status = :status";
            $params[':status'] = $filters['status'];
        }

        $whereClause = implode(" AND ", $where);

        // Overall stats
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_items,
                COALESCE(SUM(ii.quantity), 0) as total_units,
                COALESCE(SUM(ii.quantity * ii.unit_cost), 0) as total_valuation,
                COALESCE(SUM(CASE WHEN ii.quantity <= ii.minimum_stock_level THEN 1 ELSE 0 END), 0) as low_stock_count,
                COALESCE(SUM(CASE WHEN ii.quantity = 0 THEN 1 ELSE 0 END), 0) as out_of_stock_count
            FROM inventory_items ii
            WHERE {$whereClause}
        ");
        $stmt->execute($params);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Category breakdown
        $stmtCat = $this->pdo->prepare("
            SELECT 
                COALESCE(ic.name, 'Uncategorized') as category_name,
                ic.id as category_id,
                COUNT(ii.id) as item_count,
                COALESCE(SUM(ii.quantity), 0) as total_units,
                COALESCE(SUM(ii.quantity * ii.unit_cost), 0) as valuation
            FROM inventory_items ii
            LEFT JOIN inventory_categories ic ON ii.category_id = ic.id AND ic.deleted_at IS NULL
            WHERE {$whereClause}
            GROUP BY ic.id, ic.name
            ORDER BY valuation DESC
        ");
        $stmtCat->execute($params);
        $byCategory = $stmtCat->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'summary' => [
                'total_items' => (int)($summary['total_items'] ?? 0),
                'total_units' => (int)($summary['total_units'] ?? 0),
                'total_valuation' => (float)($summary['total_valuation'] ?? 0),
                'low_stock_count' => (int)($summary['low_stock_count'] ?? 0),
                'out_of_stock_count' => (int)($summary['out_of_stock_count'] ?? 0),
            ],
            'by_category' => $byCategory
        ];
    }

    /**
     * Inventory valuation detailed report.
     */
    public function getInventoryValuation(int $organizationId, array $filters = []): array
    {
        if (!$this->pdo) return [];

        $where = ["ii.organization_id = :org", "ii.deleted_at IS NULL"];
        $params = [':org' => $organizationId];

        if (!empty($filters['category_id'])) {
            $where[] = "ii.category_id = :cat_id";
            $params[':cat_id'] = (int)$filters['category_id'];
        }
        if (!empty($filters['search'])) {
            $where[] = "(ii.item_name LIKE :search OR ii.item_code LIKE :search)";
            $params[':search'] = '%' . trim($filters['search']) . '%';
        }

        $whereClause = implode(" AND ", $where);

        $stmt = $this->pdo->prepare("
            SELECT 
                ii.id,
                ii.item_code,
                ii.item_name,
                COALESCE(ic.name, 'Uncategorized') as category_name,
                ii.unit,
                ii.quantity,
                ii.unit_cost,
                (ii.quantity * ii.unit_cost) as total_value,
                ii.minimum_stock_level,
                ii.status
            FROM inventory_items ii
            LEFT JOIN inventory_categories ic ON ii.category_id = ic.id
            WHERE {$whereClause}
            ORDER BY total_value DESC
        ");
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $totalValuation = 0;
        $totalUnits = 0;
        foreach ($items as $item) {
            $totalValuation += (float)$item['total_value'];
            $totalUnits += (int)$item['quantity'];
        }

        return [
            'total_items' => count($items),
            'total_units' => $totalUnits,
            'total_valuation' => $totalValuation,
            'items' => $items
        ];
    }

    /**
     * Low-stock inventory report.
     */
    public function getLowStockReport(int $organizationId, array $filters = []): array
    {
        if (!$this->pdo) return [];

        $where = [
            "ii.organization_id = :org",
            "ii.deleted_at IS NULL",
            "ii.quantity <= ii.minimum_stock_level"
        ];
        $params = [':org' => $organizationId];

        if (!empty($filters['category_id'])) {
            $where[] = "ii.category_id = :cat_id";
            $params[':cat_id'] = (int)$filters['category_id'];
        }

        $whereClause = implode(" AND ", $where);

        $stmt = $this->pdo->prepare("
            SELECT 
                ii.id,
                ii.item_code,
                ii.item_name,
                COALESCE(ic.name, 'Uncategorized') as category_name,
                ii.quantity,
                ii.minimum_stock_level,
                ii.reorder_level,
                ii.unit_cost,
                (ii.minimum_stock_level - ii.quantity) as shortage,
                ((ii.minimum_stock_level - ii.quantity) * ii.unit_cost) as estimated_restock_cost,
                ii.status
            FROM inventory_items ii
            LEFT JOIN inventory_categories ic ON ii.category_id = ic.id
            WHERE {$whereClause}
            ORDER BY shortage DESC, ii.quantity ASC
        ");
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $totalShortageUnits = 0;
        $totalRestockCost = 0;
        foreach ($items as $it) {
            $totalShortageUnits += max(0, (int)$it['shortage']);
            $totalRestockCost += max(0, (float)$it['estimated_restock_cost']);
        }

        return [
            'low_stock_count' => count($items),
            'total_shortage_units' => $totalShortageUnits,
            'estimated_restock_cost' => $totalRestockCost,
            'items' => $items
        ];
    }

    /**
     * Stock movement / history report.
     */
    public function getStockMovementReport(int $organizationId, array $filters = []): array
    {
        if (!$this->pdo) return [];

        $where = ["st.organization_id = :org"];
        $params = [':org' => $organizationId];

        if (!empty($filters['transaction_type'])) {
            $where[] = "st.transaction_type = :ttype";
            $params[':ttype'] = $filters['transaction_type'];
        }
        if (!empty($filters['inventory_item_id'])) {
            $where[] = "st.inventory_item_id = :item_id";
            $params[':item_id'] = (int)$filters['inventory_item_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = "st.transaction_date >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "st.transaction_date <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        $whereClause = implode(" AND ", $where);
        $limit = max(1, min(500, (int)($filters['limit'] ?? 100)));

        $stmt = $this->pdo->prepare("
            SELECT 
                st.id,
                st.transaction_date,
                st.transaction_type,
                st.quantity,
                st.unit_cost,
                (st.quantity * st.unit_cost) as total_cost,
                st.reference_type,
                st.reference_id,
                st.remarks,
                st.created_at,
                ii.item_code,
                ii.item_name,
                CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as performed_by_name
            FROM stock_transactions st
            JOIN inventory_items ii ON st.inventory_item_id = ii.id
            LEFT JOIN users u ON st.performed_by = u.id
            WHERE {$whereClause}
            ORDER BY st.transaction_date DESC, st.id DESC
            LIMIT {$limit}
        ");
        $stmt->execute($params);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Breakdown by transaction type
        $typeBreakdown = [];
        foreach ($transactions as $tx) {
            $type = $tx['transaction_type'];
            if (!isset($typeBreakdown[$type])) {
                $typeBreakdown[$type] = ['count' => 0, 'units' => 0, 'value' => 0];
            }
            $typeBreakdown[$type]['count']++;
            $typeBreakdown[$type]['units'] += (int)$tx['quantity'];
            $typeBreakdown[$type]['value'] += (float)$tx['total_cost'];
        }

        return [
            'total_transactions' => count($transactions),
            'by_type' => $typeBreakdown,
            'transactions' => $transactions
        ];
    }

    // =========================================================================
    // MEMBER 5: 2. PURCHASE REPORTS
    // =========================================================================

    /**
     * Purchase summary report.
     */
    public function getPurchaseSummary(int $organizationId, array $filters = []): array
    {
        if (!$this->pdo) return [];

        $wherePr = ["pr.organization_id = :org"];
        $wherePo = ["po.organization_id = :org"];
        $paramsPr = [':org' => $organizationId];
        $paramsPo = [':org' => $organizationId];

        if (!empty($filters['date_from'])) {
            $wherePr[] = "pr.request_date >= :date_from_pr";
            $paramsPr[':date_from_pr'] = $filters['date_from'];
            $wherePo[] = "po.order_date >= :date_from_po";
            $paramsPo[':date_from_po'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $wherePr[] = "pr.request_date <= :date_to_pr";
            $paramsPr[':date_to_pr'] = $filters['date_to'];
            $wherePo[] = "po.order_date <= :date_to_po";
            $paramsPo[':date_to_po'] = $filters['date_to'];
        }

        $stmtPr = $this->pdo->prepare("
            SELECT 
                pr.status,
                COUNT(*) as count
            FROM purchase_requests pr
            WHERE " . implode(" AND ", $wherePr) . "
            GROUP BY pr.status
        ");
        $stmtPr->execute($paramsPr);
        $prStatus = $stmtPr->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        $stmtPo = $this->pdo->prepare("
            SELECT 
                po.status,
                COUNT(*) as count,
                COALESCE(SUM(po.total_amount), 0) as total_amount
            FROM purchase_orders po
            WHERE " . implode(" AND ", $wherePo) . "
            GROUP BY po.status
        ");
        $stmtPo->execute($paramsPo);
        $poStatusRows = $stmtPo->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $poStatus = [];
        $totalPoAmount = 0;
        $totalOrdersCount = 0;
        foreach ($poStatusRows as $row) {
            $poStatus[$row['status']] = [
                'count' => (int)$row['count'],
                'amount' => (float)$row['total_amount']
            ];
            $totalOrdersCount += (int)$row['count'];
            $totalPoAmount += (float)$row['total_amount'];
        }

        $stmtGr = $this->pdo->prepare("
            SELECT COUNT(*) FROM goods_receipts WHERE organization_id = :org
        ");
        $stmtGr->execute([':org' => $organizationId]);
        $totalReceipts = (int)$stmtGr->fetchColumn();

        return [
            'total_requests' => array_sum($prStatus),
            'requests_by_status' => $prStatus,
            'total_orders' => $totalOrdersCount,
            'total_order_amount' => $totalPoAmount,
            'orders_by_status' => $poStatus,
            'total_goods_receipts' => $totalReceipts
        ];
    }

    /**
     * Purchase Request & Order Status detailed report.
     */
    public function getPurchaseRequestOrderStatusReport(int $organizationId, array $filters = []): array
    {
        if (!$this->pdo) return [];

        $where = ["po.organization_id = :org"];
        $params = [':org' => $organizationId];

        if (!empty($filters['status'])) {
            $where[] = "po.status = :status";
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['vendor_id'])) {
            $where[] = "po.vendor_id = :vendor_id";
            $params[':vendor_id'] = (int)$filters['vendor_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = "po.order_date >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "po.order_date <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        $whereClause = implode(" AND ", $where);

        $stmt = $this->pdo->prepare("
            SELECT 
                po.id,
                po.po_number,
                po.order_date,
                po.expected_delivery_date,
                po.total_amount,
                po.status,
                v.company_name as vendor_name,
                pr.request_reference
            FROM purchase_orders po
            LEFT JOIN vendors v ON po.vendor_id = v.id
            LEFT JOIN purchase_requests pr ON po.purchase_request_id = pr.id
            WHERE {$whereClause}
            ORDER BY po.order_date DESC, po.id DESC
        ");
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_orders' => count($orders),
            'orders' => $orders
        ];
    }

    /**
     * Goods Received Report.
     */
    public function getGoodsReceivedReport(int $organizationId, array $filters = []): array
    {
        if (!$this->pdo) return [];

        $where = ["gr.organization_id = :org"];
        $params = [':org' => $organizationId];

        if (!empty($filters['date_from'])) {
            $where[] = "gr.receipt_date >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "gr.receipt_date <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        $whereClause = implode(" AND ", $where);

        $stmt = $this->pdo->prepare("
            SELECT 
                gr.id,
                gr.receipt_number,
                gr.receipt_date,
                gr.remarks,
                gr.created_at,
                po.po_number,
                v.company_name as vendor_name,
                CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as received_by_name
            FROM goods_receipts gr
            JOIN purchase_orders po ON gr.purchase_order_id = po.id
            LEFT JOIN vendors v ON po.vendor_id = v.id
            LEFT JOIN users u ON gr.received_by = u.id
            WHERE {$whereClause}
            ORDER BY gr.receipt_date DESC, gr.id DESC
        ");
        $stmt->execute($params);
        $receipts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_receipts' => count($receipts),
            'receipts' => $receipts
        ];
    }

    /**
     * Procurement Vendor Spend Report.
     */
    public function getProcurementVendorSpendReport(int $organizationId, array $filters = []): array
    {
        if (!$this->pdo) return [];

        $where = ["po.organization_id = :org", "po.status != 'cancelled'"];
        $params = [':org' => $organizationId];

        if (!empty($filters['date_from'])) {
            $where[] = "po.order_date >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "po.order_date <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        $whereClause = implode(" AND ", $where);

        $stmt = $this->pdo->prepare("
            SELECT 
                v.id as vendor_id,
                v.vendor_code,
                v.company_name,
                v.vendor_type,
                COUNT(po.id) as total_orders,
                COALESCE(SUM(po.total_amount), 0) as total_spend,
                COALESCE(AVG(po.total_amount), 0) as average_order_value,
                MAX(po.order_date) as last_order_date
            FROM purchase_orders po
            JOIN vendors v ON po.vendor_id = v.id
            WHERE {$whereClause}
            GROUP BY v.id, v.vendor_code, v.company_name, v.vendor_type
            ORDER BY total_spend DESC
        ");
        $stmt->execute($params);
        $vendorSpend = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $overallSpend = 0;
        foreach ($vendorSpend as $vs) {
            $overallSpend += (float)$vs['total_spend'];
        }

        return [
            'total_vendors' => count($vendorSpend),
            'overall_spend' => $overallSpend,
            'vendors' => $vendorSpend
        ];
    }

    // =========================================================================
    // MEMBER 5: 3. VENDOR REPORTS
    // =========================================================================

    /**
     * Vendor Purchase / Spend Summary.
     */
    public function getVendorPurchaseSpendReport(int $organizationId, array $filters = []): array
    {
        return $this->getProcurementVendorSpendReport($organizationId, $filters);
    }

    /**
     * Vendor Invoice & Payment Report.
     */
    public function getVendorInvoicePaymentReport(int $organizationId, array $filters = []): array
    {
        if (!$this->pdo) return [];

        $where = ["vi.organization_id = :org"];
        $params = [':org' => $organizationId];

        if (!empty($filters['vendor_id'])) {
            $where[] = "vi.vendor_id = :vendor_id";
            $params[':vendor_id'] = (int)$filters['vendor_id'];
        }
        if (!empty($filters['payment_status'])) {
            $where[] = "vi.payment_status = :status";
            $params[':status'] = $filters['payment_status'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = "vi.invoice_date >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "vi.invoice_date <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        $whereClause = implode(" AND ", $where);

        $stmt = $this->pdo->prepare("
            SELECT 
                vi.id,
                vi.invoice_number,
                vi.invoice_date,
                vi.due_date,
                vi.total_amount,
                vi.payment_status,
                v.company_name as vendor_name,
                v.vendor_code,
                po.po_number,
                CASE 
                    WHEN vi.due_date < CURDATE() AND vi.payment_status != 'paid' 
                    THEN DATEDIFF(CURDATE(), vi.due_date) 
                    ELSE 0 
                END as days_overdue
            FROM vendor_invoices vi
            JOIN vendors v ON vi.vendor_id = v.id
            LEFT JOIN purchase_orders po ON vi.purchase_order_id = po.id
            WHERE {$whereClause}
            ORDER BY vi.invoice_date DESC, vi.id DESC
        ");
        $stmt->execute($params);
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $totalInvoiced = 0;
        $totalPaid = 0;
        $totalOutstanding = 0;
        $overdueCount = 0;

        foreach ($invoices as $inv) {
            $amt = (float)$inv['total_amount'];
            $totalInvoiced += $amt;
            if ($inv['payment_status'] === 'paid') {
                $totalPaid += $amt;
            } else {
                $totalOutstanding += $amt;
                if ((int)$inv['days_overdue'] > 0) {
                    $overdueCount++;
                }
            }
        }

        return [
            'summary' => [
                'total_invoices' => count($invoices),
                'total_invoiced_amount' => $totalInvoiced,
                'total_paid_amount' => $totalPaid,
                'total_outstanding_amount' => $totalOutstanding,
                'overdue_invoices_count' => $overdueCount
            ],
            'invoices' => $invoices
        ];
    }

    // =========================================================================
    // MEMBER 5: 4. EQUIPMENT REPORTS
    // =========================================================================

    /**
     * Equipment inventory summary report.
     */
    public function getEquipmentInventorySummary(int $organizationId, array $filters = []): array
    {
        if (!$this->pdo) return [];

        $where = ["e.organization_id = :org", "e.deleted_at IS NULL"];
        $params = [':org' => $organizationId];

        $whereClause = implode(" AND ", $where);

        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_equipment,
                COALESCE(SUM(e.purchase_cost), 0) as total_asset_value,
                COALESCE(SUM(CASE WHEN e.status = 'available' THEN 1 ELSE 0 END), 0) as available_count,
                COALESCE(SUM(CASE WHEN e.status = 'assigned' THEN 1 ELSE 0 END), 0) as assigned_count,
                COALESCE(SUM(CASE WHEN e.status = 'under_maintenance' THEN 1 ELSE 0 END), 0) as maintenance_count,
                COALESCE(SUM(CASE WHEN e.status = 'damaged' THEN 1 ELSE 0 END), 0) as damaged_count,
                COALESCE(SUM(CASE WHEN e.status = 'disposed' THEN 1 ELSE 0 END), 0) as disposed_count
            FROM equipment e
            WHERE {$whereClause}
        ");
        $stmt->execute($params);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Condition breakdown
        $stmtCond = $this->pdo->prepare("
            SELECT 
                e.condition_status,
                COUNT(*) as count
            FROM equipment e
            WHERE {$whereClause}
            GROUP BY e.condition_status
        ");
        $stmtCond->execute($params);
        $conditions = $stmtCond->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        return [
            'summary' => [
                'total_equipment' => (int)($summary['total_equipment'] ?? 0),
                'total_asset_value' => (float)($summary['total_asset_value'] ?? 0),
                'available_count' => (int)($summary['available_count'] ?? 0),
                'assigned_count' => (int)($summary['assigned_count'] ?? 0),
                'maintenance_count' => (int)($summary['maintenance_count'] ?? 0),
                'damaged_count' => (int)($summary['damaged_count'] ?? 0),
                'disposed_count' => (int)($summary['disposed_count'] ?? 0),
            ],
            'by_condition' => $conditions
        ];
    }

    /**
     * Assigned Equipment Report.
     */
    public function getAssignedEquipmentReport(int $organizationId, array $filters = []): array
    {
        if (!$this->pdo) return [];

        $where = [
            "ea.organization_id = :org",
            "ea.status = 'assigned'"
        ];
        $params = [':org' => $organizationId];

        if (!empty($filters['assignee_type'])) {
            $where[] = "ea.assignee_type = :atype";
            $params[':atype'] = $filters['assignee_type'];
        }

        $whereClause = implode(" AND ", $where);

        $stmt = $this->pdo->prepare("
            SELECT 
                ea.id,
                ea.assigned_date,
                ea.expected_return_date,
                ea.condition_on_issue,
                ea.assignee_type,
                ea.notes,
                e.asset_code,
                e.equipment_name,
                CASE 
                    WHEN ea.assignee_type = 'athlete' THEN CONCAT(COALESCE(a.first_name, ''), ' ', COALESCE(a.last_name, ''))
                    WHEN ea.assignee_type = 'coach' THEN CONCAT(COALESCE(ce.first_name, ''), ' ', COALESCE(ce.last_name, ''))
                    WHEN ea.assignee_type = 'employee' THEN CONCAT(COALESCE(em.first_name, ''), ' ', COALESCE(em.last_name, ''))
                    WHEN ea.assignee_type = 'team' THEN t.name
                    WHEN ea.assignee_type = 'venue' THEN v.name
                    ELSE 'Unknown'
                END as assignee_name
            FROM equipment_assignments ea
            JOIN equipment e ON ea.equipment_id = e.id
            LEFT JOIN athletes a ON ea.athlete_id = a.id
            LEFT JOIN coach_profiles cp ON ea.coach_id = cp.id
            LEFT JOIN employees ce ON cp.employee_id = ce.id
            LEFT JOIN employees em ON ea.employee_id = em.id
            LEFT JOIN teams t ON ea.team_id = t.id
            LEFT JOIN venues v ON ea.venue_id = v.id
            WHERE {$whereClause}
            ORDER BY ea.assigned_date DESC
        ");
        $stmt->execute($params);
        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_assigned' => count($assignments),
            'assignments' => $assignments
        ];
    }

    /**
     * Returned Equipment Report.
     */
    public function getReturnedEquipmentReport(int $organizationId, array $filters = []): array
    {
        if (!$this->pdo) return [];

        $where = [
            "ea.organization_id = :org",
            "ea.status = 'returned'"
        ];
        $params = [':org' => $organizationId];

        if (!empty($filters['date_from'])) {
            $where[] = "ea.returned_date >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "ea.returned_date <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        $whereClause = implode(" AND ", $where);

        $stmt = $this->pdo->prepare("
            SELECT 
                ea.id,
                ea.assigned_date,
                ea.returned_date,
                ea.condition_on_issue,
                ea.condition_on_return,
                ea.assignee_type,
                ea.notes,
                e.asset_code,
                e.equipment_name,
                CASE 
                    WHEN ea.assignee_type = 'athlete' THEN CONCAT(COALESCE(a.first_name, ''), ' ', COALESCE(a.last_name, ''))
                    WHEN ea.assignee_type = 'coach' THEN CONCAT(COALESCE(ce.first_name, ''), ' ', COALESCE(ce.last_name, ''))
                    WHEN ea.assignee_type = 'employee' THEN CONCAT(COALESCE(em.first_name, ''), ' ', COALESCE(em.last_name, ''))
                    WHEN ea.assignee_type = 'team' THEN t.name
                    WHEN ea.assignee_type = 'venue' THEN v.name
                    ELSE 'Unknown'
                END as assignee_name
            FROM equipment_assignments ea
            JOIN equipment e ON ea.equipment_id = e.id
            LEFT JOIN athletes a ON ea.athlete_id = a.id
            LEFT JOIN coach_profiles cp ON ea.coach_id = cp.id
            LEFT JOIN employees ce ON cp.employee_id = ce.id
            LEFT JOIN employees em ON ea.employee_id = em.id
            LEFT JOIN teams t ON ea.team_id = t.id
            LEFT JOIN venues v ON ea.venue_id = v.id
            WHERE {$whereClause}
            ORDER BY ea.returned_date DESC
        ");
        $stmt->execute($params);
        $returns = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_returned' => count($returns),
            'returns' => $returns
        ];
    }

    /**
     * Overdue Equipment Returns Report.
     */
    public function getOverdueEquipmentReport(int $organizationId, array $filters = []): array
    {
        if (!$this->pdo) return [];

        $where = [
            "ea.organization_id = :org",
            "ea.status = 'assigned'",
            "ea.expected_return_date < CURDATE()"
        ];
        $params = [':org' => $organizationId];

        $whereClause = implode(" AND ", $where);

        $stmt = $this->pdo->prepare("
            SELECT 
                ea.id,
                ea.assigned_date,
                ea.expected_return_date,
                DATEDIFF(CURDATE(), ea.expected_return_date) as days_overdue,
                ea.condition_on_issue,
                ea.assignee_type,
                e.asset_code,
                e.equipment_name,
                CASE 
                    WHEN ea.assignee_type = 'athlete' THEN CONCAT(COALESCE(a.first_name, ''), ' ', COALESCE(a.last_name, ''))
                    WHEN ea.assignee_type = 'coach' THEN CONCAT(COALESCE(ce.first_name, ''), ' ', COALESCE(ce.last_name, ''))
                    WHEN ea.assignee_type = 'employee' THEN CONCAT(COALESCE(em.first_name, ''), ' ', COALESCE(em.last_name, ''))
                    WHEN ea.assignee_type = 'team' THEN t.name
                    WHEN ea.assignee_type = 'venue' THEN v.name
                    ELSE 'Unknown'
                END as assignee_name
            FROM equipment_assignments ea
            JOIN equipment e ON ea.equipment_id = e.id
            LEFT JOIN athletes a ON ea.athlete_id = a.id
            LEFT JOIN coach_profiles cp ON ea.coach_id = cp.id
            LEFT JOIN employees ce ON cp.employee_id = ce.id
            LEFT JOIN employees em ON ea.employee_id = em.id
            LEFT JOIN teams t ON ea.team_id = t.id
            LEFT JOIN venues v ON ea.venue_id = v.id
            WHERE {$whereClause}
            ORDER BY days_overdue DESC
        ");
        $stmt->execute($params);
        $overdue = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'overdue_count' => count($overdue),
            'items' => $overdue
        ];
    }

    /**
     * Equipment Condition Summary.
     */
    public function getEquipmentConditionSummary(int $organizationId, array $filters = []): array
    {
        if (!$this->pdo) return [];

        $stmt = $this->pdo->prepare("
            SELECT 
                e.condition_status,
                COUNT(*) as count,
                COALESCE(SUM(e.purchase_cost), 0) as valuation
            FROM equipment e
            WHERE e.organization_id = :org AND e.deleted_at IS NULL
            GROUP BY e.condition_status
            ORDER BY count DESC
        ");
        $stmt->execute([':org' => $organizationId]);
        $conditions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Needing attention (damaged or under_maintenance)
        $stmtAttention = $this->pdo->prepare("
            SELECT 
                e.id,
                e.asset_code,
                e.equipment_name,
                e.condition_status,
                e.status,
                e.current_location
            FROM equipment e
            WHERE e.organization_id = :org AND e.deleted_at IS NULL
              AND (e.condition_status IN ('damaged', 'poor') OR e.status = 'under_maintenance')
            ORDER BY e.condition_status ASC
        ");
        $stmtAttention->execute([':org' => $organizationId]);
        $attentionItems = $stmtAttention->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'condition_breakdown' => $conditions,
            'attention_required_count' => count($attentionItems),
            'attention_items' => $attentionItems
        ];
    }

    // =========================================================================
    // MEMBER 5: 5. FINANCIAL REPORTS
    // =========================================================================

    /**
     * Income Summary Report.
     */
    public function getIncomeSummary(int $organizationId, array $filters = []): array
    {
        if (!$this->pdo) return [];

        $where = ["it.organization_id = :org"];
        $params = [':org' => $organizationId];

        if (!empty($filters['category_id'])) {
            $where[] = "it.finance_category_id = :cat_id";
            $params[':cat_id'] = (int)$filters['category_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = "it.income_date >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "it.income_date <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        $whereClause = implode(" AND ", $where);

        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_transactions,
                COALESCE(SUM(it.amount), 0) as total_income
            FROM income_transactions it
            WHERE {$whereClause}
        ");
        $stmt->execute($params);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmtCat = $this->pdo->prepare("
            SELECT 
                COALESCE(fc.name, 'Uncategorized') as category_name,
                fc.id as category_id,
                COUNT(it.id) as transaction_count,
                COALESCE(SUM(it.amount), 0) as total_amount
            FROM income_transactions it
            LEFT JOIN finance_categories fc ON it.finance_category_id = fc.id
            WHERE {$whereClause}
            GROUP BY fc.id, fc.name
            ORDER BY total_amount DESC
        ");
        $stmtCat->execute($params);
        $byCategory = $stmtCat->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_income' => (float)($totals['total_income'] ?? 0),
            'total_transactions' => (int)($totals['total_transactions'] ?? 0),
            'by_category' => $byCategory
        ];
    }

    /**
     * Expense Summary Report.
     */
    public function getExpenseSummary(int $organizationId, array $filters = []): array
    {
        if (!$this->pdo) return [];

        $where = ["e.organization_id = :org", "e.deleted_at IS NULL"];
        $params = [':org' => $organizationId];

        if (!empty($filters['category_id'])) {
            $where[] = "e.finance_category_id = :cat_id";
            $params[':cat_id'] = (int)$filters['category_id'];
        }
        if (!empty($filters['vendor_id'])) {
            $where[] = "e.vendor_id = :vendor_id";
            $params[':vendor_id'] = (int)$filters['vendor_id'];
        }
        if (!empty($filters['payment_status'])) {
            $where[] = "e.payment_status = :status";
            $params[':status'] = $filters['payment_status'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = "e.expense_date >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "e.expense_date <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        $whereClause = implode(" AND ", $where);

        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_expenses,
                COALESCE(SUM(e.total_amount), 0) as total_amount,
                COALESCE(SUM(CASE WHEN e.payment_status = 'paid' THEN e.total_amount ELSE 0 END), 0) as paid_amount,
                COALESCE(SUM(CASE WHEN e.payment_status != 'paid' THEN e.total_amount ELSE 0 END), 0) as pending_amount
            FROM expenses e
            WHERE {$whereClause}
        ");
        $stmt->execute($params);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmtCat = $this->pdo->prepare("
            SELECT 
                COALESCE(fc.name, 'Uncategorized') as category_name,
                fc.id as category_id,
                COUNT(e.id) as expense_count,
                COALESCE(SUM(e.total_amount), 0) as total_amount
            FROM expenses e
            LEFT JOIN finance_categories fc ON e.finance_category_id = fc.id
            WHERE {$whereClause}
            GROUP BY fc.id, fc.name
            ORDER BY total_amount DESC
        ");
        $stmtCat->execute($params);
        $byCategory = $stmtCat->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_expenses' => (int)($totals['total_expenses'] ?? 0),
            'total_amount' => (float)($totals['total_amount'] ?? 0),
            'paid_amount' => (float)($totals['paid_amount'] ?? 0),
            'pending_amount' => (float)($totals['pending_amount'] ?? 0),
            'by_category' => $byCategory
        ];
    }

    /**
     * Income vs Expense Report.
     */
    public function getIncomeVsExpenseReport(int $organizationId, array $filters = []): array
    {
        if (!$this->pdo) return [];

        $income = $this->getIncomeSummary($organizationId, $filters);
        $expense = $this->getExpenseSummary($organizationId, $filters);

        $totalIncome = (float)($income['total_income'] ?? 0);
        $totalExpense = (float)($expense['total_amount'] ?? 0);
        $netMargin = $totalIncome - $totalExpense;
        $profitMarginRate = $totalIncome > 0 ? round(($netMargin / $totalIncome) * 100, 2) : 0;

        // Monthly comparison (last 6 months)
        $stmtMonthly = $this->pdo->prepare("
            SELECT 
                months.m_label,
                COALESCE(inc.total_inc, 0) as income,
                COALESCE(exp.total_exp, 0) as expense
            FROM (
                SELECT DATE_FORMAT(CURDATE() - INTERVAL (n.num) MONTH, '%Y-%m') as m_label
                FROM (SELECT 0 as num UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) n
            ) months
            LEFT JOIN (
                SELECT DATE_FORMAT(income_date, '%Y-%m') as ym, SUM(amount) as total_inc
                FROM income_transactions
                WHERE organization_id = :org
                GROUP BY ym
            ) inc ON months.m_label = inc.ym
            LEFT JOIN (
                SELECT DATE_FORMAT(expense_date, '%Y-%m') as ym, SUM(total_amount) as total_exp
                FROM expenses
                WHERE organization_id = :org AND deleted_at IS NULL
                GROUP BY ym
            ) exp ON months.m_label = exp.ym
            ORDER BY months.m_label ASC
        ");
        $stmtMonthly->execute([':org' => $organizationId]);
        $monthlyData = $stmtMonthly->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net_margin' => $netMargin,
            'profit_margin_rate' => $profitMarginRate,
            'monthly_comparison' => $monthlyData
        ];
    }

    /**
     * Budget vs Actual Report.
     */
    public function getBudgetVsActualReport(int $organizationId, array $filters = []): array
    {
        if (!$this->pdo) return [];

        $where = ["b.organization_id = :org"];
        $params = [':org' => $organizationId];

        if (!empty($filters['status'])) {
            $where[] = "b.status = :status";
            $params[':status'] = $filters['status'];
        }

        $stmt = $this->pdo->prepare("
            SELECT 
                b.id,
                b.budget_name,
                b.financial_year,
                b.start_date,
                b.end_date,
                b.total_budget,
                b.status
            FROM budgets b
            WHERE " . implode(" AND ", $where) . "
            ORDER BY b.start_date DESC, b.id DESC
        ");
        $stmt->execute($params);
        $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $results = [];
        $overallAllocated = 0;
        $overallSpent = 0;

        foreach ($budgets as $b) {
            $bId = (int)$b['id'];
            $totalBudget = (float)$b['total_budget'];
            $startDate = $b['start_date'];
            $endDate = $b['end_date'];

            // Calculate actual spend for this budget's period
            $stmtSpend = $this->pdo->prepare("
                SELECT COALESCE(SUM(total_amount), 0)
                FROM expenses
                WHERE organization_id = :org 
                  AND deleted_at IS NULL
                  AND expense_date >= :sdate 
                  AND expense_date <= :edate
            ");
            $stmtSpend->execute([
                ':org' => $organizationId,
                ':sdate' => $startDate,
                ':edate' => $endDate
            ]);
            $actualSpent = (float)$stmtSpend->fetchColumn();

            $variance = $totalBudget - $actualSpent;
            $utilization = $totalBudget > 0 ? round(($actualSpent / $totalBudget) * 100, 2) : 0;

            // Budget items breakdown
            $stmtItems = $this->pdo->prepare("
                SELECT 
                    bi.id,
                    bi.allocated_amount,
                    bi.description,
                    fc.name as category_name
                FROM budget_items bi
                LEFT JOIN finance_categories fc ON bi.finance_category_id = fc.id
                WHERE bi.budget_id = :bid
            ");
            $stmtItems->execute([':bid' => $bId]);
            $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $overallAllocated += $totalBudget;
            $overallSpent += $actualSpent;

            $results[] = [
                'id' => $bId,
                'budget_name' => $b['budget_name'],
                'financial_year' => $b['financial_year'],
                'start_date' => $startDate,
                'end_date' => $endDate,
                'allocated_amount' => $totalBudget,
                'actual_spent' => $actualSpent,
                'variance' => $variance,
                'utilization_rate' => $utilization,
                'status' => $b['status'],
                'items' => $items
            ];
        }

        $overallVariance = $overallAllocated - $overallSpent;
        $overallUtilization = $overallAllocated > 0 ? round(($overallSpent / $overallAllocated) * 100, 2) : 0;

        return [
            'summary' => [
                'total_budgets' => count($results),
                'total_allocated' => $overallAllocated,
                'total_spent' => $overallSpent,
                'variance' => $overallVariance,
                'utilization_rate' => $overallUtilization
            ],
            'budgets' => $results
        ];
    }

    /**
     * Budget Utilization analysis.
     */
    public function getBudgetUtilizationReport(int $organizationId, array $filters = []): array
    {
        $bva = $this->getBudgetVsActualReport($organizationId, $filters);
        $budgets = $bva['budgets'] ?? [];

        $exceeded = [];
        $nearingThreshold = [];
        $normal = [];

        foreach ($budgets as $b) {
            $rate = (float)$b['utilization_rate'];
            if ($rate >= 100) {
                $exceeded[] = $b;
            } elseif ($rate >= 90) {
                $nearingThreshold[] = $b;
            } else {
                $normal[] = $b;
            }
        }

        return [
            'total_budgets' => count($budgets),
            'exceeded_count' => count($exceeded),
            'warning_count' => count($nearingThreshold),
            'normal_count' => count($normal),
            'exceeded_budgets' => $exceeded,
            'warning_budgets' => $nearingThreshold,
            'normal_budgets' => $normal
        ];
    }

    /**
     * Executive Finance Summary.
     */
    public function getFinanceSummary(int $organizationId, array $filters = []): array
    {
        if (!$this->pdo) return [];

        $inc = $this->getIncomeSummary($organizationId, $filters);
        $exp = $this->getExpenseSummary($organizationId, $filters);
        $bva = $this->getBudgetVsActualReport($organizationId, $filters);
        $inv = $this->getVendorInvoicePaymentReport($organizationId, $filters);

        $totalIncome = (float)($inc['total_income'] ?? 0);
        $totalExpense = (float)($exp['total_amount'] ?? 0);
        $net = $totalIncome - $totalExpense;

        return [
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net_profit_loss' => $net,
            'budget_allocated' => (float)($bva['summary']['total_allocated'] ?? 0),
            'budget_spent' => (float)($bva['summary']['total_spent'] ?? 0),
            'budget_utilization_rate' => (float)($bva['summary']['utilization_rate'] ?? 0),
            'pending_vendor_invoices' => (float)($inv['summary']['total_outstanding_amount'] ?? 0),
            'overdue_invoices_count' => (int)($inv['summary']['overdue_invoices_count'] ?? 0)
        ];
    }
}


