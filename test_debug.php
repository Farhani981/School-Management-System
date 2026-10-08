<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$blade = app('blade.compiler');
$compiled = $blade->compileString(file_get_contents('resources/views/components/sidebar.blade.php'));
file_put_contents('compiled_sidebar.php', $compiled);
echo "Compiled length: " . strlen($compiled) . "\n";
