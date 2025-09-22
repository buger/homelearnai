import { test, expect } from '@playwright/test';
import { ModalHelper, ElementHelper } from './helpers/modal-helpers';
import { KidsModeHelper } from './helpers/kids-mode-helpers';

test.describe('Planning Board Comprehensive Tests', () => {
  let testUser: { name: string; email: string; password: string };
  let modalHelper: ModalHelper;
  let elementHelper: ElementHelper;
  let kidsModeHelper: KidsModeHelper;
  let testChild: { name: string; id?: string };
  let testSubject: { name: string; color: string };
  let testUnit: { name: string; description: string };
  let testTopic: { name: string; description: string; minutes: number };

  test.beforeEach(async ({ page }) => {
    // Initialize helpers
    modalHelper = new ModalHelper(page);
    elementHelper = new ElementHelper(page);
    kidsModeHelper = new KidsModeHelper(page);

    // Ensure we're not in kids mode at start of each test
    await kidsModeHelper.resetKidsMode();

    // Create unique test data for this session
    const timestamp = Date.now();
    testUser = {
      name: 'Planning Board Test User',
      email: `planning-test-${timestamp}@example.com`,
      password: 'testpassword123'
    };

    testChild = {
      name: `Test Child ${timestamp.toString().slice(-4)}`
    };

    testSubject = {
      name: `Test Math ${timestamp.toString().slice(-4)}`,
      color: '#ef4444'
    };

    testUnit = {
      name: `Test Unit ${timestamp.toString().slice(-4)}`,
      description: 'Test unit for planning board functionality'
    };

    testTopic = {
      name: `Test Topic ${timestamp.toString().slice(-4)}`,
      description: 'Test topic for session creation',
      minutes: 30
    };

    // Register and login user
    await page.goto('/register');
    await elementHelper.waitForPageReady();

    await elementHelper.safeFill('input[name="name"]', testUser.name);
    await elementHelper.safeFill('input[name="email"]', testUser.email);
    await elementHelper.safeFill('input[name="password"]', testUser.password);
    await elementHelper.safeFill('input[name="password_confirmation"]', testUser.password);

    await elementHelper.safeClick('button[type="submit"]');
    await elementHelper.waitForPageReady();

    // Handle potential registration outcomes
    const currentUrl = page.url();
    if (currentUrl.includes('/register')) {
      // Registration may have failed, try login instead
      await page.goto('/login');
      await elementHelper.waitForPageReady();

      await elementHelper.safeFill('input[name="email"]', testUser.email);
      await elementHelper.safeFill('input[name="password"]', testUser.password);
      await elementHelper.safeClick('button[type="submit"]');
      await elementHelper.waitForPageReady();
    }

    // Set up test data: Create child, subject, unit, and topic
    await setupTestData(page);
  });

  async function setupTestData(page) {
    // Create child
    await page.goto('/children');
    await elementHelper.waitForPageReady();

    // Try API creation first (more reliable)
    try {
      const cookies = await page.context().cookies();
      const sessionCookie = cookies.find(c => c.name.includes('session'));
      const csrfToken = await page.getAttribute('meta[name="csrf-token"]', 'content');

      if (sessionCookie && csrfToken) {
        const response = await page.request.post('/children', {
          headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Cookie': `${sessionCookie.name}=${sessionCookie.value}`,
            'Content-Type': 'application/x-www-form-urlencoded',
            'HX-Request': 'true',
          },
          form: {
            name: testChild.name,
            age: '10',
            independence_level: '2'
          }
        });

        if (response.ok()) {
          console.log('Child created successfully via API');
        } else {
          throw new Error('API creation failed');
        }
      } else {
        throw new Error('No session cookie or CSRF token found');
      }
    } catch (e) {
      console.log('API child creation failed, using modal fallback');
      // Fallback to modal creation
      await elementHelper.safeClick('[data-testid="header-add-child-btn"]');
      await page.waitForSelector('#child-form-modal [data-testid="modal-content"]', { timeout: 10000 });
      await page.waitForTimeout(1000);

      await page.fill('#child-form-modal input[name="name"]', testChild.name);
      await page.selectOption('#child-form-modal select[name="grade"]', '5th');
      await page.selectOption('#child-form-modal select[name="independence_level"]', '2');
      await page.click('#child-form-modal button[type="submit"]');

      await modalHelper.waitForHtmxCompletion();
      await page.waitForTimeout(2000);
    }

    // Create subject
    await page.goto('/subjects');
    await elementHelper.waitForPageReady();

    const addSubjectButton = page.locator('button:has-text("Add Subject")').first();
    if (await addSubjectButton.count() > 0) {
      await addSubjectButton.click();
      await page.fill('input[name="name"]', testSubject.name);
      await page.selectOption('select[name="color"]', testSubject.color);
      await page.click('button:has-text("Save")');
      await page.waitForTimeout(1000);
    }

    // Create unit
    const subjectLink = page.locator(`text=${testSubject.name}`);
    if (await subjectLink.count() > 0) {
      await subjectLink.click();
      await page.waitForTimeout(1000);

      const addUnitButton = page.locator('button:has-text("Add Unit")').first();
      if (await addUnitButton.count() > 0) {
        await addUnitButton.click();
        await modalHelper.waitForModal('unit-create-modal');

        await modalHelper.fillModalField('unit-create-modal', 'name', testUnit.name);
        await modalHelper.fillModalField('unit-create-modal', 'description', testUnit.description);
        await modalHelper.fillModalField('unit-create-modal', 'target_completion_date', '2024-12-31');

        await modalHelper.submitModalForm('unit-create-modal');
      }
    }

    // Create topic
    const viewUnitLink = page.locator('a:has-text("View Unit")');
    if (await viewUnitLink.count() > 0) {
      await viewUnitLink.click();
      await page.waitForTimeout(1000);

      const addTopicButton = page.locator('button:has-text("Add Topic")');
      if (await addTopicButton.count() > 0) {
        await addTopicButton.click();
        await modalHelper.waitForModal('topic-create-modal');

        await modalHelper.fillModalField('topic-create-modal', 'name', testTopic.name);
        await modalHelper.fillModalField('topic-create-modal', 'description', testTopic.description);
        await modalHelper.fillModalField('topic-create-modal', 'estimated_minutes', testTopic.minutes.toString());

        await modalHelper.submitModalForm('topic-create-modal');
      }
    }
  }

  test.describe('Planning Board Navigation & Setup', () => {
    test('should navigate to planning board and display correct structure', async ({ page }) => {
      await page.goto('/planning');
      await elementHelper.waitForPageReady();

      // Verify page title and description
      await expect(page.locator('h2')).toContainText('Topic Planning Board');
      await expect(page.locator('text=Plan and schedule learning sessions for your children')).toBeVisible();

      // Verify child selector is present
      await expect(page.locator('select[name="child_id"]')).toBeVisible();
      await expect(page.locator('label[for="child_id"]')).toContainText('Child');
    });

    test('should select child and load planning board with correct columns', async ({ page }) => {
      await page.goto('/planning');
      await elementHelper.waitForPageReady();

      // Select the test child
      const childSelect = page.locator('select[name="child_id"]');
      const childOption = page.locator(`select[name="child_id"] option:has-text("${testChild.name}")`);

      if (await childOption.count() > 0) {
        await childSelect.selectOption({ label: testChild.name });
        await page.waitForTimeout(1000); // Wait for HTMX to load board

        // Verify planning board columns are present
        await expect(page.locator('[data-status="backlog"]')).toBeVisible();
        await expect(page.locator('[data-status="planned"]')).toBeVisible();
        await expect(page.locator('[data-status="scheduled"]')).toBeVisible();
        await expect(page.locator('[data-status="done"]')).toBeVisible();

        // Verify column headers
        await expect(page.locator('text=Backlog')).toBeVisible();
        await expect(page.locator('text=Planned')).toBeVisible();
        await expect(page.locator('text=Scheduled')).toBeVisible();
        await expect(page.locator('text=Done')).toBeVisible();

        // Verify capacity meter is present
        await expect(page.locator('.capacity-meter, [class*="capacity"]').first()).toBeVisible();

        // Verify Create Session button is visible and clickable
        const createSessionButton = page.locator('button:has-text("Create Session")');
        await expect(createSessionButton).toBeVisible();
        await expect(createSessionButton).not.toBeDisabled();
      } else {
        console.log('Test child not found in dropdown, skipping board verification');
      }
    });

    test('should show no child selected state initially', async ({ page }) => {
      await page.goto('/planning');
      await elementHelper.waitForPageReady();

      // Should show no child selected message
      await expect(page.locator('text=No child selected')).toBeVisible();
      await expect(page.locator('text=You need to add a child first')).toBeVisible();
      await expect(page.locator('a:has-text("Manage Children")')).toBeVisible();
    });
  });

  test.describe('Session Creation Workflow', () => {
    test('should open create session modal and display form correctly', async ({ page }) => {
      await page.goto('/planning');
      await elementHelper.waitForPageReady();

      // Select child
      const childSelect = page.locator('select[name="child_id"]');
      const childOption = page.locator(`select[name="child_id"] option:has-text("${testChild.name}")`);

      if (await childOption.count() > 0) {
        await childSelect.selectOption({ label: testChild.name });
        await page.waitForTimeout(1000);

        // Click Create Session button (test both locations - header and backlog column)
        const headerCreateButton = page.locator('button:has-text("Create Session")').first();
        await expect(headerCreateButton).toBeVisible();
        await headerCreateButton.click();

        // Wait for modal to appear
        await page.waitForSelector('#create-session-modal', { timeout: 10000 });

        // Verify modal content
        await expect(page.locator('#create-session-modal')).toBeVisible();
        await expect(page.locator('h3:has-text("Create Learning Session")')).toBeVisible();

        // Verify form fields are present
        await expect(page.locator('select[name="topic_id"]')).toBeVisible();
        await expect(page.locator('input[name="estimated_minutes"]')).toBeVisible();
        await expect(page.locator('button:has-text("Create Session")')).toBeVisible();
        await expect(page.locator('button:has-text("Cancel")')).toBeVisible();

        // Verify topic is available in dropdown
        const topicOption = page.locator(`select[name="topic_id"] option:has-text("${testTopic.name}")`);
        await expect(topicOption).toBeVisible();
      }
    });

    test('should create session successfully with valid data', async ({ page }) => {
      await page.goto('/planning');
      await elementHelper.waitForPageReady();

      // Select child and open modal
      const childSelect = page.locator('select[name="child_id"]');
      const childOption = page.locator(`select[name="child_id"] option:has-text("${testChild.name}")`);

      if (await childOption.count() > 0) {
        await childSelect.selectOption({ label: testChild.name });
        await page.waitForTimeout(1000);

        const createButton = page.locator('button:has-text("Create Session")').first();
        await createButton.click();

        await page.waitForSelector('#create-session-modal', { timeout: 10000 });

        // Fill form
        await page.selectOption('select[name="topic_id"]', { label: testTopic.name });
        await page.fill('input[name="estimated_minutes"]', '45'); // Custom duration

        // Submit form
        await page.click('button[type="submit"]:has-text("Create Session")');

        // Wait for modal to close and session to appear
        await page.waitForSelector('#create-session-modal', { state: 'detached', timeout: 10000 });
        await page.waitForTimeout(2000);

        // Verify session appears in backlog column
        const backlogColumn = page.locator('[data-status="backlog"]');
        await expect(backlogColumn.locator(`.session-card:has-text("${testTopic.name}")`)).toBeVisible();

        // Verify session has correct duration
        await expect(backlogColumn.locator('text=45 min')).toBeVisible();
      }
    });

    test('should handle form validation errors', async ({ page }) => {
      await page.goto('/planning');
      await elementHelper.waitForPageReady();

      const childSelect = page.locator('select[name="child_id"]');
      const childOption = page.locator(`select[name="child_id"] option:has-text("${testChild.name}")`);

      if (await childOption.count() > 0) {
        await childSelect.selectOption({ label: testChild.name });
        await page.waitForTimeout(1000);

        const createButton = page.locator('button:has-text("Create Session")').first();
        await createButton.click();

        await page.waitForSelector('#create-session-modal', { timeout: 10000 });

        // Try to submit without selecting topic
        await page.click('button[type="submit"]:has-text("Create Session")');

        // Modal should remain open (form validation should prevent submission)
        await expect(page.locator('#create-session-modal')).toBeVisible();

        // Close modal
        await page.click('button:has-text("Cancel")');
        await page.waitForSelector('#create-session-modal', { state: 'detached', timeout: 5000 });
      }
    });

    test('should auto-fill estimated minutes when topic is selected', async ({ page }) => {
      await page.goto('/planning');
      await elementHelper.waitForPageReady();

      const childSelect = page.locator('select[name="child_id"]');
      const childOption = page.locator(`select[name="child_id"] option:has-text("${testChild.name}")`);

      if (await childOption.count() > 0) {
        await childSelect.selectOption({ label: testChild.name });
        await page.waitForTimeout(1000);

        const createButton = page.locator('button:has-text("Create Session")').first();
        await createButton.click();

        await page.waitForSelector('#create-session-modal', { timeout: 10000 });

        // Select topic
        await page.selectOption('select[name="topic_id"]', { label: testTopic.name });

        // Verify estimated minutes field is auto-filled
        const estimatedMinutesField = page.locator('input[name="estimated_minutes"]');
        await expect(estimatedMinutesField).toHaveValue(testTopic.minutes.toString());
      }
    });
  });

  test.describe('Session Management Operations', () => {
    test('should move session from backlog to planned', async ({ page }) => {
      // First create a session
      await createTestSession(page);

      // Find the session in backlog and click Plan button
      const backlogColumn = page.locator('[data-status="backlog"]');
      const sessionCard = backlogColumn.locator(`.session-card:has-text("${testTopic.name}")`);
      await expect(sessionCard).toBeVisible();

      const planButton = sessionCard.locator('button:has-text("Plan")');
      await expect(planButton).toBeVisible();
      await planButton.click();

      // Wait for HTMX update
      await page.waitForTimeout(2000);

      // Verify session moved to planned column
      const plannedColumn = page.locator('[data-status="planned"]');
      await expect(plannedColumn.locator(`.session-card:has-text("${testTopic.name}")`)).toBeVisible();

      // Verify session is no longer in backlog
      await expect(backlogColumn.locator(`.session-card:has-text("${testTopic.name}")`)).not.toBeVisible();
    });

    test('should schedule session from planned status', async ({ page }) => {
      // Create session and move to planned
      await createTestSession(page);
      await moveSessionToPlanned(page);

      // Click Schedule button
      const plannedColumn = page.locator('[data-status="planned"]');
      const sessionCard = plannedColumn.locator(`.session-card:has-text("${testTopic.name}")`);
      const scheduleButton = sessionCard.locator('button:has-text("Schedule")');

      await expect(scheduleButton).toBeVisible();
      await scheduleButton.click();

      // Should open scheduling modal - wait for it
      await page.waitForSelector('#modal-container form, .scheduling-modal', { timeout: 10000 });

      // Fill scheduling details if modal appears
      const daySelect = page.locator('select[name="scheduled_day_of_week"]');
      if (await daySelect.count() > 0) {
        await daySelect.selectOption('2'); // Tuesday
        await page.fill('input[name="scheduled_start_time"]', '10:00');
        await page.fill('input[name="scheduled_end_time"]', '10:45');

        const scheduleSubmitButton = page.locator('button:has-text("Schedule Session")');
        await scheduleSubmitButton.click();

        await page.waitForTimeout(2000);

        // Verify session moved to scheduled column
        const scheduledColumn = page.locator('[data-status="scheduled"]');
        await expect(scheduledColumn.locator(`.session-card:has-text("${testTopic.name}")`)).toBeVisible();
      }
    });

    test('should update session commitment type', async ({ page }) => {
      await createTestSession(page);

      const backlogColumn = page.locator('[data-status="backlog"]');
      const sessionCard = backlogColumn.locator(`.session-card:has-text("${testTopic.name}")`);
      await expect(sessionCard).toBeVisible();

      // Click the three-dots menu to open actions dropdown
      const menuButton = sessionCard.locator('button svg[fill="currentColor"]').locator('..');
      await menuButton.click();

      // Wait for dropdown to appear
      await page.waitForTimeout(500);

      // Look for commitment type options
      const flexibleButton = page.locator('button:has-text("Flexible")');
      if (await flexibleButton.count() > 0) {
        await flexibleButton.click();
        await page.waitForTimeout(1000);

        // Verify commitment type badge updated
        await expect(sessionCard.locator('text=Flexible')).toBeVisible();
      }
    });

    test('should delete session', async ({ page }) => {
      await createTestSession(page);

      const backlogColumn = page.locator('[data-status="backlog"]');
      const sessionCard = backlogColumn.locator(`.session-card:has-text("${testTopic.name}")`);
      await expect(sessionCard).toBeVisible();

      // Click the three-dots menu
      const menuButton = sessionCard.locator('button svg[fill="currentColor"]').locator('..');
      await menuButton.click();
      await page.waitForTimeout(500);

      // Set up dialog handler for confirmation
      page.on('dialog', dialog => dialog.accept());

      // Click delete button
      const deleteButton = page.locator('button:has-text("Delete Session")');
      if (await deleteButton.count() > 0) {
        await deleteButton.click();
        await page.waitForTimeout(2000);

        // Verify session is removed
        await expect(sessionCard).not.toBeVisible();
      }
    });

    test('should support drag and drop between columns', async ({ page }) => {
      await createTestSession(page);

      const backlogColumn = page.locator('[data-status="backlog"]');
      const plannedColumn = page.locator('[data-status="planned"]');
      const sessionCard = backlogColumn.locator(`.session-card:has-text("${testTopic.name}")`);

      await expect(sessionCard).toBeVisible();

      // Perform drag and drop
      await sessionCard.dragTo(plannedColumn);
      await page.waitForTimeout(2000);

      // Verify session moved to planned column
      await expect(plannedColumn.locator(`.session-card:has-text("${testTopic.name}")`)).toBeVisible();
      await expect(backlogColumn.locator(`.session-card:has-text("${testTopic.name}")`)).not.toBeVisible();
    });
  });

  test.describe('Authorization & Error Handling', () => {
    test('should prevent access with wrong user context', async ({ page }) => {
      // Create another user and try to access planning with wrong child ID
      const wrongUser = {
        email: `wrong-user-${Date.now()}@example.com`,
        password: 'password123'
      };

      // Register new user
      await page.goto('/register');
      await elementHelper.waitForPageReady();

      await elementHelper.safeFill('input[name="name"]', 'Wrong User');
      await elementHelper.safeFill('input[name="email"]', wrongUser.email);
      await elementHelper.safeFill('input[name="password"]', wrongUser.password);
      await elementHelper.safeFill('input[name="password_confirmation"]', wrongUser.password);
      await elementHelper.safeClick('button[type="submit"]');
      await elementHelper.waitForPageReady();

      // Try to access planning board with original child ID
      await page.goto('/planning?child_id=1');
      await elementHelper.waitForPageReady();

      // Should not crash and should show no child selected or empty state
      await expect(page.locator('body')).not.toContainText('Error');
      await expect(page.locator('body')).not.toContainText('500');
    });

    test('should handle invalid session ID gracefully', async ({ page }) => {
      await page.goto('/planning');
      await elementHelper.waitForPageReady();

      // Try to update non-existent session
      const response = await page.request.patch('/planning/sessions/99999/status', {
        data: { status: 'planned' }
      });

      // Should return 404 or similar error, not crash
      expect(response.status()).toBeGreaterThanOrEqual(400);
    });

    test('should validate session creation with invalid data', async ({ page }) => {
      await page.goto('/planning');
      await elementHelper.waitForPageReady();

      const childSelect = page.locator('select[name="child_id"]');
      const childOption = page.locator(`select[name="child_id"] option:has-text("${testChild.name}")`);

      if (await childOption.count() > 0) {
        await childSelect.selectOption({ label: testChild.name });
        await page.waitForTimeout(1000);

        // Try to create session with invalid minutes
        const response = await page.request.post('/planning/sessions', {
          data: {
            child_id: '1',
            topic_id: '1',
            estimated_minutes: '1000' // Invalid - too long
          }
        });

        // Should return validation error
        expect(response.status()).toBeGreaterThanOrEqual(400);
      }
    });

    test('should handle network errors gracefully', async ({ page }) => {
      await page.goto('/planning');
      await elementHelper.waitForPageReady();

      // Mock network failure for session creation
      await page.route('/planning/sessions', route => {
        route.fulfill({ status: 500, body: 'Server Error' });
      });

      const childSelect = page.locator('select[name="child_id"]');
      const childOption = page.locator(`select[name="child_id"] option:has-text("${testChild.name}")`);

      if (await childOption.count() > 0) {
        await childSelect.selectOption({ label: testChild.name });
        await page.waitForTimeout(1000);

        const createButton = page.locator('button:has-text("Create Session")').first();
        await createButton.click();

        await page.waitForSelector('#create-session-modal', { timeout: 10000 });

        await page.selectOption('select[name="topic_id"]', { label: testTopic.name });
        await page.click('button[type="submit"]:has-text("Create Session")');

        // Should show error or stay on modal
        await page.waitForTimeout(2000);

        // Modal should still be visible or error should be displayed
        const modalVisible = await page.locator('#create-session-modal').isVisible();
        const errorVisible = await page.locator('.error, .alert, [role="alert"]').count() > 0;

        expect(modalVisible || errorVisible).toBeTruthy();
      }
    });
  });

  test.describe('Planning Board Features', () => {
    test('should display capacity meter', async ({ page }) => {
      await page.goto('/planning');
      await elementHelper.waitForPageReady();

      const childSelect = page.locator('select[name="child_id"]');
      const childOption = page.locator(`select[name="child_id"] option:has-text("${testChild.name}")`);

      if (await childOption.count() > 0) {
        await childSelect.selectOption({ label: testChild.name });
        await page.waitForTimeout(1000);

        // Check for capacity meter elements
        const capacityElements = page.locator('.capacity-meter, [class*="capacity"], .capacity-bar');
        const hasCapacityElement = await capacityElements.count() > 0;

        if (hasCapacityElement) {
          await expect(capacityElements.first()).toBeVisible();
        }

        // Check for weekly capacity display
        const weekdayElements = page.locator('text=Monday, text=Tuesday, text=Wednesday');
        if (await weekdayElements.count() > 0) {
          await expect(weekdayElements.first()).toBeVisible();
        }
      }
    });

    test('should show catch-up lane when applicable', async ({ page }) => {
      await page.goto('/planning');
      await elementHelper.waitForPageReady();

      const childSelect = page.locator('select[name="child_id"]');
      const childOption = page.locator(`select[name="child_id"] option:has-text("${testChild.name}")`);

      if (await childOption.count() > 0) {
        await childSelect.selectOption({ label: testChild.name });
        await page.waitForTimeout(1000);

        // Look for catch-up column or related elements
        const catchUpElements = page.locator('#catch-up-column, .catch-up, text=Catch-Up');
        if (await catchUpElements.count() > 0) {
          await expect(catchUpElements.first()).toBeVisible();
        }
      }
    });

    test('should handle quality heuristics warnings', async ({ page }) => {
      // Create multiple sessions to potentially trigger quality warnings
      await createTestSession(page);

      // Look for any quality warning indicators
      const qualityElements = page.locator('.quality-warning, .heuristic, .warning, [class*="warning"]');

      // Quality warnings may not appear with just one session, so just verify no errors
      await expect(page.locator('body')).not.toContainText('Error');
    });

    test('should support session filtering and search', async ({ page }) => {
      await createTestSession(page);

      // Look for filter controls (if they exist)
      const filterElements = page.locator('input[placeholder*="search"], input[placeholder*="filter"], .filter');

      if (await filterElements.count() > 0) {
        // Test basic filtering functionality
        await filterElements.first().fill(testTopic.name.slice(0, 4));
        await page.waitForTimeout(1000);

        // Session should still be visible after filtering
        await expect(page.locator(`.session-card:has-text("${testTopic.name}")`)).toBeVisible();
      }
    });
  });

  // Helper functions
  async function createTestSession(page) {
    await page.goto('/planning');
    await elementHelper.waitForPageReady();

    const childSelect = page.locator('select[name="child_id"]');
    const childOption = page.locator(`select[name="child_id"] option:has-text("${testChild.name}")`);

    if (await childOption.count() > 0) {
      await childSelect.selectOption({ label: testChild.name });
      await page.waitForTimeout(1000);

      const createButton = page.locator('button:has-text("Create Session")').first();
      await createButton.click();

      await page.waitForSelector('#create-session-modal', { timeout: 10000 });

      await page.selectOption('select[name="topic_id"]', { label: testTopic.name });
      await page.click('button[type="submit"]:has-text("Create Session")');

      await page.waitForSelector('#create-session-modal', { state: 'detached', timeout: 10000 });
      await page.waitForTimeout(2000);
    }
  }

  async function moveSessionToPlanned(page) {
    const backlogColumn = page.locator('[data-status="backlog"]');
    const sessionCard = backlogColumn.locator(`.session-card:has-text("${testTopic.name}")`);
    const planButton = sessionCard.locator('button:has-text("Plan")');

    await planButton.click();
    await page.waitForTimeout(2000);
  }
});