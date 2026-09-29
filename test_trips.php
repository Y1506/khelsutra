<?php
require 'backend/vendor/autoload.php';
$app = require_once 'backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::create('/api/v1/trips', 'GET');
$response = $kernel->handle($request);
echo "Status: " . $response->status() . "\n";
echo "Content: " . $response->getContent() . "\n";
