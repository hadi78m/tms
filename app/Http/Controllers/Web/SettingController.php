<?php

namespace App\Http\Controllers\Web;

use App\Domain\Services\SettingsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function __construct(protected SettingsService $settingsService) {}

    public function index()
    {
        $settings = $this->settingsService->all();

        return view('settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $data = $request->except('_token');

        // Checkbox values might be missing if unchecked, so we handle known booleans
        $booleans = ['allow_reopen', 'require_evidence_on_submit', 'lock_weight'];

        foreach ($booleans as $key) {
            $value = $request->has($key) ? 'true' : 'false';
            $this->settingsService->set($key, $value, 'boolean');
        }

        // Other settings like approval_mode
        if ($request->has('approval_mode')) {
            $this->settingsService->set('approval_mode', $request->input('approval_mode'), 'string');
        }

        return redirect()->back()->with('status', 'تنظیمات با موفقیت ذخیره شد.');
    }
}
