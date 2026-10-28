import { describe, expect, it } from 'vitest';
import { addDays, daysBetween, isoDay, parseDay, relativeDay } from './days.js';

describe('days', () => {
    it('formats and parses a day without shifting it', () => {
        expect(isoDay(parseDay('2026-03-29'))).toBe('2026-03-29');
    });

    it('adds days across months and years', () => {
        expect(addDays('2026-12-31', 1)).toBe('2027-01-01');
        expect(addDays('2026-03-01', -1)).toBe('2026-02-28');
    });

    it('counts days across daylight saving time changes', () => {
        expect(daysBetween('2026-10-24', '2026-10-26')).toBe(2);
        expect(daysBetween('2026-03-28', '2026-03-30')).toBe(2);
    });

    it('names relative days', () => {
        expect(relativeDay('2026-10-06', '2026-10-06')).toBe('today');
        expect(relativeDay('2026-10-07', '2026-10-06')).toBe('tomorrow');
        expect(relativeDay('2026-10-05', '2026-10-06')).toBe('yesterday');
        expect(relativeDay('2026-10-09', '2026-10-06')).toBe('weekday');
        expect(relativeDay('2026-11-09', '2026-10-06')).toBe('later');
        expect(relativeDay('2026-09-01', '2026-10-06')).toBe('past');
        expect(relativeDay(null, '2026-10-06')).toBeNull();
    });
});
