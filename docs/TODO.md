# TODO - Category Disable Feature

## Overview
Add option to disable category when all its questions are inactive, with admin manual control override.

## Implementation Steps

### Step 1: Database - Add status column to categories table
- [x] Add `status` column (enum: 'active', 'inactive') default 'active' to categories table
- [x] Run SQL ALTER TABLE statement

### Step 2: PHP Functions (includes/functions.php)
- [x] Add `getCategoryById($conn, $id)` - get category by ID
- [x] Add `setCategoryStatus($conn, $id, $status)` - update category status
- [x] Add `checkAllQuestionsInactive($conn, $slug)` - check if all questions inactive
- [x] Add `autoDisableCategory($conn, $slug)` - auto-disable if all questions inactive

### Step 3: Auto-Disable Logic
- [x] Modify update.php - check and auto-disable category after question status change
- [x] Handle the case when last active question is disabled

### Step 4: Manual Control (category_edit.php)
- [x] Add status toggle button (Enable/Disable switch)
- [x] Save status when category is updated

### Step 5: cat.php - Filter inactive categories
- [x] Filter categories based on status (hide inactive for non-admin, show all for admin)

### Step 6: category.php - Handle disabled category
- [x] Show message when category is disabled (for non-admin users trying to access direct URL)

### Step 7: categories.php - Show status indicator
- [x] Display status column in categories list
- [x] Add quick toggle button

### Step 8: Test the implementation
- [x] Test auto-disable when all questions become inactive
- [x] Test manual enable/disable override
- [x] Test cat.php visibility rules
- [x] Test direct URL access to disabled category

## Completed
All implementation steps are complete!
