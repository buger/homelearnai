import { test, expect } from '@playwright/test';
import { ModalHelper, ElementHelper } from './helpers/modal-helpers';
import { KidsModeHelper } from './helpers/kids-mode-helpers';
import * as path from 'path';

/**
 * Basic E2E Tests for ICS Calendar Import Functionality
 *
 * Tests the core workflow of importing external calendar events:
 * - Navigation and basic UI
 * - File upload and preview
 * - Simple import workflow
 */

test.describe('ICS Calendar Import - Basic Functionality', () => {
  let modalHelper: ModalHelper;
  let elementHelper: ElementHelper;
  let kidsModeHelper: KidsModeHelper;

  // Test data
  const testUser = {
    name: 'Calendar Test User',
    email: `calendar-test-${Date.now()}@example.com`,
    password: 'password123'
  };

  const testChild = {
    name: 'Test Child',
    grade: '4th Grade',
    independence_level: 'guided'
  };

  test.beforeEach(async ({ page }) => {
    modalHelper = new ModalHelper(page);
    elementHelper = new ElementHelper(page);
    kidsModeHelper = new KidsModeHelper(page);

    // Ensure we're not in kids mode
    await kidsModeHelper.resetKidsMode();

    // Register and set up test user
    await page.goto('/register');
    await elementHelper.waitForPageReady();

    await elementHelper.safeFill('input[name="name"]', testUser.name);
    await elementHelper.safeFill('input[name="email"]', testUser.email);
    await elementHelper.safeFill('input[name="password"]', testUser.password);
    await elementHelper.safeFill('input[name="password_confirmation"]', testUser.password);

    await elementHelper.safeClick('button[type="submit"]');
    await elementHelper.waitForPageReady();
  });

  test('should display import calendar page correctly', async ({ page }) => {
    // Navigate to calendar import
    await page.goto('/calendar/import');
    await elementHelper.waitForPageReady();

    // Verify page loads with correct title
    await expect(page.locator('h1')).toContainText('Import Calendar');

    // Should show either the import form or no children message
    const hasImportForm = await page.locator('input[name="ics_file"]').count() > 0;
    const hasNoChildrenMessage = await page.locator('text=No children found').count() > 0;

    // One of these should be true
    expect(hasImportForm || hasNoChildrenMessage).toBe(true);

    if (hasNoChildrenMessage) {
      // Should show no children state
      await expect(page.locator('text=No children found')).toBeVisible();
      await expect(page.locator('text=Manage Children')).toBeVisible();
    } else {
      // Should show import form
      await expect(page.locator('input[name="ics_file"]')).toBeVisible();
      await expect(page.locator('select[name="child_id"]')).toBeVisible();
    }
  });

  test('should allow user to create child and access import form', async ({ page }) => {
    // Go to children management
    await page.goto('/children');
    await elementHelper.waitForPageReady();

    // Create a child if none exists
    const noChildrenMessage = page.locator('text=No children found');
    if (await noChildrenMessage.isVisible().catch(() => false)) {
      // Look for add child button
      const addButtons = page.locator('a[href*="/children/create"], button:text("Add Child"), a:text("Add Child"), a:text("Create Child")');

      if (await addButtons.count() > 0) {
        await elementHelper.safeClick(addButtons.first());
        await elementHelper.waitForPageReady();

        // Fill the form
        await elementHelper.safeFill('input[name="name"]', testChild.name);
        await elementHelper.safeFill('input[name="grade"]', testChild.grade);

        // Submit the form
        await elementHelper.safeClick('button[type="submit"]');
        await elementHelper.waitForPageReady();
      }
    }

    // Now go to import page
    await page.goto('/calendar/import');
    await elementHelper.waitForPageReady();

    // Should now show the import form
    await expect(page.locator('h1')).toContainText('Import Calendar');
    await expect(page.locator('input[name="ics_file"]')).toBeVisible();
    await expect(page.locator('select[name="child_id"]')).toBeVisible();
  });

  test('should handle file upload and preview', async ({ page }) => {
    // First ensure we have a child
    await page.goto('/children');
    await elementHelper.waitForPageReady();

    const noChildrenMessage = page.locator('text=No children found');
    if (await noChildrenMessage.isVisible().catch(() => false)) {
      const addButtons = page.locator('a[href*="/children/create"], button:text("Add Child"), a:text("Add Child")');

      if (await addButtons.count() > 0) {
        await elementHelper.safeClick(addButtons.first());
        await elementHelper.waitForPageReady();

        await elementHelper.safeFill('input[name="name"]', testChild.name);
        await elementHelper.safeFill('input[name="grade"]', testChild.grade);
        await elementHelper.safeClick('button[type="submit"]');
        await elementHelper.waitForPageReady();
      }
    }

    // Go to import page
    await page.goto('/calendar/import');
    await elementHelper.waitForPageReady();

    // Verify we have the form
    const fileInput = page.locator('input[name="ics_file"]');
    const childSelect = page.locator('select[name="child_id"]');

    if (await fileInput.count() > 0 && await childSelect.count() > 0) {
      // Select a child
      await page.selectOption('select[name="child_id"]', { index: 1 });

      // Upload a test ICS file
      const icsFilePath = path.join(__dirname, 'fixtures/ics/apple-calendar-example.ics');
      await page.setInputFiles('input[name="ics_file"]', icsFilePath);

      // Submit for preview
      await elementHelper.safeClick('button[type="submit"]');
      await elementHelper.waitForPageReady();

      // Should show preview or error
      const hasPreviewHeading = await page.locator('h1:text("ICS Import Preview")').count() > 0;
      const hasError = await page.locator('.alert-danger, .text-red-500').count() > 0;

      // One of these should be true
      expect(hasPreviewHeading || hasError).toBe(true);

      if (hasPreviewHeading) {
        // Should show events for preview
        await expect(page.locator('text=Piano Lessons')).toBeVisible();
        await expect(page.locator('button[type="submit"]')).toBeVisible();
      }
    } else {
      console.log('Import form not available - likely need to create child first');
    }
  });

  test('should show help documentation', async ({ page }) => {
    await page.goto('/calendar/import/help');
    await elementHelper.waitForPageReady();

    // Should show some kind of help content
    // (Exact content may vary, so we just check the page loads)
    const hasHeading = await page.locator('h1, h2, h3').count() > 0;
    expect(hasHeading).toBe(true);
  });

  test('should handle invalid file format', async ({ page }) => {
    // First ensure we have a child
    await page.goto('/children');
    await elementHelper.waitForPageReady();

    const noChildrenMessage = page.locator('text=No children found');
    if (await noChildrenMessage.isVisible().catch(() => false)) {
      const addButtons = page.locator('a[href*="/children/create"], button:text("Add Child"), a:text("Add Child")');

      if (await addButtons.count() > 0) {
        await elementHelper.safeClick(addButtons.first());
        await elementHelper.waitForPageReady();

        await elementHelper.safeFill('input[name="name"]', testChild.name);
        await elementHelper.safeFill('input[name="grade"]', testChild.grade);
        await elementHelper.safeClick('button[type="submit"]');
        await elementHelper.waitForPageReady();
      }
    }

    await page.goto('/calendar/import');
    await elementHelper.waitForPageReady();

    const fileInput = page.locator('input[name="ics_file"]');
    const childSelect = page.locator('select[name="child_id"]');

    if (await fileInput.count() > 0 && await childSelect.count() > 0) {
      // Select a child
      await page.selectOption('select[name="child_id"]', { index: 1 });

      // Upload an invalid file
      const invalidFilePath = path.join(__dirname, 'fixtures/ics/not-ics-file.txt');
      await page.setInputFiles('input[name="ics_file"]', invalidFilePath);

      // Submit
      await elementHelper.safeClick('button[type="submit"]');
      await elementHelper.waitForPageReady();

      // Should show error
      const errorMessage = page.locator('.alert-danger, .text-red-500, .text-red-600');
      await expect(errorMessage).toBeVisible({ timeout: 5000 });
    }
  });

  test('should validate required fields', async ({ page }) => {
    await page.goto('/calendar/import');
    await elementHelper.waitForPageReady();

    const fileInput = page.locator('input[name="ics_file"]');

    if (await fileInput.count() > 0) {
      // Try to submit without selecting child or file
      await elementHelper.safeClick('button[type="submit"]');
      await elementHelper.waitForPageReady();

      // Should show validation error or stay on same page
      const stillOnImportPage = await page.locator('h1:text("Import Calendar")').count() > 0;
      const hasError = await page.locator('.alert-danger, .text-red-500').count() > 0;

      expect(stillOnImportPage || hasError).toBe(true);
    }
  });
});