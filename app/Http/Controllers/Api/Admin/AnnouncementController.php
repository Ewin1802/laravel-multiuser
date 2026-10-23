<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\AnnouncementService;
use App\Services\FcmService;
use Illuminate\Http\Request;

/** Pengumuman ke aplikasi kasir, dikirim dari aplikasi admin. */
class AnnouncementController extends Controller
{
    public function index()
    {
        $page = Announcement::with('store:id,name')->latest()->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => collect($page->items())->map(fn (Announcement $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'body' => $a->body,
                'target' => $a->target,
                'store' => $a->store ? ['id' => $a->store->id, 'name' => $a->store->name] : null,
                'status' => $a->status,
                'error' => $a->error,
                'sent_at' => $a->created_at?->toIso8601String(),
            ]),
            'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
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
            return response()->json([
                'status' => 'error',
                'code' => 'fcm_disabled',
                'message' => 'Push notification belum aktif di server.',
            ], 503);
        }

        $a = $announcements->send($data['title'], $data['body'], $data['target'], $data['store_id'] ?? null, $request->user());

        return response()->json([
            'status' => $a->status === 'sent' ? 'success' : 'error',
            'message' => $a->status === 'sent' ? 'Pengumuman terkirim.' : 'Gagal mengirim: ' . $a->error,
            'data' => ['id' => $a->id, 'status' => $a->status],
        ], $a->status === 'sent' ? 201 : 502);
    }
}
