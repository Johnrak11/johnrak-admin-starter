<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DashboardWidget;

class DashboardWidgetSeeder extends Seeder
{
    public function run()
    {
        $widgets = [
            [
                'name' => 'Server Pulse',
                'component' => 'WidgetServerHealth',
                'position' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Backup Status',
                'component' => 'WidgetBackupStatus',
                'position' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Quick AI',
                'component' => 'WidgetQuickAi',
                'position' => 3,
                'is_active' => true,
            ],
        ];

        foreach ($widgets as $w) {
            DashboardWidget::firstOrCreate(
                ['component' => $w['component']],
                $w
            );
        }
    }
}
