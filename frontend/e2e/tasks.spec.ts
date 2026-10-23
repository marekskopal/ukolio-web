import {expect, test} from '@playwright/test';

import {AddEditProjectPage} from './pages/add-edit-project.page';
import {BoardPage} from './pages/board.page';
import {ProjectsPage} from './pages/projects.page';
import {TaskDrawerPage} from './pages/task-drawer.page';

test.describe('Task CRUD', () => {
    test('create a task, edit it, move it across statuses, then delete it', async ({page}) => {
        const projects = new ProjectsPage(page);
        const projectForm = new AddEditProjectPage(page);
        const board = new BoardPage(page);
        const drawer = new TaskDrawerPage(page);

        const stamp = Date.now();
        const projectName = `Task CRUD ${stamp}`;
        const taskName = `E2E task ${stamp}`;
        const editedName = `${taskName} (edited)`;

        // Seed project
        await projects.goto();
        await projects.gotoNew();
        await projectForm.fillName(projectName);
        await projectForm.submit();
        await projects.openBoard(projectName);
        await board.expectVisible();

        // Create
        await board.openNewTask();
        await drawer.fillName(taskName);
        await drawer.fillDescription('Body for the e2e task.');
        await drawer.save();
        await board.expectTaskInColumn(taskName, 'To Do');

        // Edit (rename via the drawer)
        await board.openTask(taskName);
        await drawer.fillName(editedName);
        await drawer.save();
        await board.expectTaskInColumn(editedName, 'To Do');

        // Move across statuses by changing the status select in the drawer
        await board.openTask(editedName);
        await drawer.selectStatus('In Progress');
        await drawer.save();
        await board.expectTaskInColumn(editedName, 'In Progress');

        await board.openTask(editedName);
        await drawer.selectStatus('Done');
        await drawer.save();
        await board.expectTaskInColumn(editedName, 'Done');

        // Delete
        await board.openTask(editedName);
        await drawer.delete();
        await board.expectTaskAbsent(editedName);

        // Cleanup project
        await projects.goto();
        await projects.deleteProject(projectName);
    });

    test('the open task drawer is reflected in the URL and can be deep-linked', async ({page}) => {
        const projects = new ProjectsPage(page);
        const projectForm = new AddEditProjectPage(page);
        const board = new BoardPage(page);
        const drawer = new TaskDrawerPage(page);

        const stamp = Date.now();
        const projectName = `Task URL ${stamp}`;
        const taskName = `E2E deep link ${stamp}`;

        await projects.goto();
        await projects.gotoNew();
        await projectForm.fillName(projectName);
        await projectForm.submit();
        await projects.openBoard(projectName);
        await board.expectVisible();
        await board.openNewTask();
        await drawer.fillName(taskName);
        await drawer.save();

        // Opening puts the task code in the URL; closing takes it out again.
        await board.openTask(taskName);
        await expect(page).toHaveURL(/[?&]task=[A-Z0-9-]+/);
        const taskUrl = page.url();
        await drawer.cancel();
        await drawer.expectClosed();
        await expect(page).not.toHaveURL(/[?&]task=/);

        // Back reopens it, Forward closes it.
        await page.goBack();
        await drawer.expectOpen();
        await page.goForward();
        await drawer.expectClosed();

        // A pasted task URL opens the drawer directly — on the board and on the Tasks grid.
        await page.goto(taskUrl);
        await drawer.expectOpen();
        await expect(page.locator('#task-name')).toHaveValue(taskName);
        await page.goto('tasks?task=' + new URL(taskUrl).searchParams.get('task'));
        await drawer.expectOpen();
        await expect(page.locator('#task-name')).toHaveValue(taskName);
        await drawer.cancel();
        await expect(page).not.toHaveURL(/[?&]task=/);

        await projects.goto();
        await projects.deleteProject(projectName);
    });
});
