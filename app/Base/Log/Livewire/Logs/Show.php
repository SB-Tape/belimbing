<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Base\Log\Livewire\Logs;

use Illuminate\Support\Facades\File;
use Livewire\Attributes\Url;
use Livewire\Component;

class Show extends Component
{
    public string $filename = '';

    #[Url]
    public int $tail = 100;

    #[Url]
    public string $search = '';

    #[Url]
    public bool $showAll = false;

    public int $deleteLines = 10;

    public function mount(string $filename): void
    {
        $this->filename = basename($filename);
        $this->ensureFileExists();
    }

    /**
     * Refresh the log view (re-renders with latest content).
     */
    public function refresh(): void
    {
        // Livewire re-renders automatically; this is an explicit action target.
    }

    /**
     * Delete a number of lines from the top of the log file.
     */
    public function deleteLinesFromTop(): void
    {
        $deleteLines = $this->normalizedDeleteLines();

        $path = $this->resolvedPath();
        if ($path === null) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        $remaining = array_slice($lines, $deleteLines);
        File::put($path, implode("\n", $remaining).($remaining ? "\n" : ''));
        $this->deleteLines = 10;
    }

    /**
     * Delete the entire log file and redirect back to the index.
     */
    public function deleteFile(): void
    {
        $path = $this->resolvedPath();
        if ($path !== null) {
            File::delete($path);
        }

        $this->redirect(route('admin.system.logs.index'), navigate: true);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        $lines = [];
        $totalLines = 0;
        $fileSize = 0;

        $path = $this->resolvedPath();

        if ($path !== null && File::exists($path)) {
            $fileSize = File::size($path);
            $allLines = file($path, FILE_IGNORE_NEW_LINES);
            $totalLines = count($allLines);

            // Apply tail or show all
            if (! $this->showAll && $this->tail > 0) {
                $startIndex = max(0, $totalLines - $this->tail);
                $sliced = array_slice($allLines, $startIndex, null, true);
            } else {
                $sliced = $allLines;
                $startIndex = 0;
            }

            // Build numbered lines with search filtering.
            foreach ($sliced as $index => $line) {
                $lineNumber = $index + 1;

                if ($this->search !== '' && stripos($line, $this->search) === false) {
                    continue;
                }

                $lines[] = [
                    'number' => $lineNumber,
                    'content' => $line,
                ];
            }
        }

        return view('livewire.admin.system.logs.show', [
            'lines' => $lines,
            'totalLines' => $totalLines,
            'fileSize' => $fileSize,
            'displayedCount' => count($lines),
        ]);
    }

    /**
     * Resolve and validate the log file path.
     */
    private function resolvedPath(): ?string
    {
        $logPath = storage_path('logs');
        $path = $logPath.DIRECTORY_SEPARATOR.$this->filename;

        if (! File::exists($path) || ! str_starts_with(realpath($path), realpath($logPath))) {
            return null;
        }

        return $path;
    }

    /**
     * Ensure the requested file exists within the logs directory.
     */
    private function ensureFileExists(): void
    {
        if ($this->resolvedPath() === null) {
            abort(404);
        }
    }

    /**
     * Normalize requested delete lines count.
     *
     * Treat zero/negative values as the default 10 lines.
     */
    private function normalizedDeleteLines(): int
    {
        if ($this->deleteLines < 1) {
            return 10;
        }

        return $this->deleteLines;
    }
}
