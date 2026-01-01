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
                'title' => 'Server Health',
                'component_name' => 'ServerHealthWidget',
                'width' => 2,
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'title' => 'Backup Status',
                'component_name' => 'BackupStatusWidget',
                'width' => 1,
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'title' => 'AI Quick Action',
                'component_name' => 'AiQuickActionWidget',
                'width' => 1,
                'sort_order' => 3,
                'is_active' => true,
            ],
        ];

        foreach ($widgets as $w) {
            DashboardWidget::firstOrCreate(
                ['component_name' => $w['component_name']],
                $w
            );
        }
    }
}
