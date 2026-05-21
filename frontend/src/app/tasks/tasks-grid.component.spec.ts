import {provideZonelessChangeDetection} from '@angular/core';
import {TestBed} from '@angular/core/testing';
import {FormControl} from '@angular/forms';
import {OrderDirection, TaskOrderBy} from '@app/models/task';
import {BoardService} from '@app/services/board.service';
import {CurrentUserService} from '@app/services/current-user.service';
import {FieldService} from '@app/services/field.service';
import {RealtimeService} from '@app/services/realtime.service';
import {TagService} from '@app/services/tag.service';
import {TaskService} from '@app/services/task.service';
import {WorkflowService} from '@app/services/workflow.service';
import {WorkspaceService} from '@app/services/workspace.service';
import {provideTranslateStub} from '@app/testing/test-providers';
import {Subject} from 'rxjs';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';

import {TasksGridComponent} from './tasks-grid.component';

interface PageSignal {
    (): number;
    set(value: number): void;
}

interface SelectionSignal<T> {
    (): T[];
    set(value: T[]): void;
}

interface FlagSignal {
    (): boolean;
    set(value: boolean): void;
}

interface SortBySignal {
    (): TaskOrderBy;
    set(value: TaskOrderBy): void;
}

interface DirectionSignal {
    (): OrderDirection;
    set(value: OrderDirection): void;
}

interface GridInternals {
    page: PageSignal;
    pageSize: PageSignal;
    selectedStatusIds: SelectionSignal<number>;
    selectedTagIds: SelectionSignal<number>;
    onlyActive: FlagSignal;
    sortBy: SortBySignal;
    sortDirection: DirectionSignal;
    searchControl: FormControl<string>;
    onSortClick: (column: TaskOrderBy) => void;
    onStatusToggle: (statusId: number, event: Event) => void;
    onTagToggle: (tagId: number, event: Event) => void;
    onOnlyActiveToggle: (event: Event) => void;
    onPageSizeChange: (size: number) => void;
    clearFilters: () => void;
}

function internals(component: TasksGridComponent): GridInternals {
    return component as unknown as GridInternals;
}

function checkboxEvent(checked: boolean): Event {
    return {target: {checked}} as unknown as Event;
}

function createComponent(): TasksGridComponent {
    TestBed.configureTestingModule({
        providers: [
            provideZonelessChangeDetection(),
            provideTranslateStub(),
            {provide: TaskService, useValue: {
                getTasks: vi.fn().mockResolvedValue({tasks: [], count: 0}),
                getTask: vi.fn().mockResolvedValue(null),
            }},
            {provide: WorkflowService, useValue: {getWorkflows: vi.fn().mockResolvedValue([])}},
            {provide: BoardService, useValue: {getBoard: vi.fn().mockResolvedValue({statuses: []})}},
            {provide: FieldService, useValue: {listProjectFields: vi.fn().mockResolvedValue([])}},
            {provide: TagService, useValue: {loadWorkspaceTags: vi.fn().mockResolvedValue([])}},
            {provide: WorkspaceService, useValue: {currentWorkspaceId: vi.fn(() => null)}},
            {provide: CurrentUserService, useValue: {load: vi.fn().mockResolvedValue({currentWorkspaceId: null})}},
            {provide: RealtimeService, useValue: {events$: new Subject()}},
        ],
    });
    return TestBed.createComponent(TasksGridComponent).componentInstance;
}

describe('TasksGridComponent', () => {
    beforeEach(() => {
        TestBed.resetTestingModule();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('onPageSizeChange resets the page to 1', () => {
        const inner = internals(createComponent());
        inner.page.set(4);

        inner.onPageSizeChange(100);

        expect(inner.page()).toBe(1);
        expect(inner.pageSize()).toBe(100);
    });

    it('onSortClick resets the page to 1 when switching columns', () => {
        const inner = internals(createComponent());
        inner.page.set(4);

        inner.onSortClick('name');

        expect(inner.page()).toBe(1);
        expect(inner.sortBy()).toBe('name');
    });

    it('onSortClick resets the page to 1 when toggling direction on the same column', () => {
        const inner = internals(createComponent());
        inner.page.set(3);
        const initial = inner.sortDirection();

        inner.onSortClick(inner.sortBy());

        expect(inner.page()).toBe(1);
        expect(inner.sortDirection()).not.toBe(initial);
    });

    it('onStatusToggle resets the page to 1', () => {
        const inner = internals(createComponent());
        inner.page.set(5);

        inner.onStatusToggle(7, checkboxEvent(true));

        expect(inner.page()).toBe(1);
        expect(inner.selectedStatusIds()).toContain(7);
    });

    it('onTagToggle resets the page to 1', () => {
        const inner = internals(createComponent());
        inner.page.set(5);

        inner.onTagToggle(9, checkboxEvent(true));

        expect(inner.page()).toBe(1);
        expect(inner.selectedTagIds()).toContain(9);
    });

    it('onOnlyActiveToggle resets the page to 1', () => {
        const inner = internals(createComponent());
        inner.page.set(2);

        inner.onOnlyActiveToggle(checkboxEvent(true));

        expect(inner.page()).toBe(1);
        expect(inner.onlyActive()).toBe(true);
    });

    it('clearFilters resets the page to 1 and clears every active filter', () => {
        const inner = internals(createComponent());
        inner.page.set(6);
        inner.selectedStatusIds.set([1, 2]);
        inner.selectedTagIds.set([3]);
        inner.onlyActive.set(true);
        inner.searchControl.setValue('something');

        inner.clearFilters();

        expect(inner.page()).toBe(1);
        expect(inner.selectedStatusIds()).toEqual([]);
        expect(inner.selectedTagIds()).toEqual([]);
        expect(inner.onlyActive()).toBe(false);
        expect(inner.searchControl.value).toBe('');
    });

    it('search input resets the page to 1 after the 300 ms debounce settles', () => {
        vi.useFakeTimers();
        const inner = internals(createComponent());
        inner.page.set(4);

        inner.searchControl.setValue('alpha');
        expect(inner.page()).toBe(4);

        vi.advanceTimersByTime(300);

        expect(inner.page()).toBe(1);
    });
});
