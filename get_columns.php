<?php
require_once 'backend/vendor/autoload.php';
require_once 'backend/bootstrap/app.php';

use Illuminate\Database\Capsule\Manager as Capsule;

$columns = Capsule::schema()->getColumnListing('housekeeping_tasks');
foreach($columns as $col) {
    echo $col . "\n";
}
