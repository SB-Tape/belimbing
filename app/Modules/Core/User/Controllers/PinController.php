<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\User\Controllers;

use App\Base\Menu\Services\PinMetadataNormalizer;
use App\Modules\Core\User\Models\UserPin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manages the authenticated user's pinned sidebar items.
 *
 * Supports both menu item pins (from the sidebar menu) and page pins
 * (from individual pages like tool workspaces). Called from Alpine
 * components via fetch(). All endpoints return JSON and are protected
 * by the 'auth' middleware.
 */
class PinController
{
    public function __construct(
        private readonly PinMetadataNormalizer $pinMetadataNormalizer,
    ) {}

    /**
     * Toggle a pin for the current user.
     *
     * Pins are unique by navigation destination, not by source type. If a menu item
     * pin and a page pin resolve to the same normalized URL, they are treated as the
     * same pinned destination.
     */
    public function toggle(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['required', 'string', 'in:menu_item,page'],
            'pinnable_id' => ['required', 'string', 'max:150'],
            'label' => ['required', 'string', 'max:150'],
            'url' => ['required', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();
        $type = $request->input('type');
        $pinnableId = $request->input('pinnable_id');
        $normalizedUrl = $this->pinMetadataNormalizer->normalizeUrl(
            $request->input('url'),
        );

        $existing = UserPin::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->where('pinnable_id', $pinnableId)
            ->first();

        if ($existing) {
            $existing->delete();
            $user->unsetRelation('pins');

            return response()->json([
                'pinned' => false,
                'pins' => $user->getPins(),
            ]);
        }

        $existingByUrl = UserPin::query()
            ->where('user_id', $user->id)
            ->get()
            ->first(
                fn (
                    UserPin $pin,
                ): bool => $this->pinMetadataNormalizer->normalizeUrl(
                    $pin->url,
                ) === $normalizedUrl,
            );

        if ($existingByUrl !== null) {
            $existingByUrl
                ->fill([
                    'label' => $this->pinMetadataNormalizer->normalizeLabel(
                        $request->input('label'),
                    ),
                    'url' => $request->input('url'),
                    'icon' => $existingByUrl->icon ?? $request->input('icon'),
                ])
                ->save();

            $user->unsetRelation('pins');

            return response()->json([
                'pinned' => true,
                'pins' => $user->getPins(),
            ]);
        }

        $maxOrder =
            UserPin::query()->where('user_id', $user->id)->max('sort_order') ??
            -1;

        UserPin::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'pinnable_id' => $pinnableId,
            'label' => $this->pinMetadataNormalizer->normalizeLabel(
                $request->input('label'),
            ),
            'url' => $request->input('url'),
            'icon' => $request->input('icon'),
            'sort_order' => $maxOrder + 1,
        ]);

        $user->unsetRelation('pins');

        return response()->json([
            'pinned' => true,
            'pins' => $user->getPins(),
        ]);
    }

    /**
     * Reorder the current user's pinned items.
     *
     * Accepts an ordered array of pin references. Each item's sort_order
     * is updated to match its array index.
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'pins' => ['required', 'array', 'min:1'],
            'pins.*.type' => ['required', 'string', 'in:menu_item,page'],
            'pins.*.pinnable_id' => ['required', 'string', 'max:150'],
        ]);

        $user = $request->user();
        $pins = $request->input('pins');

        foreach ($pins as $index => $pin) {
            UserPin::query()
                ->where('user_id', $user->id)
                ->where('type', $pin['type'])
                ->where('pinnable_id', $pin['pinnable_id'])
                ->update(['sort_order' => $index]);
        }

        return response()->json([
            'pins' => $user->getPins(),
        ]);
    }
}
