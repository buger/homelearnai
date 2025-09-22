import { test, expect } from '@playwright/test';

test.describe('Complete Workflow: Subject → Unit → Topic → Flashcard', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to registration page and create a new user
    await page.goto('/register');

    // Fill registration form
    await page.fill('[name="name"]', 'Test User');
    await page.fill('[name="email"]', `test-${Date.now()}@example.com`);
    await page.fill('[name="password"]', 'password123');
    await page.fill('[name="password_confirmation"]', 'password123');

    // Submit registration
    await page.click('[type="submit"]');

    // Wait for redirect to dashboard or subjects page
    await page.waitForURL(/\/(dashboard|subjects)/);
  });

  test('should create complete hierarchy: Subject → Unit → Topic → Flashcard', async ({ page }) => {
    // Step 1: Create a Subject
    console.log('Step 1: Creating subject...');

    // Navigate to subjects page if not already there
    if (!page.url().includes('/subjects')) {
      await page.goto('/subjects');
    }

    // Create a new subject
    await page.click('text=Add Subject');
    await page.fill('[name="name"]', 'Mathematics');
    await page.fill('[name="description"]', 'Basic mathematics course');
    await page.click('[type="submit"]');

    // Verify subject was created
    await expect(page.locator('text=Mathematics')).toBeVisible();

    // Step 2: Create a Unit within the Subject
    console.log('Step 2: Creating unit...');

    // Click on the subject to view its details
    await page.click('text=Mathematics');

    // Create a new unit
    await page.click('text=Add Unit');
    await page.fill('[name="name"]', 'Algebra Basics');
    await page.fill('[name="description"]', 'Introduction to algebraic concepts');
    await page.click('[type="submit"]');

    // Verify unit was created and navigate to it
    await expect(page.locator('text=Algebra Basics')).toBeVisible();
    await page.click('text=Algebra Basics');

    // Step 3: Create a Topic within the Unit
    console.log('Step 3: Creating topic...');

    // Create a new topic
    await page.click('text=Add Topic');
    await page.fill('[name="name"]', 'Linear Equations');
    await page.fill('[name="description"]', 'Solving linear equations with one variable');
    await page.fill('[name="estimated_minutes"]', '45');
    await page.check('[name="required"]');
    await page.click('[type="submit"]');

    // Verify topic was created and navigate to it
    await expect(page.locator('text=Linear Equations')).toBeVisible();
    await page.click('text=Linear Equations');

    // Step 4: Create a Flashcard within the Topic
    console.log('Step 4: Creating flashcard...');

    // Create a new flashcard
    await page.click('text=Add Flashcard');

    // Fill flashcard form (basic card type)
    await page.selectOption('[name="card_type"]', 'basic');
    await page.fill('[name="question"]', 'What is the solution to x + 5 = 12?');
    await page.fill('[name="answer"]', 'x = 7');
    await page.fill('[name="hint"]', 'Subtract 5 from both sides');
    await page.selectOption('[name="difficulty_level"]', 'easy');
    await page.fill('[name="tags"]', 'algebra, linear, basic');

    await page.click('[type="submit"]');

    // Verify flashcard was created
    await expect(page.locator('text=What is the solution to x + 5 = 12?')).toBeVisible();

    // Step 5: Verify the complete hierarchy exists
    console.log('Step 5: Verifying complete hierarchy...');

    // Navigate back through the hierarchy to verify all levels exist

    // From flashcard back to topic
    await page.click('text=Back to Topic');
    await expect(page.locator('text=Linear Equations')).toBeVisible();
    await expect(page.locator('text=1 flashcard')).toBeVisible(); // Should show flashcard count

    // From topic back to unit
    await page.click('text=Back to Unit');
    await expect(page.locator('text=Algebra Basics')).toBeVisible();
    await expect(page.locator('text=Linear Equations')).toBeVisible(); // Topic should be listed

    // From unit back to subject
    await page.click('text=Back to Subject');
    await expect(page.locator('text=Mathematics')).toBeVisible();
    await expect(page.locator('text=Algebra Basics')).toBeVisible(); // Unit should be listed

    // Verify breadcrumb navigation works
    console.log('Step 6: Testing breadcrumb navigation...');

    // Navigate back to the flashcard using the hierarchy
    await page.click('text=Algebra Basics');
    await page.click('text=Linear Equations');

    // Verify flashcard is still there
    await expect(page.locator('text=What is the solution to x + 5 = 12?')).toBeVisible();

    console.log('✅ Complete workflow test passed!');
  });

  test('should handle creation failures gracefully', async ({ page }) => {
    // Test error handling during creation process
    console.log('Testing error handling...');

    // Navigate to subjects page
    await page.goto('/subjects');

    // Try to create subject with empty name
    await page.click('text=Add Subject');
    await page.fill('[name="name"]', ''); // Empty name
    await page.click('[type="submit"]');

    // Should show validation error
    await expect(page.locator('text=required')).toBeVisible();

    // Fill valid data and continue
    await page.fill('[name="name"]', 'Test Subject');
    await page.click('[type="submit"]');

    // Navigate to unit creation
    await page.click('text=Test Subject');
    await page.click('text=Add Unit');

    // Try invalid unit data
    await page.fill('[name="name"]', ''); // Empty name
    await page.click('[type="submit"]');
    await expect(page.locator('text=required')).toBeVisible();

    console.log('✅ Error handling test passed!');
  });

  test('should support different flashcard types in topic', async ({ page }) => {
    // Create the hierarchy first
    await page.goto('/subjects');

    // Quick setup of subject, unit, topic
    await page.click('text=Add Subject');
    await page.fill('[name="name"]', 'Science');
    await page.click('[type="submit"]');

    await page.click('text=Science');
    await page.click('text=Add Unit');
    await page.fill('[name="name"]', 'Chemistry');
    await page.click('[type="submit"]');

    await page.click('text=Chemistry');
    await page.click('text=Add Topic');
    await page.fill('[name="name"]', 'Elements');
    await page.fill('[name="estimated_minutes"]', '30');
    await page.click('[type="submit"]');

    await page.click('text=Elements');

    // Test creating different types of flashcards
    console.log('Testing multiple choice flashcard...');

    // Create multiple choice flashcard
    await page.click('text=Add Flashcard');
    await page.selectOption('[name="card_type"]', 'multiple_choice');
    await page.fill('[name="question"]', 'Which of these is a noble gas?');
    await page.fill('[name="choices[0]"]', 'Helium');
    await page.fill('[name="choices[1]"]', 'Oxygen');
    await page.fill('[name="choices[2]"]', 'Nitrogen');
    await page.check('[name="correct_choices[]"][value="0"]'); // Helium is correct
    await page.click('[type="submit"]');

    await expect(page.locator('text=Which of these is a noble gas?')).toBeVisible();

    console.log('Testing true/false flashcard...');

    // Create true/false flashcard
    await page.click('text=Add Flashcard');
    await page.selectOption('[name="card_type"]', 'true_false');
    await page.fill('[name="question"]', 'Water has the chemical formula H2O');
    await page.selectOption('[name="answer"]', 'true');
    await page.click('[type="submit"]');

    await expect(page.locator('text=Water has the chemical formula H2O')).toBeVisible();

    // Verify both flashcards exist in the topic
    await expect(page.locator('text=2 flashcards')).toBeVisible();

    console.log('✅ Multiple flashcard types test passed!');
  });

  test('should maintain data integrity across navigation', async ({ page }) => {
    // Create complete hierarchy
    await page.goto('/subjects');

    await page.click('text=Add Subject');
    await page.fill('[name="name"]', 'History');
    await page.click('[type="submit"]');

    await page.click('text=History');
    await page.click('text=Add Unit');
    await page.fill('[name="name"]', 'World War II');
    await page.click('[type="submit"]');

    await page.click('text=World War II');
    await page.click('text=Add Topic');
    await page.fill('[name="name"]', 'Major Battles');
    await page.fill('[name="estimated_minutes"]', '60');
    await page.click('[type="submit"]');

    await page.click('text=Major Battles');
    await page.click('text=Add Flashcard');
    await page.fill('[name="question"]', 'When did D-Day occur?');
    await page.fill('[name="answer"]', 'June 6, 1944');
    await page.click('[type="submit"]');

    // Navigate away and back multiple times to test data persistence
    await page.goto('/subjects');
    await page.click('text=History');
    await page.click('text=World War II');
    await page.click('text=Major Battles');

    // Data should still be there
    await expect(page.locator('text=When did D-Day occur?')).toBeVisible();

    // Test browser refresh
    await page.reload();
    await expect(page.locator('text=When did D-Day occur?')).toBeVisible();

    console.log('✅ Data integrity test passed!');
  });
});