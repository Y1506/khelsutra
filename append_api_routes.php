<?php
/**
 * Utility script to append maintenance and housekeeping routes to backend API routes file.
 *
 * Inserts route definitions for maintenance and housekeeping endpoints before the fallback
 * section in the api.php routes file.
 */

$content = file_get_contents('backend/routes/api.php');
$pos = strrpos($content, '// 13. Fallback for other unassigned modules');
if ($pos !== false) {
    $insert = "
    // Member 4: Operations Maintenance & Housekeeping
    if (\$uri === '/api/v1/maintenance' && \$method === 'GET') {
        \$controller = new \App\Http\Controllers\Api\V1\Operations\MaintenanceController();
        return \$controller->index(\$orgId, \$requestData);
    }
    if (\$uri === '/api/v1/maintenance' && \$method === 'POST') {
        \$controller = new \App\Http\Controllers\Api\V1\Operations\MaintenanceController();
        return \$controller->store(\$orgId, \$requestData);
    }
    if (\$uri === '/api/v1/housekeeping' && \$method === 'GET') {
        \$controller = new \App\Http\Controllers\Api\V1\Operations\HousekeepingController();
        return \$controller->index(\$orgId, \$requestData);
    }
    if (\$uri === '/api/v1/housekeeping' && \$method === 'POST') {
        \$controller = new \App\Http\Controllers\Api\V1\Operations\HousekeepingController();
        return \$controller->store(\$orgId, \$requestData);
    }
";
    $content = substr_replace($content, $insert . "\n    // 13. Fallback for other unassigned modules", $pos, strlen('// 13. Fallback for other unassigned modules'));
    file_put_contents('backend/routes/api.php', $content);
}
