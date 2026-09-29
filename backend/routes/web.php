<?php

/**
 * KhelSutra Web Routes
 * Maps URL paths to Master UI/UX Views
 */

return [
    '/' => function() {
        return ['view' => 'dashboard/index'];
    },
    '/dashboard' => function() {
        return ['view' => 'dashboard/index'];
    },
    '/athletes' => function() {
        return ['view' => 'sports/athletes'];
    },
    '/athletes/create' => function() {
        return ['view' => 'sports/athletes-create'];
    },
    '/athletes/{id}' => function($id) {
        return ['view' => 'sports/athletes-show', 'data' => ['id' => $id]];
    },
    '/athletes/{id}/edit' => function($id) {
        return ['view' => 'sports/athletes-edit', 'data' => ['id' => $id]];
    },

    '/coaches' => function() {
        return ['view' => 'sports/coaches'];
    },
    '/coaches/create' => function() {
        return ['view' => 'sports/coaches-create'];
    },
    '/coaches/{id}' => function($id) {
        return ['view' => 'sports/coaches-show', 'data' => ['id' => $id]];
    },
    '/coaches/{id}/edit' => function($id) {
        return ['view' => 'sports/coaches-edit', 'data' => ['id' => $id]];
    },

    '/teams' => function() {
        return ['view' => 'sports/teams'];
    },
    '/teams/create' => function() {
        return ['view' => 'sports/teams-create'];
    },
    '/teams/{id}' => function($id) {
        return ['view' => 'sports/teams-show', 'data' => ['id' => $id]];
    },
    '/teams/{id}/edit' => function($id) {
        return ['view' => 'sports/teams-edit', 'data' => ['id' => $id]];
    },

    '/tournaments' => function() {
        return ['view' => 'competitions/tournaments'];
    },
    '/tournaments/create' => function() {
        return ['view' => 'competitions/tournaments-create'];
    },
    '/tournaments/{id}' => function($id) {
        return ['view' => 'competitions/tournaments-show', 'data' => ['id' => $id]];
    },
    '/tournaments/{id}/edit' => function($id) {
        return ['view' => 'competitions/tournaments-edit', 'data' => ['id' => $id]];
    },

    '/training' => function() {
        return ['view' => 'sports/training'];
    },
    '/training/create' => function() {
        return ['view' => 'sports/training-create'];
    },
    '/training/{id}' => function($id) {
        return ['view' => 'sports/training-show', 'data' => ['id' => $id]];
    },
    '/training/{id}/edit' => function($id) {
        return ['view' => 'sports/training-edit', 'data' => ['id' => $id]];
    },

    '/venues' => function() {
        return ['view' => 'venues/venues'];
    },
    '/venues/create' => function() {
        return ['view' => 'venues/venues-create'];
    },
    '/venues/bookings/create' => function() {
        return ['view' => 'venues/bookings-create'];
    },
    '/venues/{id}' => function($id) {
        return ['view' => 'venues/venues-show', 'data' => ['id' => $id]];
    },
    '/venues/{id}/edit' => function($id) {
        return ['view' => 'venues/venues-edit', 'data' => ['id' => $id]];
    },

    '/inventory' => function() {
        return ['view' => 'inventory/inventory'];
    },
    '/inventory/create' => function() {
        return ['view' => 'inventory/inventory-create'];
    },
    '/inventory/{id}' => function($id) {
        return ['view' => 'inventory/inventory-show', 'data' => ['id' => $id]];
    },
    '/inventory/{id}/edit' => function($id) {
        return ['view' => 'inventory/inventory-edit', 'data' => ['id' => $id]];
    },
    '/inventory/categories' => function() {
        return ['view' => 'inventory/inventory-categories'];
    },
    '/equipment' => function() {
        return ['view' => 'equipment/equipment-index'];
    },
    '/equipment/create' => function() {
        return ['view' => 'equipment/equipment-create'];
    },
    '/equipment/{id}' => function($id) {
        return ['view' => 'equipment/equipment-show', 'data' => ['id' => $id, 'data' => ['id' => $id]]];
    },
    '/equipment/{id}/edit' => function($id) {
        return ['view' => 'equipment/equipment-edit', 'data' => ['id' => $id, 'data' => ['id' => $id]]];
    },
    '/vendors' => function() {
        return ['view' => 'vendors/vendors-index'];
    },
    '/vendors/create' => function() {
        return ['view' => 'vendors/vendors-create'];
    },
    '/vendors/{id}' => function($id) {
        return ['view' => 'vendors/vendors-show', 'data' => ['id' => $id, 'data' => ['id' => $id]]];
    },
    '/vendors/{id}/edit' => function($id) {
        return ['view' => 'vendors/vendors-edit', 'data' => ['id' => $id, 'data' => ['id' => $id]]];
    },
    '/purchases' => function() {
        return ['view' => 'purchases/purchases-index'];
    },
    '/purchases/requests/create' => function() {
        return ['view' => 'purchases/requests-create'];
    },
    '/purchases/requests/{id}' => function($id) {
        return ['view' => 'purchases/requests-show', 'data' => ['id' => $id]];
    },
    '/purchases/orders/create' => function() {
        return ['view' => 'purchases/orders-create'];
    },
    '/purchases/orders/{id}' => function($id) {
        return ['view' => 'purchases/orders-show', 'data' => ['id' => $id]];
    },
    '/purchases/receipts/{id}' => function($id) {
        return ['view' => 'purchases/receipts-show', 'data' => ['id' => $id]];
    },
    '/finance' => function() {
        return ['view' => 'finance/finance-index'];
    },
    '/finance/expenses/create' => function() {
        return ['view' => 'finance/expenses-create'];
    },
    '/finance/expenses/{id}' => function($id) {
        return ['view' => 'finance/expenses-show', 'data' => ['id' => $id]];
    },
    '/finance/income/create' => function() {
        return ['view' => 'finance/income-create'];
    },
    '/finance/income/{id}' => function($id) {
        return ['view' => 'finance/income-show', 'data' => ['id' => $id]];
    },
    '/finance/budgets/create' => function() {
        return ['view' => 'finance/budgets-create'];
    },
    '/finance/budgets/{id}' => function($id) {
        return ['view' => 'finance/budgets-show', 'data' => ['id' => $id]];
    },
    '/finance/categories' => function() {
        return ['view' => 'finance/categories-index'];
    },
    '/hr-finance' => function() {
        return ['view' => 'hr/hr-finance'];
    },
    '/reports' => function() {
        return ['view' => 'reports/reports'];
    },
    '/settings' => function() {
        return ['view' => 'settings/settings'];
    },
    '/notifications' => function() {
        return ['view' => 'notifications/index'];
    },
    '/search' => function() {
        return ['view' => 'search/index'];
    },
    '/login' => function() {
        return ['view' => 'auth/login'];
    },

    // ==========================================
    // Member 1: Super Admin & Organisations
    // ==========================================
    '/super-admin/organizations' => function() {
        return ['view' => 'super-admin/organizations-index'];
    },
    '/super-admin/organizations/create' => function() {
        return ['view' => 'super-admin/organizations-create'];
    },
    '/super-admin/organizations/{id}' => function($id) {
        return ['view' => 'super-admin/organizations-show', 'data' => ['id' => $id]];
    },
    '/super-admin/organizations/{id}/edit' => function($id) {
        return ['view' => 'super-admin/organizations-edit', 'data' => ['id' => $id]];
    },

    // ==========================================
    // Member 1: Users & RBAC
    // ==========================================
    '/users' => function() {
        return ['view' => 'users/index'];
    },
    '/users/create' => function() {
        return ['view' => 'users/create'];
    },
    '/users/{id}' => function($id) {
        return ['view' => 'users/show', 'data' => ['id' => $id]];
    },
    '/users/{id}/edit' => function($id) {
        return ['view' => 'users/edit', 'data' => ['id' => $id]];
    },

    '/roles' => function() {
        return ['view' => 'rbac/roles'];
    },
    '/roles/{id}' => function($id) {
        return ['view' => 'rbac/role-details', 'data' => ['id' => $id]];
    },
    '/permissions' => function() {
        return ['view' => 'rbac/permissions'];
    },

    // ==========================================
    // Member 1: HR & Staff
    // ==========================================
    '/hr/employees' => function() {
        return ['view' => 'hr/employees-index'];
    },
    '/hr/employees/create' => function() {
        return ['view' => 'hr/employees-create'];
    },
    '/hr/employees/{id}' => function($id) {
        return ['view' => 'hr/employees-show', 'data' => ['id' => $id]];
    },
    '/hr/employees/{id}/edit' => function($id) {
        return ['view' => 'hr/employees-edit', 'data' => ['id' => $id]];
    },
    '/hr/departments' => function() {
        return ['view' => 'hr/departments-index'];
    },
    '/hr/employee-categories' => function() {
        return ['view' => 'hr/categories-index'];
    },
    '/hr/employees/{id}/documents' => function($id) {
        return ['view' => 'hr/documents-index', 'data' => ['id' => $id]];
    },

    // ==========================================
    // Member 1: Attendance
    // ==========================================
    '/attendance/training' => function() {
        return ['view' => 'attendance/training'];
    },
    '/attendance/matches' => function() {
        return ['view' => 'attendance/matches'];
    },
    '/attendance/history' => function() {
        return ['view' => 'attendance/history'];
    },

    // ==========================================
    // Member 1: Leave Management
    // ==========================================
    '/leave' => function() {
        return ['view' => 'leave/index'];
    },
    '/leave/create' => function() {
        return ['view' => 'leave/create'];
    },
    '/leave/{id}' => function($id) {
        return ['view' => 'leave/show', 'data' => ['id' => $id]];
    },

    // ==========================================
    // Member 1: Payroll Management
    // ==========================================
    '/payroll' => function() {
        return ['view' => 'payroll/index'];
    },
    '/payroll/salary-structures' => function() {
        return ['view' => 'payroll/salary-structures'];
    },
    '/payroll/periods' => function() {
        return ['view' => 'payroll/periods'];
    },
    '/payroll/periods/{id}' => function($id) {
        return ['view' => 'payroll/periods', 'data' => ['id' => $id]];
    },
    '/payroll/{id}' => function($id) {
        return ['view' => 'payroll/show', 'data' => ['id' => $id]];
    },

    // ==========================================
    // Member 1: Settings & Audit Logs
    // ==========================================
    '/settings/organization' => function() {
        return ['view' => 'settings/organization'];
    },
    '/audit-logs' => function() {
        return ['view' => 'audit/index'];
    },

    // ==========================================
    // Member 4: Operations & Logistics
    // ==========================================
    '/operations/venues' => function() { return ['view' => 'operations/venues/index']; },
    '/operations/venues/create' => function() { return ['view' => 'operations/venues/create']; },
    '/operations/venues/{id}' => function($id) { return ['view' => 'operations/venues/show', 'data' => ['id' => $id]]; },
    '/operations/venues/{id}/edit' => function($id) { return ['view' => 'operations/venues/edit', 'data' => ['id' => $id]]; },
    '/operations/venues/{venueId}/facilities' => function($venueId) { return ['view' => 'operations/facilities/index', 'data' => ['venueId' => $venueId]]; },
    '/operations/venues/{venueId}/facilities/create' => function($venueId) { return ['view' => 'operations/facilities/create', 'data' => ['venueId' => $venueId]]; },
    '/operations/venues/{venueId}/facilities/{id}/edit' => function($venueId, $id) { return ['view' => 'operations/facilities/edit', 'data' => ['venueId' => $venueId, 'id' => $id]]; },
    '/operations/bookings' => function() { return ['view' => 'operations/bookings/index']; },

    '/operations/maintenance' => function() { return ['view' => 'operations/maintenance/index']; },
    '/operations/housekeeping' => function() { return ['view' => 'operations/housekeeping/index']; },
    '/operations/events' => function() { return ['view' => 'operations/events/index']; },
    '/operations/school-activities' => function() { return ['view' => 'operations/school-activities/index']; },
    '/operations/transport' => function() { return ['view' => 'operations/transport/index']; },
    '/operations/accommodation' => function() { return ['view' => 'operations/accommodation/index']; },
];


