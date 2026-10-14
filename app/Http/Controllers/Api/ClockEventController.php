<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClockTamperLog;
use Illuminate\Http\Request;

/**
 * POST /api/clock-events
 *
 * Aplikasi mengirim catatan kejadian jam HP mundur / melompat / lewat
 * batas offline yang terkumpul selama offline. Idempotent lewat client_uuid.
 */
class ClockEventController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'events' => ['required', 'array', 'max:200'],
            'events.*.client_uuid' => ['required', 'uuid'],
            'events.*.kind' => ['required', 'string', 'in:' . implode(',', array_keys(ClockTamperLog::KINDS))],
            'events.*.device_time' => ['nullable', 'string', 'max:40'],
            'events.*.trusted_time' => ['nullable', 'string', 'max:40'],
            'events.*.drift_seconds' => ['nullable', 'integer'],
            'events.*.details' => ['nullable', 'array'],
        ]);

        $device = $request->attributes->get('store_device');
        $saved = 0;

        foreach ($data['events'] as $event) {
            $log = ClockTamperLog::withoutGlobalScope('store')
                ->firstOrNew(['client_uuid' => $event['client_uuid']]);

            if ($log->exists) {
                continue; // sudah pernah diterima
            }

            $log->fill([
                'store_device_id' => $device->id,
                'device_id' => $device->device_id,
                'kind' => $event['kind'],
                'device_time' => $event['device_time'] ?? null,
                'trusted_time' => $event['trusted_time'] ?? null,
                'drift_seconds' => $event['drift_seconds'] ?? null,
                'details' => $event['details'] ?? null,
                'reported_at' => now(),
            ]);
            $log->save();
            $saved++;
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'received' => count($data['events']),
                'saved' => $saved,
                'client_uuids' => array_column($data['events'], 'client_uuid'),
            ],
        ]);
    }
}
