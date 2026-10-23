import {provideZonelessChangeDetection} from '@angular/core';
import {TestBed} from '@angular/core/testing';
import {beforeEach, describe, expect, it} from 'vitest';

import {AvatarComponent} from './avatar.component';

describe('AvatarComponent', () => {
    beforeEach(() => {
        TestBed.configureTestingModule({
            imports: [AvatarComponent],
            providers: [provideZonelessChangeDetection()],
        });
    });

    function render(name: string, avatarUrl: string | null): HTMLElement {
        const fixture = TestBed.createComponent(AvatarComponent);
        fixture.componentRef.setInput('name', name);
        fixture.componentRef.setInput('avatarUrl', avatarUrl);
        fixture.detectChanges();
        return fixture.nativeElement as HTMLElement;
    }

    it('renders initials when there is no avatar', () => {
        const el = render('Jane Q. Doe', null);

        expect(el.querySelector('img')).toBeNull();
        expect(el.textContent?.trim()).toBe('JD');
    });

    it('renders the image when an avatar URL is set', () => {
        const el = render('Jane Doe', '/api/avatars/1/abc');

        expect(el.querySelector('img')?.getAttribute('src')).toBe('/api/avatars/1/abc');
        expect(el.textContent?.trim()).toBe('');
    });

    it('falls back to initials when the image fails to load', () => {
        const fixture = TestBed.createComponent(AvatarComponent);
        fixture.componentRef.setInput('name', 'Jane Doe');
        fixture.componentRef.setInput('avatarUrl', '/api/avatars/1/abc');
        fixture.detectChanges();
        const el = fixture.nativeElement as HTMLElement;

        el.querySelector('img')!.dispatchEvent(new Event('error'));
        fixture.detectChanges();

        expect(el.querySelector('img')).toBeNull();
        expect(el.textContent?.trim()).toBe('JD');
    });

    it('retries when the avatar URL changes after a failure', () => {
        const fixture = TestBed.createComponent(AvatarComponent);
        fixture.componentRef.setInput('name', 'Jane Doe');
        fixture.componentRef.setInput('avatarUrl', '/api/avatars/1/old');
        fixture.detectChanges();
        const el = fixture.nativeElement as HTMLElement;
        el.querySelector('img')!.dispatchEvent(new Event('error'));
        fixture.detectChanges();

        fixture.componentRef.setInput('avatarUrl', '/api/avatars/1/new');
        fixture.detectChanges();

        expect(el.querySelector('img')?.getAttribute('src')).toBe('/api/avatars/1/new');
    });
});
