<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SupabaseClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class LocaleController extends Controller
{
    public function __construct(
        private SupabaseClient $supabase
    ) {}

    /**
     * Generate a unique guest ID for non-authenticated users
     * Uses multiple fallback strategies for consistency
     */
    private function generateGuestId(Request $request): string
    {
        // Priority 1: Use existing guest_id from session if available
        if (Session::has('guest_id')) {
            return Session::get('guest_id');
        }

        // Priority 2: Create guest ID from request fingerprint
        $userAgent = $request->header('User-Agent', 'unknown');
        $acceptLanguage = $request->header('Accept-Language', 'unknown');

        // For E2E tests where sessions change, use a more stable fingerprint
        // that doesn't depend on the session ID
        $ipAddress = $request->ip() ?? 'unknown';
        $fingerprint = hash('sha256', $userAgent.$acceptLanguage.$ipAddress);
        $guestId = 'guest_'.substr($fingerprint, 0, 16);

        // Store in session for consistency
        Session::put('guest_id', $guestId);

        return $guestId;
    }

    /**
     * Update locale preference (unified endpoint for both authenticated and guest users)
     */
    public function updateLocale(Request $request): JsonResponse
    {
        $request->validate([
            'locale' => 'required|string|in:en,ru,he',
        ]);

        $locale = $request->input('locale');
        $userId = auth()->id();
        $isAuthenticated = ! empty($userId);
        $isAuthPage = $this->isAuthenticationPage($request);

        try {
            if ($isAuthenticated) {
                // Authenticated user - save to User model directly
                $user = auth()->user();
                $previousLocale = $user->locale;
                $user->locale = $locale;

                // Apply regional format defaults if this is the first time setting locale
                // or if the user is switching from one locale to another
                if ($previousLocale !== $locale) {
                    // Only apply defaults if user is not using custom format
                    if (! $user->isCustomFormat()) {
                        $user->applyRegionalDefaults($locale);
                    }
                }

                $user->save();

                // Also try to save to user_preferences in Supabase for compatibility
                // Only try if we have a valid access token
                $accessToken = session('supabase_token');
                if ($accessToken) {
                    try {
                        $this->supabase->setUserToken($accessToken);

                        // Try to update existing preference first
                        try {
                            $existing = $this->supabase->from('user_preferences')
                                ->select('id')
                                ->eq('user_id', $userId)
                                ->single();

                            if ($existing) {
                                // Update existing preference - need to build query with WHERE clause first
                                $this->supabase->from('user_preferences')
                                    ->eq('user_id', $userId)
                                    ->update([
                                        'locale' => $locale,
                                        'updated_at' => now()->toISOString(),
                                    ]);
                            } else {
                                // Insert new preference
                                $this->supabase->from('user_preferences')
                                    ->insert([
                                        'user_id' => $userId,
                                        'locale' => $locale,
                                        'updated_at' => now()->toISOString(),
                                    ]);
                            }
                        } catch (\Exception $e) {
                            // Fallback: try insert (might fail on conflict, but that's OK)
                            try {
                                $this->supabase->from('user_preferences')
                                    ->insert([
                                        'user_id' => $userId,
                                        'locale' => $locale,
                                        'updated_at' => now()->toISOString(),
                                    ]);
                            } catch (\Exception $insertError) {
                                // Last attempt: update (in case of race condition) - need WHERE clause first
                                $this->supabase->from('user_preferences')
                                    ->eq('user_id', $userId)
                                    ->update([
                                        'locale' => $locale,
                                        'updated_at' => now()->toISOString(),
                                    ]);
                            }
                        }
                    } catch (\Exception $supabaseError) {
                        // Log the error but don't fail the request since we saved to User model
                        \Log::warning('Failed to save locale to Supabase user_preferences', [
                            'user_id' => $userId,
                            'locale' => $locale,
                            'error' => $supabaseError->getMessage(),
                        ]);
                    }
                }

                \Log::debug('Updated user locale in database', [
                    'user_id' => $userId,
                    'locale' => $locale,
                    'supabase_attempted' => ! empty($accessToken),
                ]);
            } else {
                // Guest user - save to guest_preferences
                $guestId = $this->generateGuestId($request);

                // Use the helper function for upsert
                $this->supabase->rpc('update_guest_locale', [
                    'p_guest_id' => $guestId,
                    'p_locale' => $locale,
                ]);

                \Log::debug('Updated guest locale in database', [
                    'guest_id' => $guestId,
                    'locale' => $locale,
                ]);
            }

            // Update session locale for caching
            Session::put('locale', $locale);
            App::setLocale($locale);

            // Force session save to ensure persistence in test environment
            session()->save();

            // Debug logging for E2E tests
            if (config('app.env') === 'testing') {
                \Log::info('LocaleController: Locale updated successfully', [
                    'locale' => $locale,
                    'user_type' => $isAuthenticated ? 'authenticated' : 'guest',
                    'user_id' => $userId,
                    'guest_id' => ! $isAuthenticated ? $this->generateGuestId($request) : null,
                    'session_id' => session()->getId(),
                    'session_locale' => session('locale'),
                    'app_locale' => App::getLocale(),
                ]);
            }

            $response = response()->json([
                'success' => true,
                'locale' => $locale,
                'message' => __('Language changed successfully'),
            ]);

            // For authentication pages (pre-auth), set cookie instead of database
            if ($isAuthPage && ! $isAuthenticated) {
                $response->cookie('pre_auth_locale', $locale, 60 * 24 * 7); // 7 days
            }

            // For testing environment, also set a cookie as backup
            if (config('app.env') === 'testing') {
                $response->cookie('locale_backup', $locale, 60 * 24 * 7); // 7 days
            }

            return $response;

        } catch (\Exception $e) {
            \Log::error('Failed to update locale preference', [
                'locale' => $locale,
                'user_type' => $isAuthenticated ? 'authenticated' : 'guest',
                'user_id' => $userId,
                'is_auth_page' => $isAuthPage,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Fallback: at least update session and cookie
            try {
                Session::put('locale', $locale);
                App::setLocale($locale);
                session()->save();

                $response = response()->json([
                    'success' => true,
                    'locale' => $locale,
                    'message' => __('Language changed successfully (session only)'),
                ]);

                // On auth pages, always set pre-auth cookie as fallback
                if ($isAuthPage && ! $isAuthenticated) {
                    $response->cookie('pre_auth_locale', $locale, 60 * 24 * 7);
                }

                \Log::warning('Locale updated in session/cookie only (database failed)', [
                    'locale' => $locale,
                    'is_auth_page' => $isAuthPage,
                ]);

                return $response;
            } catch (\Exception $sessionError) {
                \Log::error('Both database and session update failed', [
                    'locale' => $locale,
                    'db_error' => $e->getMessage(),
                    'session_error' => $sessionError->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => __('Failed to update language preference'),
                ], 500);
            }
        }
    }

    /**
     * Legacy method - redirects to unified updateLocale method
     *
     * @deprecated Use updateLocale() instead
     */
    public function updateUserLocale(Request $request): JsonResponse
    {
        return $this->updateLocale($request);
    }

    /**
     * Legacy method - redirects to unified updateLocale method
     *
     * @deprecated Use updateLocale() instead
     */
    public function updateSessionLocale(Request $request): JsonResponse
    {
        return $this->updateLocale($request);
    }

    /**
     * Get translations for a specific locale (for dynamic loading)
     */
    public function getTranslations(Request $request, string $locale): JsonResponse
    {
        try {
            // Validate locale
            if (! in_array($locale, ['en', 'ru', 'he'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid locale',
                ], 400);
            }

            // Temporarily set locale to get translations
            $originalLocale = App::getLocale();
            App::setLocale($locale);

            // Get all translations for the locale
            $translations = __('*');

            // Restore original locale
            App::setLocale($originalLocale);

            return response()->json([
                'success' => true,
                'locale' => $locale,
                'translations' => $translations,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to get translations', [
                'locale' => $locale,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get translations',
            ], 500);
        }
    }

    /**
     * Get available locales
     */
    public function getAvailableLocales(): JsonResponse
    {
        try {
            $locales = [
                'en' => [
                    'name' => 'English',
                    'native' => 'English',
                    'flag' => '🇬🇧',
                ],
                'ru' => [
                    'name' => 'Russian',
                    'native' => 'Русский',
                    'flag' => '🇷🇺',
                ],
                'he' => [
                    'name' => 'Hebrew',
                    'native' => 'עברית',
                    'flag' => '🇮🇱',
                ],
            ];

            return response()->json([
                'success' => true,
                'locales' => $locales,
                'current' => App::getLocale(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to get available locales', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get available locales',
            ], 500);
        }
    }

    /**
     * Check if the current request is for an authentication page
     */
    private function isAuthenticationPage(Request $request): bool
    {
        $route = $request->route();
        if (! $route) {
            return false;
        }

        $routeName = $route->getName();
        $authRoutes = ['login', 'register', 'auth.confirm'];

        return in_array($routeName, $authRoutes);
    }
}
