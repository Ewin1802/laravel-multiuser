<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Validation\Rule;

/**
 * Kirim pengumuman ke aplikasi kasir. Dipakai panel web superadmin
 * DAN aplikasi admin, jadi aturannya sama di kedua tempat.
 */
class AnnouncementService
{
    public function __construct(private FcmService $fcm) {}

    /**
     * @param  string  $target  Announcement::TARGET_ALL | TARGET_STORE
     */
    public function send(string $title, string $body, string $target, ?int $storeId, User $by): Announcement
    {
        $toStore = $target === Announcement::TARGET_STORE;
        $topic = $toStore ? FcmService::storeTopic((int) $storeId) : FcmService::TOPIC_ALL;

        $result = $this->fcm->sendToTopic($topic, $title, $body, ['type' => 'announcement']);

        return Announcement::create([
            'title' => $title,
            'body' => $body,
            'target' => $toStore ? Announcement::TARGET_STORE : Announcement::TARGET_ALL,
            'store_id' => $toStore ? $storeId : null,
            'sent_by' => $by->id,
            'status' => $result['ok'] ? 'sent' : 'failed',
            'message_id' => $result['id'] ?? null,
            'error' => $result['error'] ?? null,
        ]);
    }

    /** Aturan validasi yang sama untuk web & API. */
    public static function rules(?string $target): array
    {
        return [
            'title' => ['required', 'string', 'max:65'],
            'body' => ['required', 'string', 'max:240'],
            'target' => ['required', Rule::in([Announcement::TARGET_ALL, Announcement::TARGET_STORE])],
            'store_id' => [
                Rule::requiredIf($target === Announcement::TARGET_STORE),
                'nullable',
                'integer',
                Rule::exists('stores', 'id'),
            ],
        ];
    }

    public const ATTRIBUTES = ['title' => 'judul', 'body' => 'isi pesan', 'store_id' => 'toko'];
}
