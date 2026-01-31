<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\RsiaFacility;
use App\Helpers\ApiResponse;
use App\Http\Resources\RealDataResource;
use Illuminate\Http\Request;

class RsiaFacilityController extends Controller
{
    public function index()
    {
        $facilities = RsiaFacility::where('status', 'active')
            ->orderBy('order')
            ->get();

        return RealDataResource::collection($facilities);
    }

    public function get()
    {
        $facilities = RsiaFacility::orderBy('order')->get();
        return RealDataResource::collection($facilities);
    }

    public function store(Request $request)
    {
        $request->validate([
            'icon'        => 'required|string|max:100',
            'title'       => 'required|string|max:100',
            'description' => 'nullable|string',
            'order'       => 'nullable|integer',
        ]);

        $facility = RsiaFacility::create([
            'icon'        => $request->icon,
            'title'       => $request->title,
            'description' => $request->description,
            'order'       => $request->order ?? 0,
            'status'      => 'active',
        ]);

        return ApiResponse::successWithData($facility, 'Fasilitas berhasil ditambahkan');
    }

    public function update(Request $request, $id)
    {
        $facility = RsiaFacility::find($id);
        if (!$facility) {
            return ApiResponse::notFound('Fasilitas tidak ditemukan');
        }

        $request->validate([
            'icon'        => 'nullable|string|max:100',
            'title'       => 'required|string|max:100',
            'description' => 'nullable|string',
            'order'       => 'nullable|integer',
            'status'      => 'nullable|in:active,inactive',
        ]);

        $facility->update($request->only(['icon', 'title', 'description', 'order', 'status']));

        return ApiResponse::successWithData($facility, 'Fasilitas berhasil diupdate');
    }

    public function destroy($id)
    {
        $facility = RsiaFacility::find($id);
        if (!$facility) {
            return ApiResponse::notFound('Fasilitas tidak ditemukan');
        }

        $facility->delete();

        return ApiResponse::success('Fasilitas berhasil dihapus');
    }

    public function updateStatus(Request $request, $id)
    {
        $facility = RsiaFacility::find($id);
        if (!$facility) {
            return ApiResponse::notFound('Fasilitas tidak ditemukan');
        }

        $request->validate([
            'status' => 'required|in:active,inactive',
        ]);

        $facility->update(['status' => $request->status]);

        return ApiResponse::successWithData($facility, 'Status fasilitas berhasil diupdate');
    }
}
