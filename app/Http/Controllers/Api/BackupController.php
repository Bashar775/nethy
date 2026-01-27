<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Response;
use Symfony\Component\Process\Process;
use ZipArchive;

class BackupController extends Controller
{
    use AuthorizesRequests;
    // List backups (authenticated users only)
    public function index(Request $request)
    {
        $this->authorize('backup',User::class);
        $backups = $this->getBackupList();
        
        return response()->json([
            'data' => $backups
        ]);
    }
    
    // Download backup (with full security checks)
    public function download(Request $request,$filename)
    {
        $this->authorize('backup', User::class);

        // 1. Validate filename (prevent directory traversal)
        if (!preg_match('/^[a-zA-Z0-9\-\.]+$/', $filename)) {
            abort(400, 'Invalid filename');
        }
        
        // 2. Check if file exists
        $filepath = config('app.name', 'Laravel') . '/' . $filename;
        
        if (!Storage::disk('backups')->exists($filepath)) {
            return response()->json(['message' => 'File not found'], 404);
        }
        $fullPath = Storage::disk('backups')->path($filepath);
        
        // 3. Log the download
        Log::info('Download', [
            'user' => $request->user()->id,
            'file' => $filename
        ]);

        // 4. Stream the file
        return response()->download($fullPath, $filename, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
    
    
    
    // Private helper
    private function getBackupList()
    {
        $appName = config('app.name', 'Laravel');
        $files = Storage::disk('backups')->files($appName);
        
        $backups = [];
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'zip') {
                $filename = basename($file);
                
                $backups[] = [
                    'id' => md5($file), // Unique ID without exposing path
                    'name' => $filename,
                    'size' => $this->formatBytes(Storage::disk('backups')->size($file)),
                    'created_at' => date('c', Storage::disk('backups')->lastModified($file)),
                    'links' => [
                        'download' => url("/api/downloadbackup/{$filename}")
                    ],
                ];
            }
        }
        
        // Sort by newest first
        usort($backups, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
        
        return [
            'app' => $appName,
            'count' => count($backups),
            'backups' => $backups,
        ];
    }
    
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    public function downloadI(Request $request)
    {
        $this->authorize('backup', User::class);
        // Simple rate limiting: 3 downloads per hour per user
        $key = 'backup-dl-' . $request->user()->id;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json([
                'error' => 'Too many backup requests. Try again in an hour.'
            ], 429);
        }
        RateLimiter::hit($key, 3600);

        // Generate filename
        $filename = 'backup-' . date('Y-m-d-H-i-s') . '.zip';

        // Stream the backup directly to response
        return Response::streamDownload(
            function () {
                $this->createBackupStream();
            },
            $filename,
            [
                'Content-Type' => 'application/zip',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }

    /**
     * Creates backup and streams it directly
     */
    private function createBackupStream()
    {
        // Create temporary ZIP in memory
        $zip = new ZipArchive;
        $tempZip = tempnam(sys_get_temp_dir(), 'backup_') . '.zip';

        if ($zip->open($tempZip, ZipArchive::CREATE) === TRUE) {
            // Add database dump
            $this->addDatabaseToZip($zip);
            
            // Add metadata
            $zip->addFromString('readme.txt', 
                "Backup created: " . date('Y-m-d H:i:s') . "\n" .
                "Type: Instant download (not stored on server)\n" .
                "Note: This file was generated on-demand and not saved."
            );
            
            $zip->close();
            
            // Stream to output
            readfile($tempZip);
            
            // Cleanup
            unlink($tempZip);
        }
    }

    /**
     * Dumps PostgreSQL database directly into ZIP
     */
    private function addDatabaseToZip(ZipArchive $zip)
    {
        $config = config('database.connections.pgsql');
        $dumpPath = $config['dump']['dump_binary_path'] ?? '';

        $process = new Process([
            $dumpPath . 'pg_dump.exe',
            '-h', $config['host'],
            '-p', $config['port'],
            '-U', $config['username'],
            '-d', $config['database'],
            '--no-owner',
            '--no-acl',
        ]);

        $process->setEnv(['PGPASSWORD' => $config['password']]);
        $process->setTimeout(120); // 2 minutes max
        $process->run();

        if ($process->isSuccessful()) {
            $zip->addFromString('database.sql', $process->getOutput());
        } else {
            $zip->addFromString('error.txt', 'Database dump failed: ' . $process->getErrorOutput());
        }
    }
}

