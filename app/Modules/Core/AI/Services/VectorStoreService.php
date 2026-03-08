<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\AI\Services;

/**
 * Per-DW vector store backed by SQLite with sqlite-vec extension.
 *
 * Manages a SQLite database per Digital Worker for storing document
 * embeddings and performing KNN vector search. Falls back to keyword
 * search when embeddings are not available.
 *
 * Requires the sqlite-vec loadable extension (vec0.so) to be installed
 * and configured via `ai.tools.memory_search.sqlite_vec_extension_dir`.
 *
 * @see https://github.com/asg017/sqlite-vec
 */
class VectorStoreService
{
    /**
     * Check whether the sqlite-vec extension is available and loadable.
     */
    public static function isSqliteVecAvailable(): bool
    {
        $extensionDir = config('ai.tools.memory_search.sqlite_vec_extension_dir');

        if ($extensionDir === null || $extensionDir === '') {
            $extensionDir = storage_path('app/sqlite-ext');
        }

        $extensionFile = config('ai.tools.memory_search.sqlite_vec_extension', 'vec0');
        $fullPath = $extensionDir.'/'.$extensionFile.'.so';

        return file_exists($fullPath);
    }

    /**
     * Get the path to the vector database for a Digital Worker.
     *
     * @param  int  $employeeId  The Digital Worker's employee ID
     */
    public static function databasePath(int $employeeId): string
    {
        return config('ai.workspace_path').'/'.$employeeId.'/vectors.sqlite';
    }
}
