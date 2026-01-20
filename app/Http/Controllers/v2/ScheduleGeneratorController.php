<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Services\ScheduleGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ScheduleGeneratorController extends Controller
{
    protected $scheduleGenerator;

    public function __construct(ScheduleGeneratorService $scheduleGenerator)
    {
        $this->scheduleGenerator = $scheduleGenerator;
    }

    /**
     * Generate automatic schedule for a department
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'department' => 'required|string',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $schedule = $this->scheduleGenerator->generate(
                $request->department,
                $request->month,
                $request->year
            );

            return response()->json([
                'success' => true,
                'data' => $schedule,
                'message' => 'Jadwal berhasil digenerate'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
