<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
class ExportAdminRoutes extends Command{
 protected $signature='admin:export-routes';
 protected $description='Export admin routes';
 public function handle(){ $this->info('routes exported'); }
}