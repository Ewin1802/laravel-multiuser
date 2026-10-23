<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\PushToken;
use App\Models\Store;
use App\Services\AnnouncementService;
use App\Services\FcmService;
use Illuminate\Http\Request;

/** Superadmin (web) mengirim push notification ke semua pengguna atau satu toko. */
class AnnouncementController extends Controller
{
    public function index(FcmService $fcm)
    {
        $kasir = PushToken::where('app', PushToken::APP_KASIR);

        return view('pages.superadmin.announcements.index', [
            'announcements' => Announcement::with(['store:id,name', 'sender:id,name'])->latest()->paginate(15),
            'stores' => Store::where('status', Store::STATUS_ACTIVE)->orderBy('name')->get(['id', 'name']),
            'fcmReady' => $fcm->enabled(),
            'deviceCount' => (clone $kasir)->count(),
            'storeReach' => (clone $kasir)->whereNotNull('store_id')->distinct()->count('store_id'),
        ]);
    }

    public function store(Request $request, FcmService $fcm, AnnouncementService $announcements)
    {
        $data = $request->validate(
            AnnouncementService::rules($request->input('target')),
            [],
            AnnouncementService::ATTRIBUTES
        );

        if (! $fcm->enabled()) {
            return back()->withInput()->withErrors([
                'title' => 'Push notification belum aktif: file service account Firebase belum dipasang di server.',
            ]);
        }

        $a = $announcements->send($data['title'], $data['body'], $data['target'], $data['store_id'] ?? null, $request->user());

        return $a->status === 'sent'
            ? redirect()->route('superadmin.announcements.index')->with('success', 'Pengumuman terkirim.')
            : back()->withInput()->withErrors(['title' => 'Gagal mengirim: ' . ($a->error ?? 'tidak diketahui')]);
    }
}
