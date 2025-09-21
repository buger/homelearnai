<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\AccessControlService;
use App\Services\FileSecurityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class AccessControlServiceTest extends TestCase
{
    use RefreshDatabase;

    private AccessControlService $accessControlService;

    private $fileSecurityService;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the FileSecurityService dependency
        $this->fileSecurityService = Mockery::mock(FileSecurityService::class);
        $this->accessControlService = new AccessControlService($this->fileSecurityService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_prevents_race_condition_in_one_time_token_validation()
    {
        // Create a one-time token
        $tokenResult = $this->accessControlService->createAccessToken(
            $this->createMockUser(),
            ['id' => 1, 'name' => 'test.pdf'],
            'read',
            ['one_time_use' => true]
        );

        $token = $tokenResult['token'];

        // First validation should succeed
        $firstValidation = $this->accessControlService->validateAccessToken($token);
        $this->assertTrue($firstValidation['valid']);

        // Second validation should fail (token consumed)
        $secondValidation = $this->accessControlService->validateAccessToken($token);
        $this->assertFalse($secondValidation['valid']);
    }

    /** @test */
    public function it_handles_concurrent_token_validation_attempts()
    {
        // Create a one-time token
        $tokenResult = $this->accessControlService->createAccessToken(
            $this->createMockUser(),
            ['id' => 1, 'name' => 'test.pdf'],
            'read',
            ['one_time_use' => true]
        );

        $token = $tokenResult['token'];

        // Simulate concurrent access by manually checking the cache behavior
        [$tokenData, $hash] = explode('.', $token, 2);

        // Verify token exists before validation
        $this->assertTrue(Cache::has("file_access_token:{$hash}"));

        // First validation should succeed and remove the token
        $firstValidation = $this->accessControlService->validateAccessToken($token);
        $this->assertTrue($firstValidation['valid']);

        // Token should be removed from cache
        $this->assertFalse(Cache::has("file_access_token:{$hash}"));

        // Any subsequent attempts should fail
        $secondValidation = $this->accessControlService->validateAccessToken($token);
        $this->assertFalse($secondValidation['valid']);
    }

    /** @test */
    public function it_allows_multiple_uses_of_non_one_time_tokens()
    {
        // Create a regular (non-one-time) token
        $tokenResult = $this->accessControlService->createAccessToken(
            $this->createMockUser(),
            ['id' => 1, 'name' => 'test.pdf'],
            'read',
            ['one_time_use' => false]
        );

        $token = $tokenResult['token'];

        // First validation should succeed
        $firstValidation = $this->accessControlService->validateAccessToken($token);
        $this->assertTrue($firstValidation['valid']);

        // Second validation should also succeed (not one-time use)
        $secondValidation = $this->accessControlService->validateAccessToken($token);
        $this->assertTrue($secondValidation['valid']);
    }

    private function createMockUser()
    {
        return User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
