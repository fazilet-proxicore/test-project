<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

$status = Artisan::call('app:merge-junie-guidelines');
echo "Command exit code: $status\n";
echo "Command output:\n".Artisan::output()."\n";

$targetPath = base_path('.junie/guidelines.md');
if (File::exists($targetPath)) {
    echo 'File .junie/guidelines.md exists. Size: '.File::size($targetPath)." bytes\n";
} else {
    echo "File .junie/guidelines.md does NOT exist.\n";
}
