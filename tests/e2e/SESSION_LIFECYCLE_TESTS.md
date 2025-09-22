# Session Lifecycle Complete E2E Tests

This document describes the comprehensive E2E test suite for validating the complete session lifecycle workflow in the homeschool learning management application.

## Test File
- **Location**: `tests/e2e/session-lifecycle-complete.spec.ts`
- **Test Count**: 7 comprehensive test scenarios
- **Focus**: End-to-end session workflow from creation to completion

## Test Coverage

### 1. Complete Session Creation to Completion Workflow
**Purpose**: Tests the full lifecycle of a learning session from creation through completion.

**Workflow Tested**:
- Session creation from available topics on planning board
- Status transitions: backlog → planned → scheduled → done
- Calendar scheduling integration
- Session execution and completion with evidence
- Automatic review generation
- Performance tracking updates

**Key Validations**:
- Session appears in correct planning board columns
- Calendar integration shows scheduled sessions
- Review system automatically generates spaced repetition entries
- Dashboard metrics update with completed sessions

### 2. Session Scheduling Features and Conflict Detection
**Purpose**: Tests session scheduling capabilities and commitment type handling.

**Features Tested**:
- Different commitment types (fixed, preferred, flexible)
- Session rescheduling functionality
- Session unscheduling (moving back to planned)
- Time slot validation

**Key Validations**:
- Commitment type badges display correctly
- Rescheduling preserves session data
- Unscheduling moves sessions back to planned status

### 3. Session Skip and Catch-up System
**Purpose**: Tests the skip functionality and catch-up session management.

**Features Tested**:
- Skipping scheduled sessions with reasons
- Automatic catch-up session creation
- Catch-up redistribution and prioritization
- Missed session tracking

**Key Validations**:
- Catch-up sessions appear in dedicated section
- Skip reasons are properly recorded
- Redistribution creates new scheduling opportunities

### 4. Cross-System Integration Validation
**Purpose**: Validates data synchronization across all system components.

**Systems Tested**:
- Planning Board ↔ Calendar integration
- Session completion → Review system
- Progress tracking → Dashboard metrics
- Subject/Unit progress updates

**Key Validations**:
- Session state changes reflect across all views
- Completion triggers review generation
- Progress bars and metrics update correctly
- Cross-navigation maintains data consistency

### 5. Bulk Session Operations
**Purpose**: Tests handling of multiple sessions and bulk operations.

**Features Tested**:
- Multiple session creation
- Bulk status updates
- Bulk scheduling operations
- Performance with larger datasets

### 6. Performance and Timing Validation
**Purpose**: Ensures acceptable performance benchmarks for session operations.

**Metrics Tested**:
- Session creation time (< 5 seconds)
- Status transition time (< 3 seconds)
- Form loading time (< 3 seconds)
- Overall test execution time monitoring

**Performance Expectations**:
- Session creation: ≤ 5000ms
- Status changes: ≤ 3000ms
- Form loads: ≤ 3000ms

### 7. Error Recovery Scenarios
**Purpose**: Tests system resilience and error handling capabilities.

**Scenarios Tested**:
- Invalid scheduling data handling
- Network interruption recovery
- Partial session completion recovery
- System conflict resolution

## Running the Tests

### Run All Session Lifecycle Tests
```bash
npm run test:e2e -- session-lifecycle-complete.spec.ts
```

### Run Specific Test
```bash
npm run test:e2e -- --grep "Complete Session Creation to Completion Workflow"
```

### Run with Browser Visible (for debugging)
```bash
npm run test:e2e:headed -- session-lifecycle-complete.spec.ts
```

### Run Single Test for Development
```bash
npm run test:e2e -- --grep "Cross-System Integration" session-lifecycle-complete.spec.ts
```

## Test Architecture

### Data Setup
- **User Creation**: Unique timestamped test users for isolation
- **Data Hierarchy**: Child → Subject → Unit → Topic → Session
- **API Integration**: Reliable data creation via API calls with UI fallbacks

### Selector Strategy
- **Session Cards**: `.session-card` with content filtering
- **Status Columns**: `[data-status="backlog|planned|scheduled|done"]`
- **Action Buttons**: Text-based selectors (`button:has-text("schedule")`)
- **Modal Handling**: `#modal-container` with HTMX loading detection

### Error Handling
- **Graceful Degradation**: API creation with UI fallbacks
- **Robust Waiting**: HTMX completion detection
- **Content Validation**: Text-based assertions for reliability

## Integration Points

### Planning Board Controller
- Session creation via `/planning/sessions`
- Status updates via `/planning/sessions/{id}/status`
- Scheduling via `/planning/sessions/{id}/schedule`

### Calendar Controller
- Calendar view integration at `/calendar`
- Time block validation and conflict detection

### Review Controller
- Automatic review creation on session completion
- Review queue management at `/reviews`

### Dashboard Controller
- Progress tracking and metrics updates
- Multi-child session management

## Dependencies

### Required Services
- **Local Supabase**: PostgreSQL database with reset capability
- **HTMX**: Dynamic content loading and form submissions
- **Alpine.js**: Client-side interactivity

### Test Helpers
- **ModalHelper**: HTMX modal interaction management
- **ElementHelper**: Safe element interaction methods
- **KidsModeHelper**: Kids mode state management

## Debugging

### Common Issues
1. **Modal Loading**: Check `#modal-container` content population
2. **Session Cards**: Verify `.session-card` with correct topic text
3. **Drag & Drop**: Ensure proper Locator targets for `dragTo`
4. **HTMX Timing**: Wait for request completion before assertions

### Debug Commands
```bash
# Run with debug output
DEBUG=pw:browser npm run test:e2e -- session-lifecycle-complete.spec.ts

# Run specific test with trace
npm run test:e2e -- --trace on --grep "Complete Session Creation"
```

## Maintenance

### Selector Updates
When UI changes occur, update selectors in order of preference:
1. **Data attributes**: `[data-testid="..."]` (most reliable)
2. **CSS classes**: `.session-card`, `.planning-column` (structural)
3. **Text content**: `button:has-text("Schedule")` (user-facing)

### Test Data Cleanup
Tests use timestamped data to avoid conflicts. No manual cleanup required as test database is reset between runs.

## Success Criteria

A successful test run validates:
✅ Complete session workflow from creation to completion
✅ Cross-system data synchronization
✅ User interface responsiveness and reliability
✅ Performance within acceptable benchmarks
✅ Error handling and recovery mechanisms
✅ Integration between planning, calendar, and review systems