<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key' => 'approval_mode',
                'value' => 'two_tier',
                'type' => 'string',
                'description' => 'نحوه تایید (two_tier یا employer_only)',
            ],
            [
                'key' => 'allow_reopen',
                'value' => 'false',
                'type' => 'boolean',
                'description' => 'آیا وظایف تایید شده قابلیت بازگشایی دارند؟',
            ],
            [
                'key' => 'require_evidence_on_submit',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'الزام وجود مستندات هنگام ثبت پایان وظیفه',
            ],
            [
                'key' => 'lock_weight',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'قفل شدن وزن وظیفه پس از ارجاع',
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
