<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\RsiaArticle;
use App\Helpers\ApiResponse;
use App\Http\Resources\RealDataResource;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RsiaArticleController extends Controller
{
    public function index()
    {
        $articles = RsiaArticle::where('status', 'active')
            ->orderBy('order')
            ->get();

        return RealDataResource::collection($articles);
    }

    public function get()
    {
        $articles = RsiaArticle::orderBy('order')->get();
        return RealDataResource::collection($articles);
    }

    public function store(Request $request)
    {
        $request->validate([
            'image'    => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:10240',
            'title'    => 'required|string|max:100',
            'content'  => 'nullable|string',
            'category' => 'nullable|string|max:50',
            'order'    => 'nullable|integer',
        ]);

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $name = time() . '_' . Str::slug($request->title) . '.' . $image->getClientOriginalExtension();
            $destinationPath = public_path('/storage/article');
            
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0777, true);
            }

            $image->move($destinationPath, $name);
            $imagePath = 'article/' . $name;

            $article = RsiaArticle::create([
                'image'    => $imagePath,
                'title'    => $request->title,
                'content'  => $request->content,
                'category' => $request->category,
                'order'    => $request->order ?? 0,
                'status'   => 'active',
            ]);

            return ApiResponse::successWithData($article, 'Artikel berhasil ditambahkan');
        }

        return ApiResponse::error('Gagal mengunggah gambar', 'image_upload_failed');
    }

    public function update(Request $request, $id)
    {
        $article = RsiaArticle::find($id);
        if (!$article) {
            return ApiResponse::notFound('Artikel tidak ditemukan');
        }

        $request->validate([
            'image'    => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10240',
            'title'    => 'required|string|max:100',
            'content'  => 'nullable|string',
            'category' => 'nullable|string|max:50',
            'order'    => 'nullable|integer',
            'status'   => 'nullable|in:active,inactive',
        ]);

        $data = $request->only(['title', 'content', 'category', 'order', 'status']);

        if ($request->hasFile('image')) {
            // Delete old image using raw value from database
            $oldImage = $article->getRawOriginal('image');
            if ($oldImage) {
                // If it's a legacy full URL, try to extract path
                if (str_starts_with($oldImage, 'http')) {
                    $oldPath = str_replace(url('/'), public_path(), $oldImage);
                } else {
                    $oldPath = public_path('storage/' . $oldImage);
                }

                if (file_exists($oldPath) && !empty($oldImage)) {
                    @unlink($oldPath);
                }
            }

            $image = $request->file('image');
            $name = time() . '_' . Str::slug($request->title) . '.' . $image->getClientOriginalExtension();
            $destinationPath = public_path('/storage/article');
            $image->move($destinationPath, $name);
            
            $data['image'] = 'article/' . $name;
        }

        $article->update($data);

        return ApiResponse::successWithData($article, 'Artikel berhasil diupdate');
    }

    public function destroy($id)
    {
        $article = RsiaArticle::find($id);
        if (!$article) {
            return ApiResponse::notFound('Artikel tidak ditemukan');
        }

        // Delete image using raw value from database
        $oldImage = $article->getRawOriginal('image');
        if ($oldImage) {
            // If it's a legacy full URL, try to extract path
            if (str_starts_with($oldImage, 'http')) {
                $oldPath = str_replace(url('/'), public_path(), $oldImage);
            } else {
                $oldPath = public_path('storage/' . $oldImage);
            }

            if (file_exists($oldPath) && !empty($oldImage)) {
                @unlink($oldPath);
            }
        }

        $article->delete();

        return ApiResponse::success('Artikel berhasil dihapus');
    }

    public function updateStatus(Request $request, $id)
    {
        $article = RsiaArticle::find($id);
        if (!$article) {
            return ApiResponse::notFound('Artikel tidak ditemukan');
        }

        $request->validate([
            'status' => 'required|in:active,inactive',
        ]);

        $article->update(['status' => $request->status]);

        return ApiResponse::successWithData($article, 'Status artikel berhasil diupdate');
    }
}
