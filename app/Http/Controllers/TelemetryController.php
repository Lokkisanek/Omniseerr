<?php

namespace App\Http\Controllers;

use App\Services\QbitService;
use App\Services\TelemetryAggregator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class TelemetryController extends Controller
{
    public function queue(TelemetryAggregator $aggregator): Response
    {
        $queue = Cache::get('omniseerr.live_queue');

        if (! is_array($queue)) {
            try {
                $queue = $aggregator->getUnifiedQueue();
            } catch (Throwable) {
                $queue = [];
            }
        }

        return Inertia::render('Activity/Queue', [
            'initialQueue' => $queue,
        ]);
    }

    public function snapshot(TelemetryAggregator $aggregator): JsonResponse
    {
        try {
            $queue = $aggregator->getUnifiedQueue();
            Cache::put('omniseerr.live_queue', $queue, 30);

            return response()->json(['queue' => $queue]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage(), 'queue' => []], 422);
        }
    }

    public function presence(Request $request): JsonResponse
    {
        $delta = (int) $request->input('delta', 0);
        $count = max(0, (int) Cache::get('omniseerr.ws_clients', 0) + $delta);
        Cache::put('omniseerr.ws_clients', $count, now()->addHours(6));

        return response()->json(['clients' => $count]);
    }

    public function control(Request $request, QbitService $qbit): JsonResponse
    {
        $data = $request->validate([
            'hash' => 'required|string',
            'action' => 'required|in:pause,resume,stop,start,recheck,delete,deletePerm',
        ]);

        try {
            $qbit->controlTorrent($data['hash'], $data['action']);

            return response()->json(['ok' => true]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
