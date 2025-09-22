<?php

namespace App\Services;

use App\Models\Child;
use App\Models\Session;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class QualityHeuristicsService
{
    public function __construct(
        private SupabaseClient $supabase
    ) {}

    /**
     * Validate scheduling quality for a child
     */
    public function validateSchedulingQuality(Child $child): array
    {
        $warnings = [];
        $errors = [];
        $suggestions = [];

        // Get child's current schedule
        $sessions = Session::forChild($child->id)
            ->where('status', 'scheduled');
        $timeBlocks = $child->timeBlocks;

        // Grade-based validation
        $gradeValidation = $this->validateGradeAppropriateScheduling($child, $sessions, $timeBlocks);
        $warnings = array_merge($warnings, $gradeValidation['warnings']);
        $suggestions = array_merge($suggestions, $gradeValidation['suggestions']);

        // Cognitive load validation
        $cognitiveValidation = $this->validateCognitiveLoad($child, $sessions);
        $warnings = array_merge($warnings, $cognitiveValidation['warnings']);
        $suggestions = array_merge($suggestions, $cognitiveValidation['suggestions']);

        // Weekly capacity validation
        $capacityValidation = $this->validateWeeklyCapacity($child, $sessions);
        $warnings = array_merge($warnings, $capacityValidation['warnings']);
        $suggestions = array_merge($suggestions, $capacityValidation['suggestions']);

        // Subject distribution validation
        $distributionValidation = $this->validateSubjectDistribution($child, $sessions);
        $warnings = array_merge($warnings, $distributionValidation['warnings']);
        $suggestions = array_merge($suggestions, $distributionValidation['suggestions']);

        return [
            'child' => $child,
            'overall_score' => $this->calculateOverallQualityScore($warnings, $suggestions),
            'warnings' => $warnings,
            'errors' => $errors,
            'suggestions' => $suggestions,
            'metrics' => [
                'total_sessions' => $sessions->count(),
                'total_weekly_minutes' => $sessions->sum('estimated_minutes'),
                'average_session_length' => $sessions->avg('estimated_minutes'),
                'days_with_sessions' => $sessions->pluck('day_of_week')->unique()->count(),
            ],
        ];
    }

    /**
     * Validate grade-appropriate scheduling
     */
    private function validateGradeAppropriateScheduling(Child $child, Collection $sessions, Collection $timeBlocks): array
    {
        $warnings = [];
        $suggestions = [];

        $grade = $child->grade;
        $recommendedLimits = $this->getGradeBasedLimits($grade);

        // Session length validation
        $longSessions = $sessions->filter(function ($session) use ($recommendedLimits) {
            return $session->estimated_minutes > $recommendedLimits['max_session_minutes'];
        });

        if ($longSessions->isNotEmpty()) {
            $warnings[] = [
                'type' => 'long_sessions',
                'severity' => 'medium',
                'message' => "Found {$longSessions->count()} session(s) longer than recommended {$recommendedLimits['max_session_minutes']} minutes for grade {$grade}",
                'affected_sessions' => $longSessions->pluck('id')->toArray(),
            ];

            $suggestions[] = [
                'type' => 'break_long_sessions',
                'priority' => 'medium',
                'message' => "Consider breaking sessions longer than {$recommendedLimits['max_session_minutes']} minutes into smaller chunks with breaks",
            ];
        }

        // Daily hour limits
        $dailyMinutes = $this->calculateDailySessionMinutes($sessions);
        foreach ($dailyMinutes as $dayOfWeek => $minutes) {
            if ($minutes > $recommendedLimits['max_daily_minutes']) {
                $warnings[] = [
                    'type' => 'daily_overload',
                    'severity' => 'high',
                    'message' => "Day {$this->getDayName($dayOfWeek)} has {$minutes} minutes of sessions, exceeding recommended {$recommendedLimits['max_daily_minutes']} minutes for grade {$grade}",
                    'day_of_week' => $dayOfWeek,
                    'minutes' => $minutes,
                ];
            }
        }

        // Early morning sessions for young kids
        if ($this->isEarlyGrade($grade)) {
            $earlyMorningSessions = $timeBlocks->filter(function ($block) {
                $startTime = Carbon::createFromFormat('H:i:s', $block->start_time);

                return $startTime->hour < 9;
            });

            if ($earlyMorningSessions->isNotEmpty()) {
                $warnings[] = [
                    'type' => 'early_morning',
                    'severity' => 'low',
                    'message' => "Young children (grade {$grade}) may struggle with sessions before 9:00 AM",
                    'affected_blocks' => $earlyMorningSessions->pluck('id')->toArray(),
                ];
            }
        }

        // Concentration span recommendations
        if ($this->isElementaryOrEarlier($grade)) {
            $backToBackSessions = $this->findBackToBackSessions($sessions, $timeBlocks);
            if (! empty($backToBackSessions)) {
                $warnings[] = [
                    'type' => 'back_to_back_sessions',
                    'severity' => 'medium',
                    'message' => "Found back-to-back sessions which may be too demanding for grade {$grade}",
                    'affected_sessions' => $backToBackSessions,
                ];

                $suggestions[] = [
                    'type' => 'add_breaks',
                    'priority' => 'medium',
                    'message' => 'Add 15-30 minute breaks between intensive learning sessions',
                ];
            }
        }

        return ['warnings' => $warnings, 'suggestions' => $suggestions];
    }

    /**
     * Validate cognitive load distribution
     */
    private function validateCognitiveLoad(Child $child, Collection $sessions): array
    {
        $warnings = [];
        $suggestions = [];

        // Group sessions by cognitive intensity
        $highIntensitySubjects = ['Math', 'Mathematics', 'Science', 'Physics', 'Chemistry', 'Programming', 'Logic'];

        $sessionsByDay = $sessions->groupBy('day_of_week');

        foreach ($sessionsByDay as $dayOfWeek => $daySessions) {
            $highIntensityCount = $daySessions->filter(function ($session) use ($highIntensitySubjects) {
                $subject = $session->subject();

                return $subject && in_array($subject->name, $highIntensitySubjects);
            })->count();

            // Too many high-intensity subjects in one day
            if ($highIntensityCount > 2) {
                $warnings[] = [
                    'type' => 'high_cognitive_load',
                    'severity' => 'medium',
                    'message' => "Day {$this->getDayName($dayOfWeek)} has {$highIntensityCount} high-intensity subjects, which may be overwhelming",
                    'day_of_week' => $dayOfWeek,
                    'count' => $highIntensityCount,
                ];

                $suggestions[] = [
                    'type' => 'distribute_intensity',
                    'priority' => 'medium',
                    'message' => 'Consider spreading high-intensity subjects like Math and Science across different days',
                ];
            }

            // Check morning vs afternoon scheduling for concentration-heavy subjects
            $morningIntense = $daySessions->filter(function ($session) use ($highIntensitySubjects) {
                $subject = $session->subject();
                if (! $subject || ! in_array($subject->name, $highIntensitySubjects)) {
                    return false;
                }

                // Assume morning is before 12:00 PM
                $startTime = Carbon::createFromFormat('H:i:s', $session->scheduled_start_time ?? '12:00:00');

                return $startTime->hour < 12;
            })->count();

            $afternoonIntense = $highIntensityCount - $morningIntense;

            if ($afternoonIntense > $morningIntense && $afternoonIntense > 1) {
                $suggestions[] = [
                    'type' => 'morning_scheduling',
                    'priority' => 'low',
                    'message' => 'Consider scheduling more concentration-heavy subjects in the morning when focus is typically higher',
                ];
            }
        }

        return ['warnings' => $warnings, 'suggestions' => $suggestions];
    }

    /**
     * Validate weekly capacity
     */
    private function validateWeeklyCapacity(Child $child, Collection $sessions): array
    {
        $warnings = [];
        $suggestions = [];

        $grade = $child->grade;
        $limits = $this->getGradeBasedLimits($grade);
        $totalWeeklyMinutes = $sessions->sum('estimated_minutes');

        // Weekly overload
        if ($totalWeeklyMinutes > $limits['max_weekly_minutes']) {
            $warnings[] = [
                'type' => 'weekly_overload',
                'severity' => 'high',
                'message' => "Total weekly learning time ({$totalWeeklyMinutes} minutes) exceeds recommended maximum ({$limits['max_weekly_minutes']} minutes) for grade {$grade}",
                'total_minutes' => $totalWeeklyMinutes,
                'recommended_max' => $limits['max_weekly_minutes'],
            ];

            $suggestions[] = [
                'type' => 'reduce_load',
                'priority' => 'high',
                'message' => 'Consider reducing session lengths or moving some topics to future weeks',
            ];
        }

        // Under-scheduled
        if ($totalWeeklyMinutes < $limits['min_weekly_minutes']) {
            $suggestions[] = [
                'type' => 'increase_engagement',
                'priority' => 'low',
                'message' => "Current schedule ({$totalWeeklyMinutes} minutes) is below recommended minimum ({$limits['min_weekly_minutes']} minutes). Consider adding more learning activities",
            ];
        }

        // Distribution across days
        $activeDays = $sessions->pluck('day_of_week')->unique()->count();
        if ($activeDays < 4 && $totalWeeklyMinutes > 240) { // Less than 4 days with 4+ hours total
            $warnings[] = [
                'type' => 'poor_distribution',
                'severity' => 'medium',
                'message' => "Learning is concentrated in only {$activeDays} days. Consider spreading across more days for better retention",
                'active_days' => $activeDays,
            ];

            $suggestions[] = [
                'type' => 'spread_schedule',
                'priority' => 'medium',
                'message' => 'Aim to have learning activities on at least 4-5 days per week for optimal retention',
            ];
        }

        return ['warnings' => $warnings, 'suggestions' => $suggestions];
    }

    /**
     * Validate subject distribution
     */
    private function validateSubjectDistribution(Child $child, Collection $sessions): array
    {
        $warnings = [];
        $suggestions = [];

        // Group sessions by subject
        $subjectMinutes = [];
        foreach ($sessions as $session) {
            $subject = $session->subject();
            if ($subject) {
                $subjectMinutes[$subject->name] = ($subjectMinutes[$subject->name] ?? 0) + $session->estimated_minutes;
            }
        }

        if (empty($subjectMinutes)) {
            return ['warnings' => $warnings, 'suggestions' => $suggestions];
        }

        $totalMinutes = array_sum($subjectMinutes);

        // Check for subject dominance (one subject taking > 50% of time)
        foreach ($subjectMinutes as $subject => $minutes) {
            $percentage = ($minutes / $totalMinutes) * 100;

            if ($percentage > 50) {
                $warnings[] = [
                    'type' => 'subject_dominance',
                    'severity' => 'medium',
                    'message' => "{$subject} takes up {$percentage}% of learning time, which may limit exposure to other subjects",
                    'subject' => $subject,
                    'percentage' => round($percentage, 1),
                ];

                $suggestions[] = [
                    'type' => 'balance_subjects',
                    'priority' => 'medium',
                    'message' => "Consider reducing {$subject} time and adding variety with other subjects",
                ];
            }
        }

        // Check for core subject coverage
        $coreSubjects = ['Math', 'Mathematics', 'English', 'Language Arts', 'Reading', 'Science'];
        $coveredCore = array_intersect(array_keys($subjectMinutes), $coreSubjects);

        if (count($coveredCore) < 3) {
            $suggestions[] = [
                'type' => 'core_subjects',
                'priority' => 'medium',
                'message' => 'Consider including more core subjects (Math, English/Reading, Science) in the weekly schedule',
            ];
        }

        return ['warnings' => $warnings, 'suggestions' => $suggestions];
    }

    /**
     * Get grade-based scheduling limits
     */
    private function getGradeBasedLimits(string $grade): array
    {
        // Based on educational research and homeschool guidelines
        $gradeGroup = $this->getGradeGroupFromGrade($grade);

        return match ($gradeGroup) {
            'preschool' => [
                'max_session_minutes' => 20,
                'max_daily_minutes' => 90,
                'max_weekly_minutes' => 450,
                'min_weekly_minutes' => 180,
            ],
            'elementary' => [
                'max_session_minutes' => 45,
                'max_daily_minutes' => 240,
                'max_weekly_minutes' => 1200,
                'min_weekly_minutes' => 450,
            ],
            'middle_school' => [
                'max_session_minutes' => 60,
                'max_daily_minutes' => 360,
                'max_weekly_minutes' => 1800,
                'min_weekly_minutes' => 600,
            ],
            'high_school' => [
                'max_session_minutes' => 90,
                'max_daily_minutes' => 480,
                'max_weekly_minutes' => 2400,
                'min_weekly_minutes' => 900,
            ],
            default => [
                'max_session_minutes' => 45,
                'max_daily_minutes' => 240,
                'max_weekly_minutes' => 1200,
                'min_weekly_minutes' => 450,
            ],
        };
    }

    /**
     * Calculate daily session minutes
     */
    private function calculateDailySessionMinutes(Collection $sessions): array
    {
        $dailyMinutes = [];

        foreach ($sessions->groupBy('day_of_week') as $dayOfWeek => $daySessions) {
            $dailyMinutes[$dayOfWeek] = $daySessions->sum('estimated_minutes');
        }

        return $dailyMinutes;
    }

    /**
     * Find back-to-back sessions
     */
    private function findBackToBackSessions(Collection $sessions, Collection $timeBlocks): array
    {
        $backToBack = [];

        foreach ($timeBlocks->groupBy('day_of_week') as $dayOfWeek => $dayBlocks) {
            $sortedBlocks = $dayBlocks->sortBy('start_time');
            $previousBlock = null;

            foreach ($sortedBlocks as $block) {
                if ($previousBlock) {
                    $prevEnd = Carbon::createFromFormat('H:i:s', $previousBlock->end_time);
                    $currentStart = Carbon::createFromFormat('H:i:s', $block->start_time);

                    // If less than 15 minutes between sessions
                    if ($prevEnd->diffInMinutes($currentStart) < 15) {
                        $backToBack[] = [
                            'day_of_week' => $dayOfWeek,
                            'first_session' => $previousBlock->id,
                            'second_session' => $block->id,
                            'gap_minutes' => $prevEnd->diffInMinutes($currentStart),
                        ];
                    }
                }
                $previousBlock = $block;
            }
        }

        return $backToBack;
    }

    /**
     * Calculate overall quality score (0-100)
     */
    private function calculateOverallQualityScore(array $warnings, array $suggestions): int
    {
        $score = 100;

        // Deduct points based on warning severity
        foreach ($warnings as $warning) {
            $deduction = match ($warning['severity']) {
                'high' => 15,
                'medium' => 8,
                'low' => 3,
                default => 5,
            };
            $score -= $deduction;
        }

        // Minor deduction for suggestions
        $score -= count($suggestions) * 2;

        return max(0, min(100, $score));
    }

    /**
     * Get day name from day number
     */
    private function getDayName(int $dayOfWeek): string
    {
        $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday',
            5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

        return $days[$dayOfWeek] ?? 'Unknown';
    }

    /**
     * Get scheduling recommendations for a child
     */
    public function getSchedulingRecommendations(Child $child): array
    {
        $grade = $child->grade;
        $limits = $this->getGradeBasedLimits($grade);

        return [
            'grade_group' => $this->getGradeGroupName($grade),
            'recommended_session_length' => $limits['max_session_minutes'],
            'recommended_daily_minutes' => $limits['max_daily_minutes'],
            'recommended_weekly_minutes' => $limits['max_weekly_minutes'],
            'optimal_times' => $this->getOptimalLearningTimes($grade),
            'break_recommendations' => $this->getBreakRecommendations($grade),
            'subject_sequencing' => $this->getSubjectSequencingTips($grade),
        ];
    }

    /**
     * Get grade group name
     */
    private function getGradeGroupName(string $grade): string
    {
        $gradeGroup = $this->getGradeGroupFromGrade($grade);

        return match ($gradeGroup) {
            'preschool' => 'Pre-K/Kindergarten',
            'elementary' => 'Elementary (1st-5th)',
            'middle_school' => 'Middle School (6th-8th)',
            'high_school' => 'High School (9th-12th)',
            default => 'Elementary',
        };
    }

    /**
     * Get optimal learning times for grade
     */
    private function getOptimalLearningTimes(string $grade): array
    {
        $gradeGroup = $this->getGradeGroupFromGrade($grade);

        return match ($gradeGroup) {
            'preschool' => [
                'best' => '9:00 AM - 11:00 AM',
                'good' => '2:00 PM - 3:00 PM',
                'avoid' => 'Before 8:30 AM, After 4:00 PM',
            ],
            'elementary' => [
                'best' => '9:00 AM - 12:00 PM',
                'good' => '1:00 PM - 3:00 PM',
                'avoid' => 'Before 8:00 AM, After 5:00 PM',
            ],
            'middle_school', 'high_school' => [
                'best' => '9:00 AM - 12:00 PM, 2:00 PM - 4:00 PM',
                'good' => '8:00 AM - 9:00 AM, 1:00 PM - 2:00 PM',
                'flexible' => 'Can adapt to individual preferences',
            ],
            default => [
                'best' => '9:00 AM - 12:00 PM',
                'good' => '1:00 PM - 3:00 PM',
                'avoid' => 'Before 8:00 AM, After 5:00 PM',
            ],
        };
    }

    /**
     * Get break recommendations
     */
    private function getBreakRecommendations(string $grade): array
    {
        $gradeGroup = $this->getGradeGroupFromGrade($grade);

        return match ($gradeGroup) {
            'preschool' => [
                'frequency' => 'Every 15-20 minutes',
                'duration' => '5-10 minutes',
                'type' => 'Movement breaks, stretching, free play',
            ],
            'elementary' => [
                'frequency' => 'Every 30-45 minutes',
                'duration' => '10-15 minutes',
                'type' => 'Exercise, social interaction, creative activities',
            ],
            'middle_school', 'high_school' => [
                'frequency' => 'Every 45-60 minutes',
                'duration' => '15-30 minutes',
                'type' => 'Physical activity, meals, self-directed time',
            ],
            default => [
                'frequency' => 'Every 30-45 minutes',
                'duration' => '10-15 minutes',
                'type' => 'Exercise, social interaction, creative activities',
            ],
        };
    }

    /**
     * Get subject sequencing tips
     */
    private function getSubjectSequencingTips(string $grade): array
    {
        return [
            'morning_priority' => 'Math, Science, intensive reading',
            'afternoon_suitable' => 'History, art, music, lighter subjects',
            'energy_management' => 'Start with most challenging subjects when energy is highest',
            'variety_importance' => 'Alternate between subjects requiring different types of thinking',
        ];
    }

    /**
     * Get grade group from grade string
     */
    private function getGradeGroupFromGrade(string $grade): string
    {
        return match ($grade) {
            'PK', 'PreK', 'K', 'Kindergarten' => 'preschool',
            '1st', '2nd', '3rd', '4th', '5th' => 'elementary',
            '6th', '7th', '8th' => 'middle_school',
            '9th', '10th', '11th', '12th' => 'high_school',
            default => 'elementary',
        };
    }

    /**
     * Check if grade is early/young
     */
    private function isEarlyGrade(string $grade): bool
    {
        $gradeGroup = $this->getGradeGroupFromGrade($grade);

        return in_array($gradeGroup, ['preschool', 'elementary']);
    }

    /**
     * Check if grade is elementary or earlier
     */
    private function isElementaryOrEarlier(string $grade): bool
    {
        $gradeGroup = $this->getGradeGroupFromGrade($grade);

        return in_array($gradeGroup, ['preschool', 'elementary']);
    }
}
