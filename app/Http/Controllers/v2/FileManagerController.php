<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\RsiaFileManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileManagerController extends Controller
{
    /**
     * Get all files
     */
    public function index(Request $request)
    {
        $query = RsiaFileManager::query();

        // Search by nama_file
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('nama_file', 'like', "%{$search}%");
        }

        $limit = $request->get('limit', 100);
        $files = $query->orderBy('created_at', 'desc')->limit($limit)->get();

        return response()->json([
            'data' => $files
        ]);
    }

    /**
     * Upload new file
     */
    public function store(Request $request)
    {
        $request->validate([
            'nama_file' => 'required',
            'file' => 'required|file|max:10240' // Max 10MB
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filename = time() . '_' . $file->getClientOriginalName();
            
            // Store in storage/app/public/documents
            $file->storeAs('public/documents', $filename);

            $fileManager = RsiaFileManager::create([
                'nama_file' => $request->nama_file,
                'file' => $filename
            ]);

            return response()->json([
                'data' => $fileManager
            ], 201);
        }

        return response()->json(['message' => 'No file uploaded'], 400);
    }

    /**
     * Update file
     */
    public function update(Request $request, $id)
    {
        $fileManager = RsiaFileManager::find($id);

        if (!$fileManager) {
            return response()->json(['message' => 'File not found'], 404);
        }

        $request->validate([
            'nama_file' => 'required'
        ]);

        // If new file uploaded, replace old file
        if ($request->hasFile('file')) {
            // Delete old file
            Storage::delete('public/documents/' . $fileManager->file);
            
            $file = $request->file('file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->storeAs('public/documents', $filename);
            
            $fileManager->file = $filename;
        }

        $fileManager->nama_file = $request->nama_file;
        $fileManager->save();

        return response()->json([
            'data' => $fileManager
        ]);
    }

    /**
     * Delete file (soft delete)
     */
    public function destroy($id)
    {
        $fileManager = RsiaFileManager::find($id);

        if (!$fileManager) {
            return response()->json(['message' => 'File not found'], 404);
        }

        $fileManager->delete();

        return response()->json(['message' => 'File deleted successfully']);
    }

    /**
     * Download file
     */
    public function download($id)
    {
        $fileManager = RsiaFileManager::find($id);

        if (!$fileManager) {
            return response()->json(['message' => 'File not found'], 404);
        }

        // 1. Check local storage first
        $filePath = storage_path('app/public/documents/' . $fileManager->file);

        if (file_exists($filePath)) {
            return response()->download($filePath, $fileManager->file);
        }

        // 2. Fallback to legacy server
        $legacyUrl = 'http://192.168.100.33/rsiap/file/berkas/' . $fileManager->file;
        
        try {
            // Check if file exists on remote server
            $headers = get_headers($legacyUrl);
            if (strpos($headers[0], '200') !== false) {
                 // Stream download from remote URL
                 return response()->streamDownload(function () use ($legacyUrl) {
                     echo file_get_contents($legacyUrl);
                 }, $fileManager->file);
            }
        } catch (\Exception $e) {
            // Ignore error and fall through
        }

        return response()->json(['message' => 'File not found on server (Local & Legacy)'], 404);
    }
}
