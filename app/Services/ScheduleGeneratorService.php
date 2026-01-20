<?php

namespace App\Services;

use App\Models\RsiaUnitShiftRule;
use App\Models\Pegawai;
use App\Models\JamMasuk;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ScheduleGeneratorService
{
    const TARGET_HOURS_PER_MONTH = 173;
    const TOLERANCE = 10; // ±10 hours is acceptable

    /**
     * Generate schedule for a department using capacity-based allocation
     */
    public function generate($department, $month, $year)
    {
        // 1. Load shift rules for this department
        $shiftRules = RsiaUnitShiftRule::with('departemen')
            ->where('dep_id', $department)
            ->orderBy('priority')
            ->get();

        if ($shiftRules->isEmpty()) {
            throw new \Exception("Tidak ada aturan shift untuk departemen ini. Silakan tambahkan di menu Unit Shift Rules.");
        }

        // 2. Load employees in this department
        $employees = Pegawai::where('departemen', $department)
            ->where('stts_aktif', 'AKTIF')
            ->get();

        if ($employees->isEmpty()) {
            throw new \Exception("Tidak ada pegawai aktif di departemen ini.");
        }

        // 3. Separate by role (check jbtn field for coordinator/admin)
        $admins = $employees->filter(function($emp) {
            $isAdmin = stripos($emp->jbtn, 'Koordinator') !== false ||
                       stripos($emp->jbtn, 'Kepala') !== false || 
                       stripos($emp->jbtn, 'Admin') !== false ||
                       stripos($emp->jbtn, 'Karu') !== false;
            
            if ($isAdmin) {
                \Log::info("Detected admin/coordinator: {$emp->nama} (jbtn: {$emp->jbtn})");
            }
            
            return $isAdmin;
        });

        $nurses = $employees->reject(function($emp) use ($admins) {
            return $admins->contains('id', $emp->id);
        });

        \Log::info("Total employees: {$employees->count()}, Admins: {$admins->count()}, Nurses: {$nurses->count()}");

        // 4. Get days in month
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;

        // 5. Generate schedules using capacity-based allocation
        $schedule = [];

        // Generate for nurses using new capacity-based method
        $nurseSchedules = $this->generateCapacityBasedSchedule(
            $nurses,
            $shiftRules,
            $daysInMonth,
            $month,
            $year
        );

        foreach ($nurseSchedules as $empId => $empSchedule) {
            $schedule[$empId] = $empSchedule;
        }

        // Generate for admins/coordinators
        foreach ($admins as $admin) {
            $schedule[$admin->id] = $this->generateAdminSchedule(
                $admin,
                $shiftRules,
                $daysInMonth,
                $month,
                $year
            );
        }

        return array_values($schedule);
    }

    /**
     * Generate capacity-based schedule ensuring max 4-5 employees per shift per day
     */
    private function generateCapacityBasedSchedule($nurses, $shiftRules, $days, $month, $year)
    {
        $nurseShifts = $shiftRules->where('role_type', 'perawat')->sortBy('priority');
        
        if ($nurseShifts->isEmpty()) {
            throw new \Exception("Tidak ada shift untuk perawat di departemen ini.");
        }

        // Initialize schedules for all nurses
        $schedules = [];
        foreach ($nurses as $nurse) {
            $schedules[$nurse->id] = [
                'id' => $nurse->id,
                'total_hours' => 0,
                'consecutive_work_days' => 0,
                'consecutive_night_shifts' => 0,
                'consecutive_same_shift' => 0,
                'days_since_night' => 0,
                'last_shift' => null
            ];
            
            // Initialize all days as empty
            for ($day = 1; $day <= $days; $day++) {
                $schedules[$nurse->id]["h$day"] = '-';
            }
        }

        // Define shift capacity (max employees per shift per day)
        $shiftCapacity = [
            'Pagi' => 5,
            'Siang' => 5,
            'Malam' => 5
        ];

        // Assign shifts day by day
        for ($day = 1; $day <= $days; $day++) {
            // Get available nurses for today
            $availableNurses = [];
            foreach ($nurses as $nurse) {
                $nurseId = $nurse->id;
                $consecutiveDays = $schedules[$nurseId]['consecutive_work_days'];
                $consecutiveNights = $schedules[$nurseId]['consecutive_night_shifts'];
                $daysSinceNight = $schedules[$nurseId]['days_since_night'];
                
                // Need rest if:
                // - Worked 5+ consecutive days, OR
                // - Just finished 2 consecutive night shifts (need 2 days rest)
                $needsRest = $consecutiveDays >= 5 || 
                            ($consecutiveNights >= 2 && $daysSinceNight < 2);
                
                if (!$needsRest) {
                    $availableNurses[] = $nurse;
                }
            }

            // Shuffle to randomize assignment
            shuffle($availableNurses);

            // Assign shifts to available nurses
            foreach ($nurseShifts as $shift) {
                $shiftCode = $shift->shift_code;
                
                // Determine base shift type
                $baseType = 'Pagi';
                if (stripos($shiftCode, 'Siang') !== false) $baseType = 'Siang';
                if (stripos($shiftCode, 'Malam') !== false) $baseType = 'Malam';
                
                $capacity = $shiftCapacity[$baseType];
                
                // Separate nurses into priority groups
                $priorityNurses = [];    // Had this shift yesterday (max 2 days)
                $otherNurses = [];       // Available for this shift
                $avoidNurses = [];       // Had this shift 3+ days already
                
                foreach ($availableNurses as $nurse) {
                    $nurseId = $nurse->id;
                    
                    // Skip if already assigned today
                    if ($schedules[$nurseId]["h$day"] !== '-') continue;
                    
                    $consecutiveSame = $schedules[$nurseId]['consecutive_same_shift'];
                    $lastShift = $schedules[$nurseId]['last_shift'];
                    
                    // Check if had same shift yesterday
                    if ($day > 1 && $lastShift === $shiftCode) {
                        // If already had this shift 3+ days, avoid giving it again
                        if ($consecutiveSame >= 3) {
                            $avoidNurses[] = $nurse;
                        } else {
                            $priorityNurses[] = $nurse;
                        }
                    } else {
                        $otherNurses[] = $nurse;
                    }
                }
                
                // Assign to priority nurses first (max 2 days same shift), then others, avoid last
                $candidateNurses = array_merge($priorityNurses, $otherNurses, $avoidNurses);
                $assigned = 0;
                
                foreach ($candidateNurses as $nurse) {
                    if ($assigned >= $capacity) break;
                    
                    $nurseId = $nurse->id;
                    
                    // Skip if already assigned today
                    if ($schedules[$nurseId]["h$day"] !== '-') continue;
                    
                    // Assign this shift
                    $schedules[$nurseId]["h$day"] = $shiftCode;
                    $schedules[$nurseId]['total_hours'] += $shift->duration_hours;
                    $schedules[$nurseId]['consecutive_work_days']++;
                    
                    // Track consecutive same shift
                    if ($schedules[$nurseId]['last_shift'] === $shiftCode) {
                        $schedules[$nurseId]['consecutive_same_shift']++;
                    } else {
                        $schedules[$nurseId]['consecutive_same_shift'] = 1;
                    }
                    
                    $schedules[$nurseId]['last_shift'] = $shiftCode;
                    
                    // Track consecutive night shifts
                    if ($baseType === 'Malam') {
                        $schedules[$nurseId]['consecutive_night_shifts']++;
                        $schedules[$nurseId]['days_since_night'] = 0;
                    } else {
                        $schedules[$nurseId]['consecutive_night_shifts'] = 0;
                    }
                    
                    $assigned++;
                }
            }

            // Reset counters for nurses who got rest today
            foreach ($nurses as $nurse) {
                $nurseId = $nurse->id;
                if ($schedules[$nurseId]["h$day"] === '-') {
                    $schedules[$nurseId]['consecutive_work_days'] = 0;
                    $schedules[$nurseId]['consecutive_same_shift'] = 0;
                    $schedules[$nurseId]['last_shift'] = null;
                    
                    // Increment days since night shift (for 2-day rest tracking)
                    if ($schedules[$nurseId]['consecutive_night_shifts'] > 0) {
                        $schedules[$nurseId]['days_since_night']++;
                    }
                    
                    // Reset night shift counter after 2 rest days
                    if ($schedules[$nurseId]['days_since_night'] >= 2) {
                        $schedules[$nurseId]['consecutive_night_shifts'] = 0;
                        $schedules[$nurseId]['days_since_night'] = 0;
                    }
                }
            }
        }

        // Calculate variance
        foreach ($schedules as $nurseId => &$schedule) {
            $schedule['variance'] = round($schedule['total_hours'] - self::TARGET_HOURS_PER_MONTH, 1);
            $schedule['total_hours'] = round($schedule['total_hours'], 1);
            
            // Remove temporary tracking fields
            unset($schedule['consecutive_work_days']);
            unset($schedule['consecutive_night_shifts']);
            unset($schedule['consecutive_same_shift']);
            unset($schedule['days_since_night']);
            unset($schedule['last_shift']);
        }

        return $schedules;
    }


    /**
     * Generate rotation schedule for nurses
     * Creates balanced patterns with max 5 consecutive work days
     */
    private function generateNurseRotation($nurse, $shiftRules, $days, $offset)
    {
        // Build rotation pattern from shift rules (perawat only)
        $nurseShifts = $shiftRules->where('role_type', 'perawat')->sortBy('priority');
        
        if ($nurseShifts->isEmpty()) {
            throw new \Exception("Tidak ada shift untuk perawat di departemen ini.");
        }

        // Create 7-day cycle pattern: max 5 work days, then 2 rest days
        // Pattern: 2x Pagi, 2x Siang, 1x Malam, 1x Libur, 1x Malam, 1x Libur
        $pattern = [];
        
        foreach ($nurseShifts as $shift) {
            $code = $shift->shift_code;
            
            // Build pattern based on shift type
            if (stripos($code, 'Pagi') !== false) {
                $pattern[] = $code;
                $pattern[] = $code;
            } elseif (stripos($code, 'Siang') !== false) {
                $pattern[] = $code;
                $pattern[] = $code;
            } elseif (stripos($code, 'Malam') !== false) {
                $pattern[] = $code;
            }
        }
        
        // Add rest day after 5 work days
        $pattern[] = '-';
        
        // Add one more night shift
        $malamShift = $nurseShifts->first(function($s) {
            return stripos($s->shift_code, 'Malam') !== false;
        });
        if ($malamShift) {
            $pattern[] = $malamShift->shift_code;
        }
        
        // Final rest day
        $pattern[] = '-';

        $rotation = ['id' => $nurse->id];
        $totalHours = 0;

        for ($day = 1; $day <= $days; $day++) {
            // Use offset to stagger employees across different phases
            $patternIndex = ($day + ($offset * 2) - 1) % count($pattern);
            $shiftCode = $pattern[$patternIndex];
            
            $rotation["h$day"] = $shiftCode;
            
            // Calculate hours
            if ($shiftCode !== '-') {
                $shiftRule = $shiftRules->firstWhere('shift_code', $shiftCode);
                $totalHours += $shiftRule ? $shiftRule->duration_hours : 7;
            }
        }

        // Add metadata
        $rotation['total_hours'] = round($totalHours, 1);
        $rotation['variance'] = round($totalHours - self::TARGET_HOURS_PER_MONTH, 1);

        return $rotation;
    }

    /**
     * Generate schedule for admin/karu/coordinator
     * Pattern: Mon-Thu = Pagi8, Fri-Sat = Pagi9, Sun = off
     */
    private function generateAdminSchedule($admin, $shiftRules, $days, $month, $year)
    {
        // Find admin shifts (Pagi8 and Pagi9)
        $pagi8Shift = $shiftRules->where('role_type', 'admin')->firstWhere('shift_code', 'Pagi8');
        $pagi9Shift = $shiftRules->where('role_type', 'admin')->firstWhere('shift_code', 'Pagi9');
        
        // Fallback to any admin shift if specific ones not found
        if (!$pagi8Shift) {
            $pagi8Shift = $shiftRules->where('role_type', 'admin')->first();
        }
        if (!$pagi9Shift) {
            $pagi9Shift = $pagi8Shift; // Use same shift if Pagi9 not defined
        }

        // If still no admin shift found, use first available shift
        if (!$pagi8Shift) {
            $pagi8Shift = $shiftRules->first();
            $pagi9Shift = $pagi8Shift;
        }

        // Final check - if no shifts at all, throw error
        if (!$pagi8Shift) {
            throw new \Exception("Tidak ada shift yang tersedia untuk koordinator/admin di departemen ini.");
        }

        $rotation = ['id' => $admin->id];
        $totalHours = 0;

        for ($day = 1; $day <= $days; $day++) {
            $date = "$year-$month-$day";
            $dayOfWeek = date('N', strtotime($date)); // 1=Monday, 7=Sunday
            
            if ($dayOfWeek >= 1 && $dayOfWeek <= 4) {
                // Monday to Thursday - Pagi8
                $rotation["h$day"] = $pagi8Shift->shift_code;
                $totalHours += $pagi8Shift->duration_hours;
            } elseif ($dayOfWeek == 5 || $dayOfWeek == 6) {
                // Friday and Saturday - Pagi9
                $rotation["h$day"] = $pagi9Shift->shift_code;
                $totalHours += $pagi9Shift->duration_hours;
            } else {
                // Sunday - off
                $rotation["h$day"] = '-';
            }
        }

        $rotation['total_hours'] = round($totalHours, 1);
        $rotation['variance'] = round($totalHours - self::TARGET_HOURS_PER_MONTH, 1);

        return $rotation;
    }
}
