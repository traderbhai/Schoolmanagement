<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = $this->loadSettings();
        $phpVersion = phpversion();
        $laravelVersion = app()->version();
        return view('admin.settings.index', compact('settings', 'phpVersion', 'laravelVersion'));
    }

    public function branding()
    {
        $settings = $this->loadSettings();
        return view('admin.settings.branding', compact('settings'));
    }

    public function update(Request $r)
    {
        $r->validate([
            'institute_name' => 'required|string|max:191',
            'short_name' => 'nullable|string|max:50',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'website' => 'nullable|url',
        ]);

        $settings = [
            'institute_name' => $r->institute_name,
            'short_name' => $r->short_name,
            'address' => $r->address,
            'phone' => $r->phone,
            'email' => $r->email,
            'website' => $r->website,
            'primary_color' => $r->primary_color ?? '#4f46e5',
        ];

        file_put_contents(storage_path('app/settings.json'), json_encode($settings, JSON_PRETTY_PRINT));
        return back()->with('success', 'Settings saved successfully.');
    }

    private function loadSettings(): array
    {
        $path = storage_path('app/settings.json');
        if (!file_exists($path)) return [
            'institute_name' => 'EduManage Institute',
            'short_name' => 'EduManage',
            'address' => '',
            'phone' => '',
            'email' => '',
            'website' => '',
            'primary_color' => '#4f46e5',
        ];
        return json_decode(file_get_contents($path), true) ?? [];
    }
}
