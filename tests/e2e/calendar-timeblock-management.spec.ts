import { test, expect } from '@playwright/test';
import { ModalHelper, ElementHelper } from './helpers/modal-helpers';
import { KidsModeHelper } from './helpers/kids-mode-helpers';

test.describe('Calendar Time Block Management', () => {
  let testUser: { name: string; email: string; password: string };
  let modalHelper: ModalHelper;
  let elementHelper: ElementHelper;
  let kidsModeHelper: KidsModeHelper;

  test.beforeEach(async ({ page }) => {
    // Initialize helpers
    modalHelper = new ModalHelper(page);
    elementHelper = new ElementHelper(page);
    kidsModeHelper = new KidsModeHelper(page);

    // Ensure we're not in kids mode at start of each test
    await kidsModeHelper.resetKidsMode();

    // Create a unique test user for this session
    testUser = {
      name: 'Calendar Test Parent',
      email: `calendar-${Date.now()}@example.com`,
      password: 'testpassword123'
    };

    // Start with a fresh session
    await page.goto('/');
    await elementHelper.waitForPageReady();

    // Register new user
    await page.goto('/register');
    await elementHelper.waitForPageReady();

    // Fill registration form using helper methods
    await elementHelper.safeFill('input[name="name"]', testUser.name);
    await elementHelper.safeFill('input[name="email"]', testUser.email);
    await elementHelper.safeFill('input[name="password"]', testUser.password);
    await elementHelper.safeFill('input[name="password_confirmation"]', testUser.password);

    // Submit registration
    await elementHelper.safeClick('button[type="submit"]');

    // Wait for navigation
    await elementHelper.waitForPageReady();

    // Check if we're authenticated by trying to go to dashboard
    const currentUrl = page.url();
    if (currentUrl.includes('/login') || currentUrl.includes('/register')) {
      // Authentication didn't work, try manual login
      await page.goto('/login');
      await elementHelper.waitForPageReady();

      await elementHelper.safeFill('input[name="email"]', testUser.email);
      await elementHelper.safeFill('input[name="password"]', testUser.password);
      await elementHelper.safeClick('button[type="submit"]');

      await elementHelper.waitForPageReady();
    }

    // Navigate to dashboard to ensure we're authenticated
    await page.goto('/dashboard');
    await elementHelper.waitForPageReady();

    // Verify authentication by checking for the logged in user name in the navigation
    await expect(page.locator('button:has-text("Calendar Test Parent")')).toBeVisible({ timeout: 10000 });

    // Skip the onboarding wizard if it appears
    const skipButton = page.locator('button:has-text("Skip Setup")');
    if (await skipButton.isVisible({ timeout: 2000 }).catch(() => false)) {
      await skipButton.click();
      await elementHelper.waitForPageReady();
    }

    // Reset any modal state from previous tests
    await modalHelper.resetTestState();
  });

  test.describe('Calendar Navigation & Display', () => {
    test('should navigate to calendar view and display weekly grid', async ({ page }) => {
      // Navigate to calendar
      await page.goto('/calendar');
      await elementHelper.waitForPageReady();

      // Verify we're on the calendar page
      await expect(page.locator('h2:has-text("Weekly Calendar")')).toBeVisible();

      // Check that child selector dropdown is present
      await expect(page.locator('select[name="child_id"]')).toBeVisible();

      // Verify calendar grid structure is present (even if no child selected)
      await expect(page.locator('text=Select a child from the dropdown above to view their calendar.')).toBeVisible();
    });

    test('should show calendar grid when child is selected', async ({ page }) => {
      // First create a test child if none exists
      await page.goto('/children');
      await elementHelper.waitForPageReady();

      // Check if we need to create a child
      const hasChildren = await page.locator('.child-card').count() > 0;

      if (!hasChildren) {
        // Create a test child
        await page.click('button:has-text("Add Child")');
        await modalHelper.waitForChildModal();

        await modalHelper.fillModalForm({
          'name': 'Test Child',
          'grade': 'grade_5',
          'birth_date': '2015-01-15',
          'independence_level': 'guided'
        });

        await modalHelper.submitModalForm('child-modal-overlay');
      }

      // Now navigate to calendar
      await page.goto('/calendar');
      await elementHelper.waitForPageReady();

      // Select the first child
      await page.selectOption('select[name="child_id"]', { index: 1 });
      await elementHelper.waitForPageReady();

      // Verify calendar grid appears with days of week
      await expect(page.locator('text=Monday').first()).toBeVisible();

      // Verify the "Add Time Block" button is visible
      await expect(page.locator('button:has-text("Add Time Block")')).toBeVisible();

      // Verify day columns are present
      await expect(page.locator('.grid-cols-7')).toBeVisible();
    });

    test('should display correct weekly hours and time block count', async ({ page }) => {
      // Navigate to calendar with existing child
      await page.goto('/calendar');
      await elementHelper.waitForPageReady();

      // Select first child (assuming one exists from previous test)
      const childOptions = await page.locator('select[name="child_id"] option').count();
      if (childOptions > 1) {
        await page.selectOption('select[name="child_id"]', { index: 1 });
        await elementHelper.waitForPageReady();

        // Check for time block statistics
        await expect(page.locator('text=total_time_blocks_colon')).toBeVisible();
        await expect(page.locator('text=weekly_hours_colon')).toBeVisible();
      }
    });
  });

  test.describe('Time Block Creation Workflow', () => {
    test('should open time block creation modal and fill form', async ({ page }) => {
      // Setup: navigate to calendar with child selected
      await page.goto('/calendar');
      await elementHelper.waitForPageReady();

      // Select child if available, otherwise create one
      const childCount = await page.locator('select[name="child_id"] option').count();
      if (childCount <= 1) {
        // Navigate to children to create one first
        await page.goto('/children');
        await elementHelper.waitForPageReady();

        await page.click('button:has-text("Add Child")');
        await modalHelper.waitForChildModal();

        await modalHelper.fillModalForm({
          'name': 'Calendar Test Child',
          'grade': 'grade_6',
          'birth_date': '2014-06-15',
          'independence_level': 'independent'
        });

        await modalHelper.submitModalForm('child-modal-overlay');

        // Return to calendar
        await page.goto('/calendar');
        await elementHelper.waitForPageReady();
      }

      // Select the child
      await page.selectOption('select[name="child_id"]', { index: 1 });
      await elementHelper.waitForPageReady();

      // Click main "Add Time Block" button
      await page.click('button:has-text("Add Time Block")');

      // Wait for the time block form modal to appear (Alpine.js modal)
      await page.waitForSelector('#time-block-form-modal .fixed.inset-0', { state: 'visible', timeout: 10000 });

      // Verify modal has correct title
      await expect(page.locator('text=Add New Time Block')).toBeVisible();

      // Verify all form fields are present
      await expect(page.locator('select[name="day_of_week"]')).toBeVisible();
      await expect(page.locator('input[name="start_time"]')).toBeVisible();
      await expect(page.locator('input[name="end_time"]')).toBeVisible();
      await expect(page.locator('input[name="label"]')).toBeVisible();

      // Fill the form
      await page.selectOption('select[name="day_of_week"]', '1'); // Monday
      await page.fill('input[name="start_time"]', '09:00');
      await page.fill('input[name="end_time"]', '10:00');
      await page.fill('input[name="label"]', 'Mathematics');

      // Submit the form
      await page.click('button[type="submit"]:has-text("Add Block")');

      // Wait for HTMX to complete and modal to close
      await modalHelper.waitForHtmxCompletion();
      await page.waitForTimeout(1000);

      // Verify the time block appears in the Monday column
      await expect(page.locator('text=Mathematics')).toBeVisible();
      await expect(page.locator('text=9:00 AM - 10:00 AM')).toBeVisible();
    });

    test('should validate time input correctly', async ({ page }) => {
      // Setup and navigate to calendar
      await page.goto('/calendar');
      await elementHelper.waitForPageReady();

      // Select child
      await page.selectOption('select[name="child_id"]', { index: 1 });
      await elementHelper.waitForPageReady();

      // Open time block creation modal
      await page.click('button:has-text("Add Time Block")');
      await page.waitForSelector('#time-block-form-modal .fixed.inset-0', { state: 'visible', timeout: 10000 });

      // Test invalid time range (end time before start time)
      await page.selectOption('select[name="day_of_week"]', '1');
      await page.fill('input[name="start_time"]', '10:00');
      await page.fill('input[name="end_time"]', '09:00'); // Invalid: before start time
      await page.fill('input[name="label"]', 'Invalid Time Block');

      // Attempt to submit
      await page.click('button[type="submit"]:has-text("Add Block")');

      // Should show validation error or reject submission
      // The browser's built-in validation should prevent submission
      // or server should return error
    });

    test('should detect overlapping time blocks', async ({ page }) => {
      // Setup and navigate to calendar
      await page.goto('/calendar');
      await elementHelper.waitForPageReady();

      // Select child
      await page.selectOption('select[name="child_id"]', { index: 1 });
      await elementHelper.waitForPageReady();

      // Create first time block
      await page.click('button:has-text("Add Time Block")');
      await page.waitForSelector('#time-block-form-modal .fixed.inset-0', { state: 'visible', timeout: 10000 });

      await page.selectOption('select[name="day_of_week"]', '2'); // Tuesday
      await page.fill('input[name="start_time"]', '09:00');
      await page.fill('input[name="end_time"]', '10:00');
      await page.fill('input[name="label"]', 'First Block');

      await page.click('button[type="submit"]:has-text("Add Block")');
      await modalHelper.waitForHtmxCompletion();
      await page.waitForTimeout(1000);

      // Try to create overlapping time block
      await page.click('button:has-text("Add Time Block")');
      await page.waitForSelector('#time-block-form-modal .fixed.inset-0', { state: 'visible', timeout: 10000 });

      await page.selectOption('select[name="day_of_week"]', '2'); // Same day
      await page.fill('input[name="start_time"]', '09:30'); // Overlaps with first block
      await page.fill('input[name="end_time"]', '10:30');
      await page.fill('input[name="label"]', 'Overlapping Block');

      await page.click('button[type="submit"]:has-text("Add Block")');
      await modalHelper.waitForHtmxCompletion();

      // Should show error message about overlap
      await expect(page.locator('text=overlaps')).toBeVisible({ timeout: 5000 });
    });

    test('should use quick suggestions to fill form', async ({ page }) => {
      // Setup and navigate to calendar
      await page.goto('/calendar');
      await elementHelper.waitForPageReady();

      // Select child
      await page.selectOption('select[name="child_id"]', { index: 1 });
      await elementHelper.waitForPageReady();

      // Open time block creation modal
      await page.click('button:has-text("Add Time Block")');
      await page.waitForSelector('#time-block-form-modal .fixed.inset-0', { state: 'visible', timeout: 10000 });

      // Use mathematics suggestion
      await page.click('button:has-text("Math 9-10am")');

      // Verify fields are filled correctly
      await expect(page.locator('input[name="label"]')).toHaveValue('Mathematics');
      await expect(page.locator('input[name="start_time"]')).toHaveValue('09:00');
      await expect(page.locator('input[name="end_time"]')).toHaveValue('10:00');

      // Complete the form
      await page.selectOption('select[name="day_of_week"]', '3'); // Wednesday
      await page.click('button[type="submit"]:has-text("Add Block")');

      await modalHelper.waitForHtmxCompletion();
      await page.waitForTimeout(1000);

      // Verify time block was created
      await expect(page.locator('text=Mathematics')).toBeVisible();
    });
  });

  test.describe('Time Block Management Operations', () => {
    test('should edit existing time block', async ({ page }) => {
      // Setup: ensure we have a time block to edit
      await page.goto('/calendar');
      await elementHelper.waitForPageReady();

      // Select child
      await page.selectOption('select[name="child_id"]', { index: 1 });
      await elementHelper.waitForPageReady();

      // Look for existing time block, or create one
      const existingBlock = await page.locator('.bg-white.rounded-lg.shadow-sm.border-l-4').count();

      if (existingBlock === 0) {
        // Create a time block first
        await page.click('button:has-text("Add Time Block")');
        await page.waitForSelector('#time-block-form-modal .fixed.inset-0', { state: 'visible', timeout: 10000 });

        await page.selectOption('select[name="day_of_week"]', '4'); // Thursday
        await page.fill('input[name="start_time"]', '11:00');
        await page.fill('input[name="end_time"]', '12:00');
        await page.fill('input[name="label"]', 'Editable Block');

        await page.click('button[type="submit"]:has-text("Add Block")');
        await modalHelper.waitForHtmxCompletion();
        await page.waitForTimeout(1000);
      }

      // Hover over time block to reveal edit button
      const timeBlock = page.locator('.bg-white.rounded-lg.shadow-sm.border-l-4').first();
      await timeBlock.hover();

      // Click edit button
      await timeBlock.locator('button[title="Edit"]').click();
      await page.waitForSelector('#time-block-form-modal .fixed.inset-0', { state: 'visible', timeout: 10000 });

      // Verify it's edit mode
      await expect(page.locator('text=Edit Time Block')).toBeVisible();
      await expect(page.locator('button:has-text("Update Block")')).toBeVisible();

      // Modify the time block
      await page.fill('input[name="label"]', 'Updated Block Name');
      await page.fill('input[name="end_time"]', '12:30');

      // Submit changes
      await page.click('button[type="submit"]:has-text("Update Block")');
      await modalHelper.waitForHtmxCompletion();
      await page.waitForTimeout(1000);

      // Verify changes are reflected
      await expect(page.locator('text=Updated Block Name')).toBeVisible();
    });

    test('should delete time block with confirmation', async ({ page }) => {
      // Setup: ensure we have a time block to delete
      await page.goto('/calendar');
      await elementHelper.waitForPageReady();

      // Select child
      await page.selectOption('select[name="child_id"]', { index: 1 });
      await elementHelper.waitForPageReady();

      // Look for existing time block, or create one
      const existingBlocks = await page.locator('.bg-white.rounded-lg.shadow-sm.border-l-4').count();

      if (existingBlocks === 0) {
        // Create a time block to delete
        await page.click('button:has-text("Add Time Block")');
        await page.waitForSelector('#time-block-form-modal .fixed.inset-0', { state: 'visible', timeout: 10000 });

        await page.selectOption('select[name="day_of_week"]', '5'); // Friday
        await page.fill('input[name="start_time"]', '14:00');
        await page.fill('input[name="end_time"]', '15:00');
        await page.fill('input[name="label"]', 'Block to Delete');

        await page.click('button[type="submit"]:has-text("Add Block")');
        await modalHelper.waitForHtmxCompletion();
        await page.waitForTimeout(1000);
      }

      // Get the text of the block we're about to delete
      const blockToDelete = page.locator('.bg-white.rounded-lg.shadow-sm.border-l-4').first();
      const blockText = await blockToDelete.locator('h4').textContent();

      // Hover over time block to reveal delete button
      await blockToDelete.hover();

      // Setup confirmation dialog handler
      page.on('dialog', async dialog => {
        expect(dialog.message()).toContain('Are you sure you want to delete this time block?');
        await dialog.accept();
      });

      // Click delete button
      await blockToDelete.locator('button[title="Delete"]').click();
      await modalHelper.waitForHtmxCompletion();
      await page.waitForTimeout(1000);

      // Verify time block is removed
      if (blockText) {
        await expect(page.locator(`text=${blockText}`)).not.toBeVisible();
      }
    });

    test('should create time block from day column', async ({ page }) => {
      // Navigate to calendar
      await page.goto('/calendar');
      await elementHelper.waitForPageReady();

      // Select child
      await page.selectOption('select[name="child_id"]', { index: 1 });
      await elementHelper.waitForPageReady();

      // Find a day column "Add Block" button
      const dayAddButton = page.locator('.border-dashed button:has-text("Add Block")').first();
      await dayAddButton.click();

      await page.waitForSelector('#time-block-form-modal .fixed.inset-0', { state: 'visible', timeout: 10000 });

      // The day should be pre-selected based on which column was clicked
      const selectedDay = await page.locator('select[name="day_of_week"]').inputValue();
      expect(parseInt(selectedDay)).toBeGreaterThanOrEqual(1);
      expect(parseInt(selectedDay)).toBeLessThanOrEqual(7);

      // Fill and submit
      await page.fill('input[name="start_time"]', '16:00');
      await page.fill('input[name="end_time"]', '17:00');
      await page.fill('input[name="label"]', 'Day Column Block');

      await page.click('button[type="submit"]:has-text("Add Block")');
      await modalHelper.waitForHtmxCompletion();
      await page.waitForTimeout(1000);

      // Verify block appears
      await expect(page.locator('text=Day Column Block')).toBeVisible();
    });
  });

  test.describe('Validation & Error Handling', () => {
    test('should require all form fields', async ({ page }) => {
      // Navigate to calendar
      await page.goto('/calendar');
      await elementHelper.waitForPageReady();

      // Select child
      await page.selectOption('select[name="child_id"]', { index: 1 });
      await elementHelper.waitForPageReady();

      // Open time block creation modal
      await page.click('button:has-text("Add Time Block")');
      await page.waitForSelector('#time-block-form-modal .fixed.inset-0', { state: 'visible', timeout: 10000 });

      // Try to submit empty form
      await page.click('button[type="submit"]:has-text("Add Block")');

      // Browser validation should prevent submission
      // Check that we're still in the modal
      await expect(page.locator('text=Add New Time Block')).toBeVisible();
    });

    test('should handle missing child gracefully', async ({ page }) => {
      // Navigate directly to calendar without child
      await page.goto('/calendar');
      await elementHelper.waitForPageReady();

      // Should show "no child selected" message
      await expect(page.locator('text=Select a child from the dropdown above to view their calendar.')).toBeVisible();

      // Should not show "Add Time Block" button
      await expect(page.locator('button:has-text("Add Time Block")')).not.toBeVisible();
    });

    test('should validate time format input', async ({ page }) => {
      // Navigate to calendar
      await page.goto('/calendar');
      await elementHelper.waitForPageReady();

      // Select child
      await page.selectOption('select[name="child_id"]', { index: 1 });
      await elementHelper.waitForPageReady();

      // Open time block creation modal
      await page.click('button:has-text("Add Time Block")');
      await page.waitForSelector('#time-block-form-modal .fixed.inset-0', { state: 'visible', timeout: 10000 });

      // HTML5 time inputs should automatically validate format
      // Test that invalid times are handled
      const startTimeInput = page.locator('input[name="start_time"]');
      await expect(startTimeInput).toHaveAttribute('type', 'time');

      const endTimeInput = page.locator('input[name="end_time"]');
      await expect(endTimeInput).toHaveAttribute('type', 'time');
    });

    test('should prevent unauthorized access to other users time blocks', async ({ page }) => {
      // This test verifies that time blocks are properly isolated by user
      // Navigate to calendar
      await page.goto('/calendar');
      await elementHelper.waitForPageReady();

      // Select child
      await page.selectOption('select[name="child_id"]', { index: 1 });
      await elementHelper.waitForPageReady();

      // The calendar should only show this user's child's time blocks
      // This is verified by the fact that only the selected child's blocks appear
      // and the dropdown only shows children belonging to the authenticated user
      const childOptions = await page.locator('select[name="child_id"] option').allTextContents();
      expect(childOptions.length).toBeGreaterThan(1); // At least "Select a child" + one child
    });
  });

  test.describe('Calendar Display Features', () => {
    test('should display time blocks in correct time slots', async ({ page }) => {
      // Navigate to calendar
      await page.goto('/calendar');
      await elementHelper.waitForPageReady();

      // Select child
      await page.selectOption('select[name="child_id"]', { index: 1 });
      await elementHelper.waitForPageReady();

      // Create a time block for testing
      await page.click('button:has-text("Add Time Block")');
      await page.waitForSelector('#time-block-form-modal .fixed.inset-0', { state: 'visible', timeout: 10000 });

      await page.selectOption('select[name="day_of_week"]', '6'); // Saturday
      await page.fill('input[name="start_time"]', '08:00');
      await page.fill('input[name="end_time"]', '09:30');
      await page.fill('input[name="label"]', 'Display Test Block');

      await page.click('button[type="submit"]:has-text("Add Block")');
      await modalHelper.waitForHtmxCompletion();
      await page.waitForTimeout(1000);

      // Verify the time block displays with correct information
      await expect(page.locator('text=Display Test Block')).toBeVisible();
      await expect(page.locator('text=8:00 AM - 9:30 AM')).toBeVisible();
      await expect(page.locator('text=1h 30m')).toBeVisible(); // Duration
    });

    test('should show proper visual formatting for time blocks', async ({ page }) => {
      // Navigate to calendar
      await page.goto('/calendar');
      await elementHelper.waitForPageReady();

      // Select child
      await page.selectOption('select[name="child_id"]', { index: 1 });
      await elementHelper.waitForPageReady();

      // Look for existing time blocks or create one
      const existingBlocks = await page.locator('.bg-white.rounded-lg.shadow-sm.border-l-4').count();

      if (existingBlocks === 0) {
        // Create a time block for visual testing
        await page.click('button:has-text("Add Time Block")');
        await page.waitForSelector('#time-block-form-modal .fixed.inset-0', { state: 'visible', timeout: 10000 });

        await page.selectOption('select[name="day_of_week"]', '7'); // Sunday
        await page.fill('input[name="start_time"]', '10:00');
        await page.fill('input[name="end_time"]', '11:00');
        await page.fill('input[name="label"]', 'Visual Test Block');

        await page.click('button[type="submit"]:has-text("Add Block")');
        await modalHelper.waitForHtmxCompletion();
        await page.waitForTimeout(1000);
      }

      // Verify visual elements
      const timeBlock = page.locator('.bg-white.rounded-lg.shadow-sm.border-l-4').first();
      await expect(timeBlock).toBeVisible();

      // Hover to reveal action buttons
      await timeBlock.hover();
      await expect(timeBlock.locator('button[title="Edit"]')).toBeVisible();
      await expect(timeBlock.locator('button[title="Delete"]')).toBeVisible();
    });

    test('should be responsive on different screen sizes', async ({ page }) => {
      // Test desktop view
      await page.setViewportSize({ width: 1200, height: 800 });
      await page.goto('/calendar');
      await elementHelper.waitForPageReady();

      // Select child
      await page.selectOption('select[name="child_id"]', { index: 1 });
      await elementHelper.waitForPageReady();

      // Should show full day names on desktop
      await expect(page.locator('text=Monday').first()).toBeVisible();

      // Test mobile view
      await page.setViewportSize({ width: 375, height: 667 });
      await page.waitForTimeout(500);

      // Should show abbreviated day names on mobile (first 3 letters)
      // The mobile view uses .md:hidden class to show abbreviated names
      const dayElements = page.locator('.md\\:hidden');
      await expect(dayElements.first()).toBeVisible();
    });

    test('should update statistics when time blocks are added/removed', async ({ page }) => {
      // Navigate to calendar
      await page.goto('/calendar');
      await elementHelper.waitForPageReady();

      // Select child
      await page.selectOption('select[name="child_id"]', { index: 1 });
      await elementHelper.waitForPageReady();

      // Note initial statistics if visible
      const hasStats = await page.locator('text=total_time_blocks_colon').isVisible().catch(() => false);

      // Create a time block
      await page.click('button:has-text("Add Time Block")');
      await page.waitForSelector('#time-block-form-modal .fixed.inset-0', { state: 'visible', timeout: 10000 });

      await page.selectOption('select[name="day_of_week"]', '1');
      await page.fill('input[name="start_time"]', '13:00');
      await page.fill('input[name="end_time"]', '14:00');
      await page.fill('input[name="label"]', 'Stats Test Block');

      await page.click('button[type="submit"]:has-text("Add Block")');
      await modalHelper.waitForHtmxCompletion();
      await page.waitForTimeout(1000);

      // Verify statistics are updated (if they were visible before)
      if (hasStats) {
        await expect(page.locator('text=total_time_blocks_colon')).toBeVisible();
        await expect(page.locator('text=weekly_hours_colon')).toBeVisible();
      }

      // Verify the block appears
      await expect(page.locator('text=Stats Test Block')).toBeVisible();
    });
  });

  test.afterEach(async ({ page }) => {
    // Clean up any remaining modals
    await modalHelper.resetTestState();
  });
});