import {describe, expect, it} from 'vitest';

import {initials} from './initials';

describe('initials', () => {
    it('takes the first and last word', () => {
        expect(initials('Jane Q. Doe')).toBe('JD');
    });

    it('takes two letters of a single word', () => {
        expect(initials('marek')).toBe('MA');
    });

    it('splits email-like names', () => {
        expect(initials('jane.doe@example.com')).toBe('JC');
    });

    it('returns a placeholder for blank names', () => {
        expect(initials('   ')).toBe('?');
    });
});
