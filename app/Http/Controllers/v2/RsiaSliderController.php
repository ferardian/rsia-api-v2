<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\RsiaSlider;
use App\Helpers\ApiResponse;
use App\Http\Resources\RealDataResource;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RsiaSliderController extends Controller
{
    public function index()
    {
        $sliders = RsiaSlider::where('status', 'active')
            ->orderBy('order')
            ->get();

        return RealDataResource::collection($sliders);
    }

    public function get()
    {
        $sliders = RsiaSlider::orderBy('order')->get();
        return RealDataResource::collection($sliders);
    }

    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:10240',
            'title' => 'nullable|string|max:100',
            'link'  => 'nullable|string|max:255',
            'order' => 'nullable|integer',
        ]);

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $name = time() . '_' . Str::slug($request->title ?? 'slider') . '.' . $image->getClientOriginalExtension();
            $destinationPath = public_path('/storage/slider');
            
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0777, true);
            }

            $image->move($destinationPath, $name);
            $imageUrl = url('/storage/slider/' . $name);
            
            if (!str_contains($imageUrl, 'localhost')) {
                $imageUrl = str_replace('http://', 'https://', $imageUrl);
            }

            $slider = RsiaSlider::create([
                'image'  => $imageUrl,
                'title'  => $request->title,
                'link'   => $request->link,
                'order'  => $request->order ?? 0,
                'status' => 'active',
            ]);

            return ApiResponse::successWithData($slider, 'Slider berhasil ditambahkan');
        }

        return ApiResponse::error('Gagal mengunggah gambar', 'image_upload_failed');
    }

    public function update(Request $request, $id)
    {
        $slider = RsiaSlider::find($id);
        if (!$slider) {
            return ApiResponse::notFound('Slider tidak ditemukan');
        }

        $request->validate([
            'image'  => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10240',
            'title'  => 'nullable|string|max:100',
            'link'   => 'nullable|string|max:255',
            'order'  => 'nullable|integer',
            'status' => 'nullable|in:active,inactive',
        ]);

        $data = $request->only(['title', 'link', 'order', 'status']);

        if ($request->hasFile('image')) {
            // Delete old image
            $oldPath = str_replace(url('/'), public_path(), $slider->image);
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }

            $image = $request->file('image');
            $name = time() . '_' . Str::slug($request->title ?? 'slider') . '.' . $image->getClientOriginalExtension();
            $destinationPath = public_path('/storage/slider');
            $image->move($destinationPath, $name);
            
            $imageUrl = url('/storage/slider/' . $name);
            if (!str_contains($imageUrl, 'localhost')) {
                $imageUrl = str_replace('http://', 'https://', $imageUrl);
            }

            $data['image'] = $imageUrl;
        }

        $slider->update($data);

        return ApiResponse::successWithData($slider, 'Slider berhasil diupdate');
    }

    public function destroy($id)
    {
        $slider = RsiaSlider::find($id);
        if (!$slider) {
            return ApiResponse::notFound('Slider tidak ditemukan');
        }

        // Delete image
        $oldPath = str_replace(url('/'), public_path(), $slider->image);
        if (file_exists($oldPath)) {
            @unlink($oldPath);
        }

        $slider->delete();

        return ApiResponse::success('Slider berhasil dihapus');
    }

    public function updateStatus(Request $request, $id)
    {
        $slider = RsiaSlider::find($id);
        if (!$slider) {
            return ApiResponse::notFound('Slider tidak ditemukan');
        }

        $request->validate([
            'status' => 'required|in:active,inactive',
        ]);

        $slider->update(['status' => $request->status]);

        return ApiResponse::successWithData($slider, 'Status slider berhasil diupdate');
    }
}
